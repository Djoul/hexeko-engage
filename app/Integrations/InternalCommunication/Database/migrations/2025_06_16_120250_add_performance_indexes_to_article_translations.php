<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds critical performance indexes to the article_translations table
     * to address N+1 query problems and optimize common filtering patterns.
     */
    public function up(): void
    {
        Schema::table('int_communication_rh_article_translations', function (Blueprint $table): void {
            // Index for filtering by status (very common in queries)
            $table->index('status', 'int_communication_rh_article_translations_status_idx');

            // Index for filtering by language (very common)
            $table->index('language', 'int_communication_rh_article_translations_language_idx');

            // Index for published_at (used in date range filtering)
            $table->index('published_at', 'int_communication_rh_article_translations_published_at_idx');

            // Composite index for status + language (common filter combination)
            $table->index(['status', 'language'], 'int_communication_rh_article_translations_status_language_idx');

            // Composite index for published articles filtering
            $table->index(['status', 'published_at'], 'int_communication_rh_article_translations_status_published_idx');

            // Composite index for language + published_at (for user-specific queries)
            $table->index(['language', 'published_at'], 'int_communication_rh_article_translations_lang_published_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_communication_rh_article_translations', function (Blueprint $table): void {
            $table->dropIndex('int_communication_rh_article_translations_status_idx');
            $table->dropIndex('int_communication_rh_article_translations_language_idx');
            $table->dropIndex('int_communication_rh_article_translations_published_at_idx');
            $table->dropIndex('int_communication_rh_article_translations_status_language_idx');
            $table->dropIndex('int_communication_rh_article_translations_status_published_idx');
            $table->dropIndex('int_communication_rh_article_translations_lang_published_idx');
        });
    }
};
