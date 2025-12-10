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
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('invoice_number');
            $table->string('invoice_type');
            $table->string('issuer_type');
            $table->uuid('issuer_id')->nullable();
            $table->string('recipient_type');
            $table->uuid('recipient_id');

            $table->date('billing_period_start');
            $table->date('billing_period_end');

            $table->integer('subtotal_htva');
            $table->decimal('vat_rate', 5, 2);
            $table->integer('vat_amount');
            $table->integer('total_ttc');
            $table->string('currency', 3)->default('EUR');

            $table->string('status');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->date('due_date')->nullable();

            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['invoice_number', 'issuer_id'], 'invoices_invoice_number_issuer_unique');
            $table->index('invoice_number', 'idx_invoices_number');
            $table->index(['recipient_type', 'recipient_id'], 'idx_invoices_recipient');
            $table->index(['billing_period_start', 'billing_period_end'], 'idx_invoices_period');
            $table->index('status', 'idx_invoices_status');
        });

        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_subtotal_htva_positive CHECK (subtotal_htva >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_total_ttc_positive CHECK (total_ttc >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
