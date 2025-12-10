<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('division_balances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('division_id')->unique();
            $table->integer('balance')->default(0);
            $table->foreign('division_id')->references('id')->on('divisions');
            $table->timestamp('last_invoice_at')->nullable();
            $table->timestamp('last_payment_at')->nullable();
            $table->timestamp('last_credit_at')->nullable();
            $table->timestamps();

            $table->index('division_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('division_balances');
    }
};
