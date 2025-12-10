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
        Schema::create('module_pricing_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->uuid('entity_id')->comment('Division or Financer ID');
            $table->string('entity_type')->comment('division or financer');
            $table->integer('old_price')->nullable()->comment('Previous price in euro cents');
            $table->integer('new_price')->nullable()->comment('New price in euro cents');
            $table->string('price_type')->comment('core_package or module_price');
            $table->uuid('changed_by')->nullable()->comment('User ID who made the change');
            $table->text('reason')->nullable()->comment('Reason for price change');
            $table->timestamps();

            // Indexes
            $table->index(['module_id', 'entity_id', 'entity_type']);
            $table->index('changed_by');
            $table->index('created_at');

            // Foreign keys
            $table->foreign('module_id')->references('id')->on('modules');
            $table->foreign('changed_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_pricing_history');
    }
};
