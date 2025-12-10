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
            $table->integer('core_package_price')->nullable()
                ->after('status')
                ->comment('Price in euro cents for core modules package (overrides division price)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financers', function (Blueprint $table): void {
            $table->dropColumn('core_package_price');
        });
    }
};
