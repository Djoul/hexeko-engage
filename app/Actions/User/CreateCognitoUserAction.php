<?php

namespace App\Actions\User;

use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Services\CognitoService;
use App\Services\Models\UserService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;
use Mail;
use RuntimeException;

class CreateCognitoUserAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private User $user) {}

    public function handle(CognitoService $cognitoService, UserService $userService): void
    {
        try {
            $tempPassword = generateSecurePassword(10);
            $this->user->temp_password = $tempPassword;

            /** @var array<string,mixed> $response */
            $response = $cognitoService->createUser($this->user);

            if ($response && array_key_exists('User', $response)
                && (is_array($response['User']) && array_key_exists('Username', $response['User']))) {
                $username = $response['User']['Username'];
            } else {
                // Handle cases where 'Username' does not exist
                throw new RuntimeException("'Username' or 'User' key is missing in the response.");
            }

            $userService->update(
                $this->user,
                [
                    'cognito_id' => $username,
                    'temp_password' => $tempPassword,
                ]
            );

        } catch (Exception $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTrace()]);
            throw $e;
        } finally {
            Mail::to($this->user->email)->send(new WelcomeEmail($this->user));

        }
    }
}
