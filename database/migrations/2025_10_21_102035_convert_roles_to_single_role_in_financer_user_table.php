<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Convert roles (JSON array) to role (string) in financer_user table
     */
    public function up(): void
    {
        // Step 1: Add new 'role' column as nullable first
        Schema::table('financer_user', function (Blueprint $table): void {
            $table->string('role', 50)->nullable()->after('active');
        });

        // Step 2: Migrate data from 'roles' (JSON) to 'role' (string)
        // Take the first role from the JSON array, or 'beneficiary' as default
        DB::table('financer_user')->get()->each(function ($record): void {
            $roles = json_decode($record->roles, true);
            $role = is_array($roles) && count($roles) > 0 ? $roles[0] : 'beneficiary';

            DB::table('financer_user')
                ->where('user_id', $record->user_id)
                ->where('financer_id', $record->financer_id)
                ->update(['role' => $role]);
        });

        // Step 3: Make 'role' NOT NULL now that all data is migrated
        Schema::table('financer_user', function (Blueprint $table): void {
            $table->string('role', 50)->default('beneficiary')->change();
        });

        // Step 4: Drop old 'roles' column
        Schema::table('financer_user', function (Blueprint $table): void {
            $table->dropColumn('roles');
        });
    }
};
