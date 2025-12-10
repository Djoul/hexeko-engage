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
        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users');
            $table->string('subscription_id')->unique();
            $table->enum('device_type', ['ios', 'android', 'web', 'desktop']);
            $table->string('device_model')->nullable();
            $table->string('device_os')->nullable();
            $table->string('app_version')->nullable();
            $table->string('timezone')->nullable();
            $table->string('language')->nullable();
            $table->jsonb('notification_preferences')->default('{}');
            $table->boolean('push_enabled')->default(true);
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('vibration_enabled')->default(true);
            $table->jsonb('tags')->default('{}');
            $table->jsonb('metadata')->default('{}');
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'push_enabled']);
            $table->index('device_type');
            $table->index('last_active_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
