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
        Schema::create('int_survey_question_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('question_id');
            $table->uuid('original_question_option_id')->nullable();
            $table->jsonb('text');
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['question_id']);
            $table->index('deleted_at');
        });

        Schema::table('int_survey_question_options', function (Blueprint $table): void {
            $table->foreign('question_id')->references('id')->on('int_survey_questions');
            $table->foreign('original_question_option_id')->references('id')->on('int_survey_question_options');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_survey_question_options');
    }
};
