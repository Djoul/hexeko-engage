<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove the unique constraint from users.email column
     * to allow the same email across multiple financers for active users.
     * Uniqueness is now enforced at application level: email + financer_id + active.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['email']);
            $table->index('email'); // Keep index for performance
        });
    }
};
