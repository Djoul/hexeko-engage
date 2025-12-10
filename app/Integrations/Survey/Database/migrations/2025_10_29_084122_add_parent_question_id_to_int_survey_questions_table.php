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
        Schema::table('int_survey_questions', function (Blueprint $table): void {
            $table->uuid('parent_question_id')->nullable()->after('original_question_id');
        });

        Schema::table('int_survey_questions', function (Blueprint $table): void {
            $table->foreign('parent_question_id')->references('id')->on('int_survey_questions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_survey_questions', function (Blueprint $table): void {
            $table->dropColumn('parent_question_id');
        });
    }
};
