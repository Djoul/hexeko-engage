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
        Schema::create('financer_user', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('financer_id');
            $table->uuid('user_id');
            $table->boolean('active')->default(false);
            $table->string('sirh_id')->nullable();
            $table->timestamps();
            $table->string('external_id')->nullable();
            $table->timestamp('from')->useCurrent();
            $table->timestamp('to')->nullable();

            $table->index(['financer_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financer_user');
    }
};
