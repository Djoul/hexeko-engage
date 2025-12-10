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
        Schema::create('int_survey_answers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('submission_id');
            $table->uuid('question_id');
            $table->jsonb('answer');
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'submission_id', 'question_id']);
        });

        Schema::table('int_survey_answers', function (Blueprint $table): void {
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('submission_id')->references('id')->on('int_survey_submissions');
            $table->foreign('question_id')->references('id')->on('int_survey_questions');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_survey_answers');
    }
};
