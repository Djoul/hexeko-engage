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
        Schema::table('financer_user', function (Blueprint $table): void {
            $table->foreign(['financer_id'])->references(['id'])->on('financers')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financer_user', function (Blueprint $table): void {
            $table->dropForeign('financer_user_financer_id_foreign');
            $table->dropForeign('financer_user_user_id_foreign');
        });
    }
};
