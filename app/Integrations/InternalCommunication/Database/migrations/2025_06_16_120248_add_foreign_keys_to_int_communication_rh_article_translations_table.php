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
        Schema::table('int_communication_rh_article_translations', function (Blueprint $table): void {
            $table->foreign(['article_id'])->references(['id'])->on('int_communication_rh_articles')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_communication_rh_article_translations', function (Blueprint $table): void {
            $table->dropForeign('int_communication_rh_article_translations_article_id_foreign');
        });
    }
};
