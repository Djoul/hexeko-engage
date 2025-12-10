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
            // Add new column for media id
            $table->unsignedBigInteger('illustration_id')->nullable()->after('llm_request_id');

            // Drop old illustration column
            $table->dropColumn('illustration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_communication_rh_article_versions', function (Blueprint $table): void {
            // Re-add the original illustration column
            $table->text('illustration')->nullable()->after('llm_request_id');

            // Drop the illustration_id column
            $table->dropColumn('illustration_id');
        });
    }
};
