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
            // Only add columns that don't already exist
            if (! Schema::hasColumn('int_vouchers_amilon_orders', 'stripe_payment_id')) {
                $table->string('stripe_payment_id')->nullable()->after('payment_method')
                    ->comment('Reference to Stripe payment intent ID');
            }

            if (! Schema::hasColumn('int_vouchers_amilon_orders', 'balance_amount_used')) {
                $table->decimal('balance_amount_used', 10, 2)->nullable()->after('stripe_payment_id')
                    ->comment('Amount paid with balance in mixed payments');
            }

            if (! Schema::hasColumn('int_vouchers_amilon_orders', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->nullable()->after('amount')
                    ->comment('Total order amount');
            }

            // Add index for stripe_payment_id if it doesn't exist
            $indexExists = collect(Schema::getIndexes('int_vouchers_amilon_orders'))
                ->pluck('name')
                ->contains('int_vouchers_amilon_orders_stripe_payment_id_index');

            if (! $indexExists) {
                $table->index('stripe_payment_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_vouchers_amilon_orders', function (Blueprint $table): void {
            // Only drop index if it exists
            $indexExists = collect(Schema::getIndexes('int_vouchers_amilon_orders'))
                ->pluck('name')
                ->contains('int_vouchers_amilon_orders_stripe_payment_id_index');

            if ($indexExists) {
                $table->dropIndex(['stripe_payment_id']);
            }

            // Only drop columns that exist
            $columnsToDrop = [];
            if (Schema::hasColumn('int_vouchers_amilon_orders', 'stripe_payment_id')) {
                $columnsToDrop[] = 'stripe_payment_id';
            }
            if (Schema::hasColumn('int_vouchers_amilon_orders', 'balance_amount_used')) {
                $columnsToDrop[] = 'balance_amount_used';
            }
            if (Schema::hasColumn('int_vouchers_amilon_orders', 'total_amount')) {
                $columnsToDrop[] = 'total_amount';
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
