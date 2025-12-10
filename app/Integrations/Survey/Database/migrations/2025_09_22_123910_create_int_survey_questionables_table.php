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
        Schema::create('int_survey_questionables', function (Blueprint $table): void {
            $table->uuid('question_id');
            $table->uuidMorphs('questionable');
            $table->unsignedInteger('position')->default(0);
            $table->primary(['question_id', 'questionable_id', 'questionable_type']);
            $table->timestamps();
        });

        Schema::table('int_survey_questionables', function (Blueprint $table): void {
            $table->foreign('question_id')->references('id')->on('int_survey_questions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_survey_questionables');
    }
};
