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
        Schema::create('push_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('push_notification_id')->constrained('push_notifications');
            $table->foreignUuid('push_subscription_id')->nullable()->constrained('push_subscriptions');
            $table->enum('event_type', ['sent', 'delivered', 'opened', 'clicked', 'dismissed', 'failed']);
            $table->string('event_id')->nullable();
            $table->jsonb('event_data')->default('{}');
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['push_notification_id', 'event_type']);
            $table->index(['push_subscription_id', 'event_type']);
            $table->index('event_type');
            $table->index('occurred_at');
            $table->unique(['push_notification_id', 'push_subscription_id', 'event_type', 'event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_events');
    }
};
