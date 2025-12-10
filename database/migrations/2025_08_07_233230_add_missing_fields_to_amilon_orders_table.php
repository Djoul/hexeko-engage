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
            // Add missing fields that are in the Model but not in the database
            if (! Schema::hasColumn('int_vouchers_amilon_orders', 'voucher_pin')) {
                $table->string('voucher_pin')->nullable()->after('voucher_code')
                    ->comment('PIN code for the voucher from API response');
            }

            if (! Schema::hasColumn('int_vouchers_amilon_orders', 'product_name')) {
                $table->string('product_name')->nullable()->after('voucher_pin')
                    ->comment('Product/Retailer name from API response');
            }

            if (! Schema::hasColumn('int_vouchers_amilon_orders', 'currency')) {
                $table->string('currency', 3)->default('EUR')->after('product_name')
                    ->comment('Currency code for the order');
            }

            // Add index for currency for better query performance
            $table->index('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_vouchers_amilon_orders', function (Blueprint $table): void {
            // Drop index first
            if (Schema::hasColumn('int_vouchers_amilon_orders', 'currency')) {
                $table->dropIndex(['currency']);
            }

            // Drop columns
            $columnsToDrop = [];
            if (Schema::hasColumn('int_vouchers_amilon_orders', 'voucher_pin')) {
                $columnsToDrop[] = 'voucher_pin';
            }
            if (Schema::hasColumn('int_vouchers_amilon_orders', 'product_name')) {
                $columnsToDrop[] = 'product_name';
            }
            if (Schema::hasColumn('int_vouchers_amilon_orders', 'currency')) {
                $columnsToDrop[] = 'currency';
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
