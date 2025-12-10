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
        Schema::create('int_outils_rh_link_user', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('link_id');
            $table->uuid('user_id');
            $table->boolean('pinned')->default(true);
            $table->timestamps();

            $table->unique(['link_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_outils_rh_link_user');
    }
};
