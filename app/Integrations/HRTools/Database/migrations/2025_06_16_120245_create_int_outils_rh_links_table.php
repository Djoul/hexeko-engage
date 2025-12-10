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
        Schema::create('int_outils_rh_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->json('name');
            $table->json('description')->nullable();
            $table->json('url');
            $table->string('logo_url')->nullable();
            $table->uuid('financer_id');
            $table->integer('position')->default(0);
            $table->string('api_endpoint')->nullable();
            $table->string('front_endpoint')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_outils_rh_links');
    }
};
