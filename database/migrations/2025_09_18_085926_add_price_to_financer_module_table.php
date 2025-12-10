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
        Schema::table('financer_module', function (Blueprint $table): void {
            $table->integer('price_per_beneficiary')->nullable()
                ->after('promoted')
                ->comment('Price in euro cents per beneficiary for this module (overrides division price)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financer_module', function (Blueprint $table): void {
            $table->dropColumn('price_per_beneficiary');
        });
    }
};
