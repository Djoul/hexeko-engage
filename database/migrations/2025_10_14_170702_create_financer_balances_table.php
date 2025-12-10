<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financer_balances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('financer_id')->unique();
            $table->integer('balance')->default(0);
            $table->foreign('financer_id')->references('id')->on('financers');
            $table->timestamp('last_invoice_at')->nullable();
            $table->timestamp('last_payment_at')->nullable();
            $table->timestamp('last_credit_at')->nullable();
            $table->timestamps();

            $table->index('financer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financer_balances');
    }
};
