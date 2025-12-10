<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds performance indexes to the article_interactions table
     * to optimize user interaction queries and reduce N+1 problems.
     */
    public function up(): void
    {
        Schema::table('int_communication_rh_article_interactions', function (Blueprint $table): void {
            // Index for filtering by reaction type
            $table->index('reaction', 'int_communication_rh_article_interactions_reaction_idx');

            // Index for filtering by favorite status
            $table->index('is_favorite', 'int_communication_rh_article_interactions_is_favorite_idx');

            // Composite index for user favorites
            $table->index(['user_id', 'is_favorite'], 'int_communication_rh_article_interactions_user_favorite_idx');

            // Composite index for article interactions with reaction
            $table->index(['article_id', 'reaction'], 'int_communication_rh_article_interactions_article_reaction_idx');

            // Index for article_translation_id (often used in joins)
            $table->index('article_translation_id', 'int_communication_rh_article_interactions_translation_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_communication_rh_article_interactions', function (Blueprint $table): void {
            $table->dropIndex('int_communication_rh_article_interactions_reaction_idx');
            $table->dropIndex('int_communication_rh_article_interactions_is_favorite_idx');
            $table->dropIndex('int_communication_rh_article_interactions_user_favorite_idx');
            $table->dropIndex('int_communication_rh_article_interactions_article_reaction_idx');
            $table->dropIndex('int_communication_rh_article_interactions_translation_idx');
        });
    }
};
