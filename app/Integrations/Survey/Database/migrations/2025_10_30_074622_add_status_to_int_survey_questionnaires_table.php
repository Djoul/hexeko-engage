<?php

use App\Integrations\Survey\Enums\QuestionnaireStatusEnum;
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
        Schema::table('int_survey_questionnaires', function (Blueprint $table): void {
            $table->enum('status', QuestionnaireStatusEnum::getValues())->default('draft')->after('type');

            $table->index(['financer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_survey_questionnaires', function (Blueprint $table): void {
            $table->dropColumn('status');
            $table->dropIndex(['financer_id', 'status']);
        });
    }
};
