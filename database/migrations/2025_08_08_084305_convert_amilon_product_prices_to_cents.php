<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Convert all product prices from euros (decimal) to cents (integer).
     * This ensures consistency across the application where all monetary
     * amounts are stored as integers in cents to avoid floating point issues.
     */
    public function up(): void
    {
        // First, convert existing euro values to cents
        DB::statement('
            UPDATE int_vouchers_amilon_products
            SET price = CASE
                WHEN price IS NOT NULL THEN ROUND(price * 100)
                ELSE NULL
            END,
            net_price = CASE
                WHEN net_price IS NOT NULL THEN ROUND(net_price * 100)
                ELSE NULL
            END,
            discount = CASE
                WHEN discount IS NOT NULL THEN ROUND(discount * 100)
                ELSE NULL
            END
        ');

        // Then, alter the column types from decimal/float to integer
        Schema::table('int_vouchers_amilon_products', function (Blueprint $table): void {
            // Change price from decimal(10,2) to integer (stores cents)
            $table->integer('price')->nullable()->change();

            // Change net_price from float to integer (stores cents)
            $table->integer('net_price')->nullable()->change();

            // Change discount from float to integer (stores cents)
            $table->integer('discount')->nullable()->change();
        });

        // Add comments to clarify the unit (PostgreSQL syntax)
        DB::statement("COMMENT ON COLUMN int_vouchers_amilon_products.price IS 'Price in cents (1 euro = 100 cents)'");
        DB::statement("COMMENT ON COLUMN int_vouchers_amilon_products.net_price IS 'Net price in cents (1 euro = 100 cents)'");
        DB::statement("COMMENT ON COLUMN int_vouchers_amilon_products.discount IS 'Discount amount in cents (1 euro = 100 cents)'");
    }

    /**
     * Reverse the migrations.
     *
     * Convert prices back from cents to euros for rollback.
     */
    public function down(): void
    {
        // First, convert cents back to euros
        DB::statement('
            UPDATE int_vouchers_amilon_products
            SET price = CASE
                WHEN price IS NOT NULL THEN price / 100
                ELSE NULL
            END,
            net_price = CASE
                WHEN net_price IS NOT NULL THEN net_price / 100
                ELSE NULL
            END,
            discount = CASE
                WHEN discount IS NOT NULL THEN discount / 100
                ELSE NULL
            END
        ');

        // Then, change column types back to original
        Schema::table('int_vouchers_amilon_products', function (Blueprint $table): void {
            // Restore price to decimal(10,2)
            $table->decimal('price', 10, 2)->nullable()->change();

            // Restore net_price to float
            $table->float('net_price')->nullable()->change();

            // Restore discount to float
            $table->float('discount')->nullable()->change();
        });

        // Remove comments (PostgreSQL doesn't need explicit removal, they're replaced with column change)
    }
};
