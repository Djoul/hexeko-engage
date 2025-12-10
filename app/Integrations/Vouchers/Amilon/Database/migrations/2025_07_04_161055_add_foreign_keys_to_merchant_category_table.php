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
        Schema::table('int_vouchers_amilon_merchant_category', function (Blueprint $table): void {
            $table->foreign('merchant_id')->references('id')->on('int_vouchers_amilon_merchants');
            $table->foreign('category_id')->references('id')->on('int_vouchers_amilon_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_vouchers_amilon_merchant_category', function (Blueprint $table): void {
            $table->dropForeign(['merchant_id']);
            $table->dropForeign(['category_id']);
        });
    }
};
