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
        Schema::create('credit_balances', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('owner_type');
            $table->uuid('owner_id');
            $table->string('type');
            $table->integer('balance')->default(0);
            $table->timestamps();
            $table->json('context')->nullable();

            $table->unique(['owner_type', 'owner_id', 'type'], 'unique_credit_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_balances');
    }
};
