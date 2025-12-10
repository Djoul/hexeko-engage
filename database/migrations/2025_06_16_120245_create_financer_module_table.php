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
        Schema::create('financer_module', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('financer_id');
            $table->uuid('module_id');
            $table->boolean('active');
            $table->timestamps();
            $table->boolean('promoted')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financer_module');
    }
};
