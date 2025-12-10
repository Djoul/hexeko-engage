<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('invoice_type');
            $table->string('year', 4);
            $table->unsignedBigInteger('sequence')->default(0);
            $table->timestamps();

            $table->unique(['invoice_type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
