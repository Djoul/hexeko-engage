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
        Schema::create('int_vouchers_amilon_merchant_category', function (Blueprint $table): void {
            $table->uuid('merchant_id');
            $table->uuid('category_id');
            $table->timestamps();

            $table->primary(['merchant_id', 'category_id']);

            $table->index('merchant_id');
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_vouchers_amilon_merchant_category');
    }
};
