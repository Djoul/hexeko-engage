<?php

namespace App\Jobs\Push;

use App\Models\PushNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PushNotification $notification
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // This will be implemented later
        // For now, just a placeholder for scheduled notifications
    }
}
