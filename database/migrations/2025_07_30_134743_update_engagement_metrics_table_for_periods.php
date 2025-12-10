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
        Schema::table('engagement_metrics', function (Blueprint $table): void {
            // Rename existing columns
            $table->renameColumn('date', 'date_from');
            $table->renameColumn('module', 'financer_id');

            // Add new columns
            $table->date('date_to')->after('date_from');
            $table->string('period')->after('financer_id');

            // Update indexes
            $table->dropUnique(['date', 'metric', 'module']);
            $table->unique(['date_from', 'date_to', 'metric', 'financer_id', 'period'], 'engagement_metrics_unique_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('engagement_metrics', function (Blueprint $table): void {
            // Drop new index
            $table->dropUnique('engagement_metrics_unique_period');

            // Remove new columns
            $table->dropColumn(['date_to', 'period']);

            // Rename columns back
            $table->renameColumn('date_from', 'date');
            $table->renameColumn('financer_id', 'module');

            // Restore original index
            $table->unique(['date', 'metric', 'module']);
        });
    }
};
