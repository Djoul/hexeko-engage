<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->string('item_type');
            $table->uuid('module_id')->nullable();
            $table->jsonb('label');
            $table->jsonb('description')->nullable();
            $table->integer('beneficiaries_count')->nullable();
            $table->integer('unit_price_htva');
            $table->integer('quantity');
            $table->integer('subtotal_htva');
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->integer('vat_amount')->nullable();
            $table->integer('total_ttc')->nullable();
            $table->decimal('prorata_percentage', 5, 2)->nullable();
            $table->integer('prorata_days')->nullable();
            $table->integer('total_days')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('module_id')->references('id')->on('modules')->nullOnDelete();

            $table->index('invoice_id', 'idx_invoice_items_invoice');
            $table->index('module_id', 'idx_invoice_items_module');
            $table->index('item_type', 'idx_invoice_items_type');
        });

        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT invoice_items_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT invoice_items_subtotal_htva_positive CHECK (subtotal_htva >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
