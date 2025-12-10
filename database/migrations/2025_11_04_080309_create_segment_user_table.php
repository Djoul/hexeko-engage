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
        Schema::create('segment_user', function (Blueprint $table): void {
            $table->uuid('segment_id')->nullable();
            $table->uuid('user_id')->nullable();

            $table->index(['segment_id', 'user_id']);
            $table->timestamps();
        });

        Schema::table('segment_user', function (Blueprint $table): void {
            $table->foreign('segment_id')->references('id')->on('segments');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('segment_user');
    }
};
