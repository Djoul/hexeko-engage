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
        Schema::table('sessions', function (Blueprint $table): void {
            // Drop the old user_id column
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table): void {
            // Add user_id as UUID string
            $table->string('user_id', 36)->nullable()->index()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            // Drop the UUID user_id column
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table): void {
            // Restore original bigInteger user_id
            $table->bigInteger('user_id')->nullable()->index()->after('id');
        });
    }
};
