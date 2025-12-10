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
        Schema::create('stored_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('aggregate_uuid')->nullable()->index();
            $table->bigInteger('aggregate_version')->nullable();
            $table->smallInteger('event_version')->default(1);
            $table->string('event_class')->index();
            $table->jsonb('event_properties');
            $table->jsonb('meta_data');
            $table->timestamp('created_at');

            $table->unique(['aggregate_uuid', 'aggregate_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stored_events');
    }
};
