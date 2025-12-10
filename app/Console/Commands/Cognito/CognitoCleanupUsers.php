<?php

namespace App\Console\Commands\Cognito;

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CognitoCleanupUsers extends Command
{
    protected $signature = 'cognito:cleanup-users 
        {--dry-run : Run without actually deleting users}
        {--keep-file=keep_emails_dev.txt : Path to file containing emails to keep}';

    protected $description = 'Delete all Cognito users not in the keep list';

    protected CognitoIdentityProviderClient $cognito;

    public function __construct()
    {
        parent::__construct();

        $this->cognito = new CognitoIdentityProviderClient([
            'region' => config('services.cognito.region'),
            'version' => 'latest',
        ]);
    }

    public function handle(): void
    {
        $userPoolId = config('services.cognito.user_pool_id');
        $keepFileOption = $this->option('keep-file');
        $keepFile = is_string($keepFileOption) ? base_path($keepFileOption) : '';
        $isDryRun = $this->option('dry-run');

        if (! File::exists($keepFile)) {
            $this->error("Keep file not found: $keepFile");

            return;
        }

        // Load emails to keep
        $fileContent = file($keepFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($fileContent === false) {
            $this->error("Failed to read keep file: $keepFile");

            return;
        }
        $keepEmails = array_map('trim', $fileContent);
        $keepEmails = array_map('strtolower', $keepEmails);

        $this->info('Loaded '.count($keepEmails)." emails to keep from $keepFile");

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No users will be deleted');
        }

        try {
            $deletedCount = 0;
            $skippedCount = 0;
            $paginationToken = null;

            do {
                // List users from Cognito
                $params = [
                    'UserPoolId' => $userPoolId,
                    'Limit' => 60,
                ];

                if ($paginationToken) {
                    $params['PaginationToken'] = $paginationToken;
                }

                $result = $this->cognito->listUsers($params);
                $users = $result['Users'] ?? [];
                $paginationToken = $result['PaginationToken'] ?? null;

                if (! is_array($users)) {
                    $users = [];
                }

                foreach ($users as $user) {
                    if (! is_array($user)) {
                        continue;
                    }

                    $email = null;
                    $username = array_key_exists('Username', $user) && is_string($user['Username']) ? $user['Username'] : '';

                    // Extract email from attributes
                    $attributes = array_key_exists('Attributes', $user) && is_array($user['Attributes']) ? $user['Attributes'] : [];
                    foreach ($attributes as $attribute) {
                        if (! is_array($attribute)) {
                            continue;
                        }
                        if (array_key_exists('Name', $attribute) && $attribute['Name'] === 'email' && array_key_exists('Value', $attribute) && is_string($attribute['Value'])) {
                            $email = strtolower($attribute['Value']);
                            break;
                        }
                    }

                    if (in_array($email, [null, '', '0'], true)) {
                        $this->warn("No email found for user: $username");

                        continue;
                    }

                    // Check if email is in keep list
                    if (in_array($email, $keepEmails)) {
                        $this->info("✓ Keeping: $email (Username: $username)");
                        $skippedCount++;
                    } elseif (! $isDryRun) {
                        try {
                            $this->cognito->adminDeleteUser([
                                'UserPoolId' => $userPoolId,
                                'Username' => $username,
                            ]);
                            $this->error("✗ Deleted: $email (Username: $username)");
                            $deletedCount++;
                        } catch (Exception $e) {
                            $this->error("Failed to delete user $email: ".$e->getMessage());
                        }
                    } else {
                        $this->warn("✗ Would delete: $email (Username: $username)");
                        $deletedCount++;
                    }
                }

                // Show progress
                $this->info('Processed '.(is_array($users) ? count($users) : 0).' users in this batch...');

            } while ($paginationToken);

            // Summary
            $this->newLine();
            $this->info('=== SUMMARY ===');
            $this->info("Total users kept: $skippedCount");

            if ($isDryRun) {
                $this->warn("Total users that would be deleted: $deletedCount");
                $this->warn('Run without --dry-run to actually delete users');
            } else {
                $this->error("Total users deleted: $deletedCount");
            }

        } catch (Exception $e) {
            $this->error('Error processing users: '.$e->getMessage());
        }
    }
}
