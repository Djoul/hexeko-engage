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
            // Add column to reference the original cancelled order that was recovered
            $table->uuid('order_recovered_id')->nullable()->after('external_order_id');

            // Add foreign key constraint
            $table->foreign('order_recovered_id')
                ->references('id')
                ->on('int_vouchers_amilon_orders')
                ->nullOnDelete();

            // Add index for performance
            $table->index('order_recovered_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_vouchers_amilon_orders', function (Blueprint $table): void {
            // Drop foreign key and index first
            $table->dropForeign(['order_recovered_id']);
            $table->dropIndex(['order_recovered_id']);

            // Drop the column
            $table->dropColumn('order_recovered_id');
        });
    }
};
