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
            $table->foreign(['article_id'])->references(['id'])->on('int_communication_rh_articles')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['article_translation_id'], 'int_communication_rh_article_versions_article_translation_id_fo')->references(['id'])->on('int_communication_rh_article_translations')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['author_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_communication_rh_article_versions', function (Blueprint $table): void {
            $table->dropForeign('int_communication_rh_article_versions_article_id_foreign');
            $table->dropForeign('int_communication_rh_article_versions_article_translation_id_fo');
            $table->dropForeign('int_communication_rh_article_versions_author_id_foreign');
        });
    }
};
