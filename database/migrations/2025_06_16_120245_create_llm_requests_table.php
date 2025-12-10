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
        Schema::create('llm_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('prompt');
            $table->text('response');
            $table->integer('tokens_used')->default(0);
            $table->string('engine_used', 50);
            $table->uuid('financer_id');
            $table->uuid('requestable_id');
            $table->string('requestable_type');
            $table->timestamps();
            $table->text('prompt_system')->nullable();

            $table->index(['requestable_type', 'requestable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_requests');
    }
};
