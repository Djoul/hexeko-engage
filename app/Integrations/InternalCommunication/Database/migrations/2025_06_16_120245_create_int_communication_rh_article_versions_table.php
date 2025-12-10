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
        Schema::create('int_communication_rh_article_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('article_id');
            $table->jsonb('content');
            $table->text('prompt')->nullable();
            $table->text('llm_response')->nullable();
            $table->integer('version_number');
            $table->timestamps();
            $table->uuid('article_translation_id')->nullable();
            $table->string('language', 5)->nullable();
            $table->string('title')->nullable();
            $table->uuid('llm_request_id')->nullable();
            $table->uuid('author_id')->nullable();
            $table->string('illustration')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_communication_rh_article_versions');
    }
};
