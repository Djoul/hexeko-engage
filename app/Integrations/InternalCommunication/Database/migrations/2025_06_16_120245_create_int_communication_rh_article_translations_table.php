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
        Schema::create('int_communication_rh_article_translations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('article_id');
            $table->string('language', 5);
            $table->string('title');
            $table->text('content');
            $table->softDeletes();
            $table->timestamps();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->unique(['article_id', 'language'], 'int_communication_rh_article_translations_article_id_language_u');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_communication_rh_article_translations');
    }
};
