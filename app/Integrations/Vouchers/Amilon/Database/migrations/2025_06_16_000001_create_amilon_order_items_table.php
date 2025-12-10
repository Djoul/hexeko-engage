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
        Schema::create('int_vouchers_amilon_order_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('int_vouchers_amilon_orders')->onDelete('cascade');
            $table->foreignUuid('product_id')->constrained('int_vouchers_amilon_products')->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('price', 10, 2)->nullable();
            $table->jsonb('vouchers')->nullable();
            $table->timestamps();

            // Add index for faster lookups
            $table->index('order_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_vouchers_amilon_order_items');
    }
};
