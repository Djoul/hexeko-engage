<?php

declare(strict_types=1);

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
        Schema::create('translation_migrations', function (Blueprint $table): void {
            $table->id();
            $table->string('filename')->unique();
            $table->string('interface_origin');
            $table->string('version')->index();
            $table->string('checksum', 64);
            $table->json('metadata');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'rolled_back']);
            $table->integer('batch_number')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['interface_origin', 'version']);
            $table->index(['status', 'batch_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('translation_migrations');
    }
};
