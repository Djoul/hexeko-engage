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
            $table->string('payment_id')->nullable()->after('user_id');
            $table->dateTime('order_date')->nullable()->after('payment_id');
            $table->string('order_status')->nullable()->after('payment_id');
            $table->float('gross_amount')->nullable()->after('order_date');
            $table->float('net_amount')->nullable()->after('gross_amount');
            $table->integer('total_requested_codes')->nullable()->after('net_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_vouchers_amilon_orders', function (Blueprint $table): void {
            $table->dropColumn('payment_id');
        });
    }
};
