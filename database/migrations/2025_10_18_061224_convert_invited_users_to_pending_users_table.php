<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Converts all existing invited_users records to users with invitation_status='pending'
     * and creates corresponding financer_user pivot records.
     */
    public function up(): void
    {
        // Get all invited users from the old table
        $invitedUsers = DB::table('invited_users')->get();

        if ($invitedUsers->isEmpty()) {
            Log::info('No invited users to migrate.');

            return;
        }

        Log::info("Migrating {$invitedUsers->count()} invited users to users table...");

        $migratedCount = 0;
        $skippedCount = 0;

        foreach ($invitedUsers as $invitedUser) {
            // Check if user with same email already exists (ANY status, not just pending)
            $existingUser = DB::table('users')
                ->where('email', $invitedUser->email)
                ->first();

            if ($existingUser) {
                /** @var string $status */
                $status = $existingUser->invitation_status ?? 'unknown';
                Log::warning("Skipping {$invitedUser->email} - already exists in users table (status: {$status})");
                $skippedCount++;

                continue;
            }

            // Decode extra_data JSON
            /** @var array<string, mixed> $extraData */
            $extraData = json_decode($invitedUser->extra_data ?? '{}', true) ?: [];

            // Prepare invitation_metadata JSON
            $invitationMetadata = [
                'financer_id' => $invitedUser->financer_id,
                'sirh_id' => $invitedUser->sirh_id,
                'intended_role' => is_array($extraData) && array_key_exists('intended_role', $extraData) ? $extraData['intended_role'] : null,
            ];

            // Remove null values
            $invitationMetadata = array_filter($invitationMetadata, fn ($value): bool => $value !== null);

            // Insert into users table
            $userId = DB::table('users')->insertGetId([
                'id' => $invitedUser->id, // Preserve UUID
                'first_name' => $invitedUser->first_name,
                'last_name' => $invitedUser->last_name,
                'email' => $invitedUser->email,
                'email_verified_at' => null,
                'invitation_status' => 'pending',
                'invitation_token' => is_array($extraData) && array_key_exists('invitation_token', $extraData) ? $extraData['invitation_token'] : null,
                'invitation_expires_at' => is_array($extraData) && array_key_exists('expires_at', $extraData) ? $extraData['expires_at'] : null,
                'invited_at' => $invitedUser->created_at,
                'invited_by' => is_array($extraData) && array_key_exists('invited_by', $extraData) ? $extraData['invited_by'] : null,
                'invitation_metadata' => json_encode($invitationMetadata),
                'enabled' => false, // Not enabled until invitation accepted
                'locale' => 'fr-FR', // Default locale
                'currency' => 'EUR', // Default currency
                'timezone' => 'Europe/Paris', // Default timezone
                'created_at' => $invitedUser->created_at,
                'updated_at' => $invitedUser->updated_at,
            ]);

            // Create financer_user pivot record
            DB::table('financer_user')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $invitedUser->id,
                'financer_id' => $invitedUser->financer_id,
                'active' => false, // Not active until invitation accepted
                'from' => $invitedUser->created_at,
                'to' => null,
                'sirh_id' => $invitedUser->sirh_id ?? '',
                'created_at' => $invitedUser->created_at,
                'updated_at' => $invitedUser->updated_at,
            ]);

            $migratedCount++;
        }

        Log::info("Migration complete: {$migratedCount} migrated, {$skippedCount} skipped");
    }

    /**
     * Reverse the migrations.
     *
     * WARNING: This will delete all pending invitation users created by this migration.
     * Use with caution!
     */
    public function down(): void
    {
        Log::warning('Rolling back invited users to users migration...');

        // Get all UUIDs from invited_users
        $invitedUserIds = DB::table('invited_users')->pluck('id');

        if ($invitedUserIds->isEmpty()) {
            Log::info('No invited users found to rollback.');

            return;
        }

        // Delete financer_user pivot records for these users
        $pivotDeleted = DB::table('financer_user')
            ->whereIn('user_id', $invitedUserIds)
            ->delete();

        // Delete users that match the invited_users IDs and are still pending
        $usersDeleted = DB::table('users')
            ->whereIn('id', $invitedUserIds)
            ->where('invitation_status', 'pending')
            ->delete();

        Log::info("Rollback complete: {$usersDeleted} users deleted, {$pivotDeleted} pivot records deleted");
    }
};
