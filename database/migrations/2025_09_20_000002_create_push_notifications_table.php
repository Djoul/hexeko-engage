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
        Schema::create('push_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('notification_id')->unique();
            $table->string('external_id')->nullable(); // OneSignal notification ID
            $table->enum('delivery_type', ['targeted', 'broadcast'])->default('targeted');
            $table->integer('device_count')->default(0);
            $table->enum('type', ['transaction', 'marketing', 'system', 'reminder', 'alert']);
            $table->string('title');
            $table->text('body');
            $table->string('url')->nullable();
            $table->string('image')->nullable();
            $table->string('icon')->nullable();
            $table->jsonb('data')->default('{}');
            $table->jsonb('buttons')->default('[]');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->integer('ttl')->default(86400);
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed', 'cancelled'])
                ->default('draft');
            $table->integer('recipient_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignUuid('author_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('type');
            $table->index('author_id');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
    }
};
