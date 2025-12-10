<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds performance indexes to the articles table
     * to optimize main article queries and reduce N+1 problems.
     */
    public function up(): void
    {
        Schema::table('int_communication_rh_articles', function (Blueprint $table): void {
            // Composite index for financer + author queries
            $table->index(['financer_id', 'author_id'], 'int_communication_rh_articles_financer_author_idx');

            // Index for created_at (used in sorting and date filtering)
            $table->index('created_at', 'int_communication_rh_articles_created_at_idx');

            // Index for updated_at (used in sorting)
            $table->index('updated_at', 'int_communication_rh_articles_updated_at_idx');

            // Index for deleted_at (soft deletes)
            $table->index('deleted_at', 'int_communication_rh_articles_deleted_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_communication_rh_articles', function (Blueprint $table): void {
            $table->dropIndex('int_communication_rh_articles_financer_author_idx');
            $table->dropIndex('int_communication_rh_articles_created_at_idx');
            $table->dropIndex('int_communication_rh_articles_updated_at_idx');
            $table->dropIndex('int_communication_rh_articles_deleted_at_idx');
        });
    }
};
