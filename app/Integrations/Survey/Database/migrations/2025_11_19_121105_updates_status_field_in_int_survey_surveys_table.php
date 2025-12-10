<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the existing check constraint if it exists
        DB::statement('ALTER TABLE int_survey_surveys DROP CONSTRAINT IF EXISTS int_survey_surveys_status_check');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the check constraint
        DB::statement('ALTER TABLE int_survey_surveys DROP CONSTRAINT IF EXISTS int_survey_surveys_status_check');
    }
};
