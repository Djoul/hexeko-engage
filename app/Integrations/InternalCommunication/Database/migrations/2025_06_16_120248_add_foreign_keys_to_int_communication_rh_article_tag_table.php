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
        Schema::table('int_communication_rh_article_tag', function (Blueprint $table): void {
            $table->foreign(['article_id'])->references(['id'])->on('int_communication_rh_articles')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['tag_id'])->references(['id'])->on('int_communication_rh_tags')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_communication_rh_article_tag', function (Blueprint $table): void {
            $table->dropForeign('int_communication_rh_article_tag_article_id_foreign');
            $table->dropForeign('int_communication_rh_article_tag_tag_id_foreign');
        });
    }
};
