<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeleteExpiredInvitedUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invited-users:delete-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete pending invitations (users with invitation_status=pending) that are older than 7 days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting deletion of expired pending invitations...');

        try {
            // Get the date 7 days ago
            $expirationDate = Carbon::now()->subDays(7);

            // Find and delete pending invitations (users with invitation_status='pending') created before expiration date
            $expiredUsers = User::where('invitation_status', 'pending')
                ->where('created_at', '<', $expirationDate)
                ->get();

            $count = $expiredUsers->count();

            if ($count > 0) {
                foreach ($expiredUsers as $user) {
                    $this->info("Deleting expired pending invitation: {$user->email}");
                    $user->delete();
                }

                $this->info("Successfully deleted {$count} expired pending invitations.");
                Log::info("Cron job: Deleted {$count} expired pending invitations.");
            } else {
                $this->info('No expired pending invitations found.');
                Log::info('Cron job: No expired pending invitations found.');
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("An error occurred: {$e->getMessage()}");
            Log::error("Cron job error: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
