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
        Schema::create('engagement_metrics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('date');
            $table->string('metric');
            $table->string('module')->nullable();
            $table->json('data');
            $table->timestamps();

            $table->unique(['date', 'metric', 'module']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engagement_metrics');
    }
};
