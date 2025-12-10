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
            // Remove deprecated payment status columns
            // These are no longer needed as payment status is tracked in the generic Stripe integration
            $table->dropColumn(['payment_status', 'payment_completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_vouchers_amilon_orders', function (Blueprint $table): void {
            // Restore the columns if rolling back
            $table->string('payment_status')->default('pending')->after('status')
                ->comment('Payment status: pending, completed, failed');

            $table->timestamp('payment_completed_at')->nullable()->after('payment_status');
        });
    }
};
