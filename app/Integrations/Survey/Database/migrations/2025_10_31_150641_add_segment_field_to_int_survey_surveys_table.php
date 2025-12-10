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
        Schema::table('int_survey_surveys', function (Blueprint $table): void {
            $table->uuid('segment_id')->nullable()->after('financer_id');
            $table->index(['segment_id']);
            $table->foreign('segment_id')->references('id')->on('segments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_survey_surveys', function (Blueprint $table): void {
            $table->dropColumn('segment_id');
        });
    }
};
