<?php

namespace App\Console\Commands\Cognito;

use App\Models\User;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Exception;
use Illuminate\Console\Command;

class CognitoSetUserPassword extends Command
{
    protected $signature = 'cognito:set-password {email} {password}';

    protected $description = 'Set a permanent password for a Cognito user by email';

    protected CognitoIdentityProviderClient $cognito;

    public function __construct()
    {
        parent::__construct();
        // Initialize the Cognito client

        $this->cognito = new CognitoIdentityProviderClient([
            'region' => config('services.cognito.region'),
            'version' => 'latest',
        ]);
    }

    public function handle(): void
    {
        $email = $this->argument('email');
        $password = $this->argument('password');
        $userPoolId = config('services.cognito.user_pool_id');

        // Find user by email and get cognito_id
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email $email not found in database");

            return;
        }

        if (! $user->cognito_id) {
            $this->error("User with email $email does not have a Cognito ID");

            return;
        }

        try {
            // Get current user status before setting password
            $userDetails = $this->cognito->adminGetUser([
                'UserPoolId' => $userPoolId,
                'Username' => $user->cognito_id,
            ]);

            $userDetailsArray = $userDetails->toArray();
            $currentStatus = array_key_exists('UserStatus', $userDetailsArray) && is_scalar($userDetailsArray['UserStatus']) ? (string) $userDetailsArray['UserStatus'] : 'unknown';
            $this->info("Current user status: $currentStatus");

            // If user has FORCE_CHANGE_PASSWORD status, we need to reset it properly
            if ($currentStatus === 'FORCE_CHANGE_PASSWORD') {
                // First, create a temporary password to reset the user
                $tempPassword = 'TempPassword123!@#'.uniqid();

                // Reset user with temporary password
                $this->cognito->adminResetUserPassword([
                    'UserPoolId' => $userPoolId,
                    'Username' => $user->cognito_id,
                ]);

                // Set permanent password which will change status to CONFIRMED
                $this->cognito->adminSetUserPassword([
                    'UserPoolId' => $userPoolId,
                    'Username' => $user->cognito_id,
                    'Password' => $password,
                    'Permanent' => true,
                ]);

                $this->info('✓ User status changed from FORCE_CHANGE_PASSWORD to CONFIRMED');
            } else {
                // For users already CONFIRMED or other statuses, just set the password
                $this->cognito->adminSetUserPassword([
                    'UserPoolId' => $userPoolId,
                    'Username' => $user->cognito_id,
                    'Password' => $password,
                    'Permanent' => true,
                ]);

                if ($currentStatus === 'CONFIRMED') {
                    $this->info('User is already CONFIRMED');
                }
            }

            $this->info("✓ Password successfully set for user: $email (Cognito ID: $user->cognito_id)");
        } catch (Exception $e) {
            $this->error("Failed to set password for user: $email. Error: ".$e->getMessage());
        }
    }
}
