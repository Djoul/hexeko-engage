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
        Schema::create('manager_user', function (Blueprint $table): void {
            $table->uuid('manager_id');
            $table->uuid('user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['manager_id', 'user_id']);
        });

        Schema::table('manager_user', function (Blueprint $table): void {
            $table->foreign('manager_id')->references('id')->on('users');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manager_user');
    }
};
