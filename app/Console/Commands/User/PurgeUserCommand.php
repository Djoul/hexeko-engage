<?php

namespace App\Console\Commands\User;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PurgeUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'user:purge {user_id : The UUID of the user to purge}
                            {--force : Force deletion without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Completely purge a user and all associated data from the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->argument('user_id');

        // Load user with soft deletes
        $user = User::withTrashed()->find($userId);

        if (! $user) {
            $this->error("User with ID [{$userId}] not found.");

            return self::FAILURE;
        }

        // Display user info
        $this->info('User to purge:');
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $user->id],
                ['Email', $user->email],
                ['Name', $user->full_name ?? 'N/A'],
                ['Created', $user->created_at?->toDateTimeString() ?? 'N/A'],
                ['Deleted', $user->deleted_at ? 'Yes ('.$user->deleted_at->toDateTimeString().')' : 'No'],
            ]
        );

        // Confirm deletion
        if (! $this->option('force')) {
            if (! $this->confirm('Are you absolutely sure you want to PERMANENTLY delete this user and ALL associated data?')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }

            if (! $this->confirm('This action CANNOT be undone. Continue?', false)) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        try {
            DB::beginTransaction();

            $this->info('Purging user data...');

            // 1. Delete credit balances (polymorphic)
            $creditsCount = $user->credits()->forceDelete();
            $this->line("✓ Deleted {$creditsCount} credit balance(s)");

            // 2. Delete engagement logs
            $engagementLogsCount = $user->engagementLogs()->forceDelete();
            $this->line("✓ Deleted {$engagementLogsCount} engagement log(s)");

            // 3. Delete notification topic subscriptions (BEFORE push_subscriptions)
            $topicSubscriptionsCount = DB::table('notification_topic_subscriptions')
                ->whereIn('push_subscription_id', function ($query) use ($userId): void {
                    $query->select('id')
                        ->from('push_subscriptions')
                        ->where('user_id', $userId);
                })
                ->count();
            DB::table('notification_topic_subscriptions')
                ->whereIn('push_subscription_id', function ($query) use ($userId): void {
                    $query->select('id')
                        ->from('push_subscriptions')
                        ->where('user_id', $userId);
                })
                ->delete();
            $this->line("✓ Deleted {$topicSubscriptionsCount} topic subscription(s)");

            // 4. Delete push subscriptions
            $pushSubscriptionsCount = $user->pushSubscriptions()->forceDelete();
            $this->line("✓ Deleted {$pushSubscriptionsCount} push subscription(s)");

            // 4. Detach pinned modules
            $pinnedModulesCount = $user->pinnedModules()->count();
            $user->pinnedModules()->detach();
            $this->line("✓ Detached {$pinnedModulesCount} pinned module(s)");

            // 5. Detach pinned HR Tools links
            $pinnedLinksCount = $user->pinnedHRToolsLinks()->count();
            $user->pinnedHRToolsLinks()->detach();
            $this->line("✓ Detached {$pinnedLinksCount} pinned HR Tools link(s)");

            // 6. Delete financer_user pivot records
            $financersCount = DB::table('financer_user')
                ->where('user_id', $userId)
                ->count();
            DB::table('financer_user')
                ->where('user_id', $userId)
                ->delete();
            $this->line("✓ Deleted {$financersCount} financer association(s)");

            // 7. Delete roles and permissions (Spatie)
            $rolesCount = $user->roles()->count();
            $user->roles()->detach();
            $this->line("✓ Detached {$rolesCount} role(s)");

            $permissionsCount = $user->permissions()->count();
            $user->permissions()->detach();
            $this->line("✓ Detached {$permissionsCount} permission(s)");

            // 8. Delete model_has_roles records (use model_uuid for UUID models)
            DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->where('model_uuid', $userId)
                ->delete();

            // 9. Delete model_has_permissions records (use model_uuid for UUID models)
            DB::table('model_has_permissions')
                ->where('model_type', User::class)
                ->where('model_uuid', $userId)
                ->delete();

            // 10. Delete activity logs (Spatie)
            $activitiesCount = DB::table('activity_log')
                ->where('subject_type', User::class)
                ->where('subject_id', $userId)
                ->orWhere('causer_type', User::class)
                ->where('causer_id', $userId)
                ->count();
            DB::table('activity_log')
                ->where('subject_type', User::class)
                ->where('subject_id', $userId)
                ->orWhere('causer_type', User::class)
                ->where('causer_id', $userId)
                ->delete();
            $this->line("✓ Deleted {$activitiesCount} activity log(s)");

            // 11. Delete audits (OwenIt)
            $auditsCount = DB::table('audits')
                ->where('auditable_type', User::class)
                ->where('auditable_id', $userId)
                ->orWhere('user_type', User::class)
                ->where('user_id', $userId)
                ->count();
            DB::table('audits')
                ->where('auditable_type', User::class)
                ->where('auditable_id', $userId)
                ->orWhere('user_type', User::class)
                ->where('user_id', $userId)
                ->delete();
            $this->line("✓ Deleted {$auditsCount} audit(s)");

            // 12. Delete push notifications (custom table - author_id column)
            $pushNotificationsCount = DB::table('push_notifications')
                ->where('author_id', $userId)
                ->count();
            DB::table('push_notifications')
                ->where('author_id', $userId)
                ->delete();
            $this->line("✓ Deleted {$pushNotificationsCount} push notification(s)");

            // 13. Delete media (Spatie Media Library)
            $mediaCount = $user->media()->count();
            $user->clearMediaCollection();
            $this->line("✓ Deleted {$mediaCount} media file(s)");

            // 14. Delete admin audit logs
            $adminAuditsCount = DB::table('admin_audit_logs')
                ->where('user_id', $userId)
                ->count();
            DB::table('admin_audit_logs')
                ->where('user_id', $userId)
                ->delete();
            $this->line("✓ Deleted {$adminAuditsCount} admin audit log(s)");

            // 15. Delete translation activity logs
            $translationLogsCount = DB::table('translation_activity_logs')
                ->where('user_id', $userId)
                ->count();
            DB::table('translation_activity_logs')
                ->where('user_id', $userId)
                ->delete();
            $this->line("✓ Deleted {$translationLogsCount} translation activity log(s)");

            // 16. Delete Amilon orders (if any)
            $amilonOrdersCount = DB::table('int_vouchers_amilon_orders')
                ->where('user_id', $userId)
                ->count();
            DB::table('int_vouchers_amilon_orders')
                ->where('user_id', $userId)
                ->delete();
            $this->line("✓ Deleted {$amilonOrdersCount} Amilon order(s)");

            // 17. Delete article interactions (if table exists)
            $articleInteractionsCount = 0;
            if (Schema::hasTable('int_internal_communication_article_interactions')) {
                $articleInteractionsCount = DB::table('int_internal_communication_article_interactions')
                    ->where('user_id', $userId)
                    ->count();
                DB::table('int_internal_communication_article_interactions')
                    ->where('user_id', $userId)
                    ->delete();
            }
            $this->line("✓ Deleted {$articleInteractionsCount} article interaction(s)");

            // 18. Finally, force delete the user
            $user->forceDelete();
            $this->line('✓ User permanently deleted');

            DB::commit();

            $this->newLine();
            $this->info("User [{$userId}] and all associated data have been permanently deleted.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            DB::rollBack();

            $this->error('Failed to purge user: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
