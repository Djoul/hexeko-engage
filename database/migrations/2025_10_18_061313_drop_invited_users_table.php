<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops the invited_users table after all data has been migrated to users table.
     *
     * IMPORTANT: This migration should only run AFTER the convert_invited_users_to_pending_users_table
     * migration has successfully completed and all data has been verified.
     */
    public function up(): void
    {
        Schema::dropIfExists('invited_users');

        Log::info('Table invited_users has been dropped successfully.');
    }

    /**
     * Reverse the migrations.
     *
     * Recreates the invited_users table structure.
     * WARNING: This will NOT restore the data - data must be manually restored from backup.
     */
    public function down(): void
    {
        Schema::create('invited_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->uuid('financer_id');
            $table->string('sirh_id')->nullable();
            $table->json('extra_data')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('financer_id')
                ->references('id')
                ->on('financers');
        });

        Log::warning('Table invited_users has been recreated but contains NO DATA.');
        Log::warning('You must restore data from backup if needed.');
    }
};
