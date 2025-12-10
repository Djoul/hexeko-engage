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
        Schema::create('int_survey_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('financer_id');
            $table->uuid('user_id');
            $table->uuid('survey_id');
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['survey_id', 'user_id']);
            $table->index(['created_by', 'updated_by']);
        });

        Schema::table('int_survey_submissions', function (Blueprint $table): void {
            $table->foreign('financer_id')->references('id')->on('financers');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('survey_id')->references('id')->on('int_survey_surveys');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_survey_submissions');
    }
};
