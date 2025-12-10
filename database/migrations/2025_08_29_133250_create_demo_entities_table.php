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
        Schema::create('demo_entities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('entity_type', 191);
            $table->uuid('entity_id');
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id'], 'demo_entity_unique');
            $table->index('entity_type');
            $table->index('entity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_entities');
    }
};
