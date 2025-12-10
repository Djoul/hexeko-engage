<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_topic_subscriptions')) {
            return;
        }

        Schema::create('notification_topic_subscriptions', function (Blueprint $table): void {
            $table->uuid('notification_topic_id');
            $table->uuid('push_subscription_id');
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_topic_id', 'push_subscription_id'], 'topic_subscription_unique');
            $table->index('notification_topic_id');
            $table->index('push_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_topic_subscriptions');

    }
};
