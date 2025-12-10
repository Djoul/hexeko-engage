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
        Schema::create('int_survey_themes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->jsonb('name');
            $table->jsonb('description');
            $table->uuid('financer_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->integer('position')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['financer_id', 'is_default']);
            $table->index(['created_by', 'updated_by']);
            $table->index('deleted_at');
        });

        Schema::table('int_survey_themes', function (Blueprint $table): void {
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
        Schema::dropIfExists('int_survey_themes');
    }
};
