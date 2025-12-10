<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First add the column as nullable
        Schema::table('financers', function (Blueprint $table): void {
            $table->string('company_number')->nullable()->after('registration_number');
        });

        // Update existing records with a default value based on their ID
        DB::table('financers')->whereNull('company_number')->update([
            'company_number' => DB::raw("CONCAT('TEMP-', id)"),
        ]);

        // Now make the column NOT NULL
        Schema::table('financers', function (Blueprint $table): void {
            $table->string('company_number')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financers', function (Blueprint $table): void {
            $table->dropColumn('company_number');
        });
    }
};
