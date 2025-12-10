<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds performance indexes to the tags table
     * to optimize tag queries and reduce N+1 problems.
     */
    public function up(): void
    {
        Schema::table('int_communication_rh_tags', function (Blueprint $table): void {
            // Index for deleted_at (soft deletes)
            $table->index('deleted_at', 'int_communication_rh_tags_deleted_at_idx');

            // Index for created_at (used for sorting)
            $table->index('created_at', 'int_communication_rh_tags_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_communication_rh_tags', function (Blueprint $table): void {
            $table->dropIndex('int_communication_rh_tags_deleted_at_idx');
            $table->dropIndex('int_communication_rh_tags_created_at_idx');
        });
    }
};
