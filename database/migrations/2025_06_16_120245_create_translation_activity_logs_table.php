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
        Schema::create('translation_activity_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('user_id')->nullable();
            $table->enum('action', ['created', 'updated', 'deleted']);
            $table->enum('target_type', ['key', 'value']);
            $table->bigInteger('target_id');
            $table->string('locale', 10)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translation_activity_logs');
    }
};
