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
            $table->unsignedInteger('users_count')->default(0);
            $table->unsignedInteger('submissions_count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_survey_surveys', function (Blueprint $table): void {
            $table->dropColumn('users_count');
            $table->dropColumn('submissions_count');
        });
    }
};
