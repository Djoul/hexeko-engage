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
        Schema::table('manager_user', function (Blueprint $table): void {
            $table->uuid('financer_id')->nullable();

            $table->index(['financer_id']);
        });

        Schema::table('manager_user', function (Blueprint $table): void {
            $table->foreign('financer_id')->references('id')->on('financers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manager_user', function (Blueprint $table): void {
            $table->dropColumn('financer_id');
        });

        Schema::table('manager_user', function (Blueprint $table): void {
            $table->dropForeign(['financer_id']);
        });
    }
};
