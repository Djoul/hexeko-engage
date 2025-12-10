<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds performance indexes to the article_versions table
     * to optimize version queries and reduce N+1 problems.
     */
    public function up(): void
    {
        Schema::table('int_communication_rh_article_versions', function (Blueprint $table): void {
            // Index for version_number (used for ordering versions)
            $table->index('version_number', 'int_communication_rh_article_versions_version_number_idx');

            // Index for language (used in filtering)
            $table->index('language', 'int_communication_rh_article_versions_language_idx');

            // Composite index for article versions ordering
            $table->index(['article_id', 'version_number'], 'int_communication_rh_article_versions_article_version_idx');

            // Index for article_translation_id (FK queries)
            $table->index('article_translation_id', 'int_communication_rh_article_versions_translation_idx');

            // Index for author_id (when filtering by author)
            $table->index('author_id', 'int_communication_rh_article_versions_author_idx');

            // Index for llm_request_id (when tracking LLM requests)
            $table->index('llm_request_id', 'int_communication_rh_article_versions_llm_request_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_communication_rh_article_versions', function (Blueprint $table): void {
            $table->dropIndex('int_communication_rh_article_versions_version_number_idx');
            $table->dropIndex('int_communication_rh_article_versions_language_idx');
            $table->dropIndex('int_communication_rh_article_versions_article_version_idx');
            $table->dropIndex('int_communication_rh_article_versions_translation_idx');
            $table->dropIndex('int_communication_rh_article_versions_author_idx');
            $table->dropIndex('int_communication_rh_article_versions_llm_request_idx');
        });
    }
};
