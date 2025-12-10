<?php

use App\Integrations\Survey\Enums\QuestionTypeEnum;
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
        Schema::create('int_survey_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('financer_id')->nullable();
            $table->uuid('theme_id')->nullable();
            $table->uuid('original_question_id')->nullable();
            $table->jsonb('text');
            $table->jsonb('help_text')->nullable();
            $table->enum('type', QuestionTypeEnum::getValues());
            $table->jsonb('metadata')->nullable();
            $table->boolean('is_default')->default(false);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['financer_id', 'is_default']);
            $table->index(['created_by', 'updated_by']);
            $table->index('deleted_at');
            $table->index('archived_at');
        });

        Schema::table('int_survey_questions', function (Blueprint $table): void {
            $table->foreign('financer_id')->references('id')->on('financers');
            $table->foreign('theme_id')->references('id')->on('int_survey_themes');
            $table->foreign('original_question_id')->references('id')->on('int_survey_questions');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_survey_questions');
    }
};
