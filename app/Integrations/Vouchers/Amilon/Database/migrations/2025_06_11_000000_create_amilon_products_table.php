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
        Schema::create('int_vouchers_amilon_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('category')->nullable();
            $table->uuid('category_id')->nullable();
            $table->string('merchant_id');
            $table->string('product_code')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency')->nullable();
            $table->string('country')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            // Add indexes for faster lookups
            $table->index('merchant_id');
            $table->index('category_id');
            $table->index('country');
            $table->index('product_code');

            // Add foreign key constraint
            $table->foreign('merchant_id')
                ->references('merchant_id')
                ->on('int_vouchers_amilon_merchants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_vouchers_amilon_products');
    }
};
