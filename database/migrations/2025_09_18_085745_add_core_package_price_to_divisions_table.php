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
        Schema::table('divisions', function (Blueprint $table): void {
            $table->integer('core_package_price')->nullable()
                ->after('is_active')
                ->comment('Price in euro cents for core modules package');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table): void {
            $table->dropColumn('core_package_price');
        });
    }
};
