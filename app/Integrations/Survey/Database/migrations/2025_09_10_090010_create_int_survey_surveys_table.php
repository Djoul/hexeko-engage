<?php

use App\Integrations\Survey\Enums\SurveyStatusEnum;
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
        Schema::create('int_survey_surveys', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('financer_id');
            $table->jsonb('title')->nullable();
            $table->jsonb('description')->nullable();
            $table->jsonb('welcome_message')->nullable();
            $table->jsonb('thank_you_message')->nullable();
            $table->enum('status', SurveyStatusEnum::getValues());
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->jsonb('settings')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['financer_id', 'status']);
            $table->index(['starts_at', 'ends_at']);
            $table->index(['created_by', 'updated_by']);
            $table->index('deleted_at');
            $table->index('archived_at');
        });

        Schema::table('int_survey_surveys', function (Blueprint $table): void {
            $table->foreign('financer_id')->references('id')->on('financers');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_survey_surveys');
    }
};
