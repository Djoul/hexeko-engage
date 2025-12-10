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
        Schema::table('financer_user', function (Blueprint $table): void {
            $table->uuid('work_mode_id')->nullable()->after('user_id');
            $table->uuid('job_title_id')->nullable()->after('work_mode_id');
            $table->uuid('job_level_id')->nullable()->after('job_title_id');
            $table->timestamp('started_at')->nullable()->after('to');

            $table->index(['work_mode_id']);
            $table->index(['job_title_id']);
            $table->index(['job_level_id']);
        });

        Schema::table('financer_user', function (Blueprint $table): void {
            $table->foreign('work_mode_id')->references('id')->on('work_modes');
            $table->foreign('job_title_id')->references('id')->on('job_titles');
            $table->foreign('job_level_id')->references('id')->on('job_levels');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financer_user', function (Blueprint $table): void {
            $table->dropColumn('work_mode_id');
            $table->dropColumn('job_title_id');
            $table->dropColumn('job_level_id');
            $table->dropColumn('started_at');
        });

        Schema::table('financer_user', function (Blueprint $table): void {
            $table->dropIndex(['work_mode_id']);
            $table->dropIndex(['job_title_id']);
            $table->dropIndex(['job_level_id']);

            $table->dropForeign(['work_mode_id']);
            $table->dropForeign(['job_title_id']);
            $table->dropForeign(['job_level_id']);
        });
    }
};
