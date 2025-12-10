<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('int_vouchers_amilon_orders', function (Blueprint $table): void {
            $table->integer('recovery_attempts')->default(0)->after('status');
            $table->text('last_error')->nullable()->after('recovery_attempts');
            $table->timestamp('last_recovery_attempt')->nullable()->after('last_error');
            $table->timestamp('next_retry_at')->nullable()->after('last_recovery_attempt');

            // Add index for efficient queries
            $table->index(['payment_status', 'status', 'recovery_attempts']);
            $table->index('next_retry_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_vouchers_amilon_orders', function (Blueprint $table): void {
            $table->dropIndex(['payment_status', 'status', 'recovery_attempts']);
            $table->dropIndex(['next_retry_at']);

            $table->dropColumn([
                'recovery_attempts',
                'last_error',
                'last_recovery_attempt',
                'next_retry_at',
            ]);
        });
    }
};
