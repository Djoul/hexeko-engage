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
        Schema::create('mobile_version_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('financer_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->enum('platform', ['ios', 'android'])->nullable();
            $table->string('version')->nullable();
            $table->string('minimum_required_version')->nullable();
            $table->boolean('should_update')->default(false);
            $table->string('update_type', 50)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['platform', 'version']);
            $table->index('created_at');
        });

        Schema::table('mobile_version_logs', function (Blueprint $table): void {
            $table->foreign('financer_id')->references('id')->on('financers');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_version_logs');
    }
};
