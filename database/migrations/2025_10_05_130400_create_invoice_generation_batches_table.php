<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_generation_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('batch_id')->unique();
            $table->string('month_year');
            $table->unsignedInteger('total_invoices');
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('status')->default('in_progress');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('batch_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_generation_batches');
    }
};
