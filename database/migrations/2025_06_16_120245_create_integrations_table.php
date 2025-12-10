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
        Schema::create('integrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('module_id');
            $table->string('name');
            $table->string('type');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->json('settings')->nullable();
            $table->string('api_endpoint')->nullable();
            $table->string('front_endpoint')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
