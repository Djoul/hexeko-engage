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
        Schema::create('notification_topics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->foreignUuid('financer_id')->nullable()->constrained('financers');
            $table->boolean('is_active')->default(true);
            $table->integer('subscriber_count')->default(0);
            $table->timestamps();

            $table->index(['financer_id', 'is_active']);
            $table->index('is_active');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_topic_subscriptions');
    }
};
