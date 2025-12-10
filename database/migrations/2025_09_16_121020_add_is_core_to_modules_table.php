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
        Schema::table('modules', function (Blueprint $table): void {
            $table->boolean('is_core')->default(false)->after('active');
            $table->index('is_core');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table): void {
            $table->dropIndex(['is_core']);
            $table->dropColumn('is_core');
        });
    }
};
