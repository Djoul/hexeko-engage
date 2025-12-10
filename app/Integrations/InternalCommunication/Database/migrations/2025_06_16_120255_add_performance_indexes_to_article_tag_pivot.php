<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds performance indexes to the article_tag pivot table
     * to optimize tag-article relationship queries.
     */
    public function up(): void
    {
        Schema::table('int_communication_rh_article_tag', function (Blueprint $table): void {
            // Index for reverse lookups (tag -> articles)
            $table->index('tag_id', 'int_communication_rh_article_tag_tag_id_idx');

            // Index for created_at (if needed for tracking when tags were added)
            $table->index('created_at', 'int_communication_rh_article_tag_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_communication_rh_article_tag', function (Blueprint $table): void {
            $table->dropIndex('int_communication_rh_article_tag_tag_id_idx');
            $table->dropIndex('int_communication_rh_article_tag_created_at_idx');
        });
    }
};
