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
        Schema::table('int_communication_rh_article_versions', function (Blueprint $table): void {
            // Add foreign key constraint to media table
            $table->foreign('illustration_id')->references('id')->on('media')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_communication_rh_article_versions', function (Blueprint $table): void {
            // Drop foreign key constraint
            $table->dropForeign(['illustration_id']);
        });
    }
};
