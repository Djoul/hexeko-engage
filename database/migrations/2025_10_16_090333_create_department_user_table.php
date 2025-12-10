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
        Schema::create('department_user', function (Blueprint $table): void {
            $table->uuid('department_id');
            $table->uuid('user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'user_id']);
            $table->index(['user_id', 'department_id']);
        });

        Schema::table('department_user', function (Blueprint $table): void {
            $table->foreign('department_id')->references('id')->on('departments');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_user');
    }
};
