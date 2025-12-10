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
        Schema::table('invited_users', function (Blueprint $table): void {
            $table->string('sirh_id')->nullable()->after('financer_id');
            $table->jsonb('extra_data')->nullable()->after('sirh_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invited_users', function (Blueprint $table): void {
            $table->dropColumn(['sirh_id', 'extra_data']);
        });
    }
};
