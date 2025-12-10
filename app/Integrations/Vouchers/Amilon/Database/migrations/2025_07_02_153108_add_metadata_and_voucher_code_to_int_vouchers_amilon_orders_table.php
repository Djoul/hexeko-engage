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
        Schema::table('int_vouchers_amilon_orders', function (Blueprint $table): void {
            $table->json('metadata')->nullable()->after('total_requested_codes');
            $table->string('voucher_code')->nullable()->after('metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_vouchers_amilon_orders', function (Blueprint $table): void {
            $table->dropColumn(['metadata', 'voucher_code']);
        });
    }
};
