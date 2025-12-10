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
        // Check if the column exists before trying to drop it
        if (Schema::hasColumn('int_vouchers_amilon_products', 'category')) {
            Schema::table('int_vouchers_amilon_products', function (Blueprint $table): void {
                $table->dropColumn('category');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add the category column if needed for rollback
        Schema::table('int_vouchers_amilon_products', function (Blueprint $table): void {
            $table->string('category')->nullable()->after('name');
        });
    }
};
