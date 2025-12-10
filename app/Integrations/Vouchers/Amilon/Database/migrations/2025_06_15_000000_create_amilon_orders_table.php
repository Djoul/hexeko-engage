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
        Schema::create('int_vouchers_amilon_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('merchant_id');
            $table->decimal('amount', 10, 2);
            $table->string('external_order_id')->unique();
            $table->string('order_id')->nullable();
            $table->string('status')->nullable();
            $table->decimal('price_paid', 10, 2)->nullable();
            $table->text('download_url')->nullable();
            $table->uuid('user_id')->nullable();
            $table->timestamps();

            // Add index for faster lookups
            $table->index('merchant_id');
            $table->index('status');
            $table->index('external_order_id');
            $table->index('order_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_vouchers_amilon_orders');
    }
};
