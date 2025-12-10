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
        Schema::table('financers', function (Blueprint $table): void {
            $table->enum('status', ['active', 'pending', 'archived'])
                ->default('pending')
                ->after('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financers', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};
