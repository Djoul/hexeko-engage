<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds invitation fields to users table for unified User/InvitedUser model.
     * Sprint 1 - Foundation: 8 fields + 4 indexes for performance.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Invitation status: 'pending', 'accepted', 'expired', 'revoked', or null for regular users
            $table->string('invitation_status', 20)->nullable()->after('enabled');

            // Unique invitation token (44 chars)
            $table->string('invitation_token', 64)->nullable()->unique()->after('invitation_status');

            // Invitation expiration timestamp (default 7 days)
            $table->timestamp('invitation_expires_at')->nullable()->after('invitation_token');

            // User who created the invitation (foreign key to users.id)
            $table->uuid('invited_by')->nullable()->after('invitation_expires_at');

            // When invitation was created
            $table->timestamp('invited_at')->nullable()->after('invited_by');

            // When invitation was accepted
            $table->timestamp('invitation_accepted_at')->nullable()->after('invited_at');

            // Invitation extra metadata (JSON)
            $table->json('invitation_metadata')->nullable()->after('invitation_accepted_at');

            // Original invited user ID for migration traceability
            $table->uuid('original_invited_user_id')->nullable()->after('invitation_metadata');

            // Performance indexes
            $table->index('invitation_status');
            $table->index('invitation_token');
            $table->index('invitation_expires_at');
            $table->index('invited_by');

            // Foreign key constraint
            $table->foreign('invited_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Drop foreign key first
            $table->dropForeign(['invited_by']);

            // Drop indexes
            $table->dropIndex(['invitation_status']);
            $table->dropIndex(['invitation_token']);
            $table->dropIndex(['invitation_expires_at']);
            $table->dropIndex(['invited_by']);

            // Drop columns
            $table->dropColumn([
                'invitation_status',
                'invitation_token',
                'invitation_expires_at',
                'invited_by',
                'invited_at',
                'invitation_accepted_at',
                'invitation_metadata',
                'original_invited_user_id',
            ]);
        });
    }
};
