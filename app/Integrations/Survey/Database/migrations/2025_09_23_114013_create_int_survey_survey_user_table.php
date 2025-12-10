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
        Schema::create('int_survey_survey_user', function (Blueprint $table): void {
            $table->uuid('survey_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->index(['survey_id', 'user_id']);
        });

        Schema::table('int_survey_survey_user', function (Blueprint $table): void {
            $table->foreign('survey_id')->references('id')->on('int_survey_surveys');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_survey_survey_user');
    }
};
