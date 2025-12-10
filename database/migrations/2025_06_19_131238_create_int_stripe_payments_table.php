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
        Schema::create('int_stripe_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id'); // FK in separate migration
            $table->string('stripe_payment_id')->nullable()->unique();
            $table->string('stripe_checkout_id')->nullable()->unique();
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled']);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->integer('credit_amount');
            $table->string('credit_type');
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['stripe_payment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_stripe_payments');
    }
};
