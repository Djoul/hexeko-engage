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
            $table->uuid('product_id')->nullable()->after('merchant_id');
            $table->decimal('total_amount', 10, 2)->nullable()->after('amount');
            $table->string('payment_status')->default('pending')->after('status');
            $table->string('payment_method')->nullable()->after('payment_status');
            $table->timestamp('payment_completed_at')->nullable()->after('payment_method');

            $table->index('product_id');
            $table->index('payment_status');
            $table->index('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_vouchers_amilon_orders', function (Blueprint $table): void {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['payment_method']);

            $table->dropColumn([
                'product_id',
                'total_amount',
                'payment_status',
                'payment_method',
                'payment_completed_at',
            ]);
        });
    }
};
