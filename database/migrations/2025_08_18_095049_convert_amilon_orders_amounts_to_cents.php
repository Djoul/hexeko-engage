<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, multiply existing values by 100 to convert to cents
        DB::statement('UPDATE int_vouchers_amilon_orders SET amount = amount * 100 WHERE amount IS NOT NULL');
        DB::statement('UPDATE int_vouchers_amilon_orders SET price_paid = price_paid * 100 WHERE price_paid IS NOT NULL');
        DB::statement('UPDATE int_vouchers_amilon_orders SET gross_amount = gross_amount * 100 WHERE gross_amount IS NOT NULL');
        DB::statement('UPDATE int_vouchers_amilon_orders SET net_amount = net_amount * 100 WHERE net_amount IS NOT NULL');
        DB::statement('UPDATE int_vouchers_amilon_orders SET total_amount = total_amount * 100 WHERE total_amount IS NOT NULL');
        DB::statement('UPDATE int_vouchers_amilon_orders SET balance_amount_used = balance_amount_used * 100 WHERE balance_amount_used IS NOT NULL');

        // Then change column types from decimal to integer
        Schema::table('int_vouchers_amilon_orders', function (Blueprint $table): void {
            // Change all amount columns from decimal to integer
            $table->integer('amount')->change();
            $table->integer('price_paid')->nullable()->change();
            $table->integer('gross_amount')->nullable()->change();
            $table->integer('net_amount')->nullable()->change();
            $table->integer('total_amount')->nullable()->change();
            $table->integer('balance_amount_used')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Change column types back to decimal
        Schema::table('int_vouchers_amilon_orders', function (Blueprint $table): void {
            // Change all amount columns back to decimal
            $table->decimal('amount', 10, 2)->change();
            $table->decimal('price_paid', 10, 2)->nullable()->change();
            $table->decimal('gross_amount', 10, 2)->nullable()->change();
            $table->decimal('net_amount', 10, 2)->nullable()->change();
            $table->decimal('total_amount', 10, 2)->nullable()->change();
            $table->decimal('balance_amount_used', 10, 2)->nullable()->change();
        });

        // Convert cents back to decimal values
        DB::statement('UPDATE int_vouchers_amilon_orders SET amount = amount / 100 WHERE amount IS NOT NULL');
        DB::statement('UPDATE int_vouchers_amilon_orders SET price_paid = price_paid / 100 WHERE price_paid IS NOT NULL');
        DB::statement('UPDATE int_vouchers_amilon_orders SET gross_amount = gross_amount / 100 WHERE gross_amount IS NOT NULL');
        DB::statement('UPDATE int_vouchers_amilon_orders SET net_amount = net_amount / 100 WHERE net_amount IS NOT NULL');
        DB::statement('UPDATE int_vouchers_amilon_orders SET total_amount = total_amount / 100 WHERE total_amount IS NOT NULL');
        DB::statement('UPDATE int_vouchers_amilon_orders SET balance_amount_used = balance_amount_used / 100 WHERE balance_amount_used IS NOT NULL');
    }
};
