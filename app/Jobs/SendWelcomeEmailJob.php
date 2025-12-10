<?php

namespace App\Jobs;

use App\Mail\WelcomeEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Log;
use stdClass;
use Throwable;

class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(
        public readonly string $invitedUserId,
        public readonly string $email,
        public readonly string $firstName,
        public readonly string $lastName
    ) {
        $this->onQueue(config('queue.connections.sqs.queue-email'));
    }

    public function handle(): void
    {
        try {
            // Create a temporary user object for the email template
            $tempUser = new stdClass;
            $tempUser->email = $this->email;
            $tempUser->first_name = $this->firstName;
            $tempUser->last_name = $this->lastName;
            $tempUser->id = $this->invitedUserId;

            Mail::to($this->email)->send(new WelcomeEmail($tempUser, $this->invitedUserId));

            Log::info('Welcome email sent successfully', [
                'invited_user_id' => $this->invitedUserId,
                'email' => $this->email,
            ]);
        } catch (Throwable $e) {
            // Check if it's an inactive recipient error
            if (str_contains($e->getMessage(), 'inactive') || str_contains($e->getMessage(), 'suppression')) {
                Log::warning('Email not sent - recipient marked as inactive', [
                    'invited_user_id' => $this->invitedUserId,
                    'email' => $this->email,
                    'message' => $e->getMessage(),
                ]);
                // Don't retry for inactive recipients
                $this->fail($e);
            } else {
                Log::error('Failed to send welcome email', [
                    'invited_user_id' => $this->invitedUserId,
                    'email' => $this->email,
                    'error' => $e->getMessage(),
                ]);
                // Let it retry
                throw $e;
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Welcome email job failed permanently', [
            'invited_user_id' => $this->invitedUserId,
            'email' => $this->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
