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
        Schema::create('int_communication_rh_article_interactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('article_id');
            $table->string('reaction')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();
            $table->uuid('article_translation_id')->nullable();

            $table->unique(['user_id', 'article_id'], 'int_communication_rh_article_interactions_user_id_article_id_un');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_communication_rh_article_interactions');
    }
};
