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
        Schema::create('contract_types', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('financer_id');
            $table->string('apideck_id')->nullable();
            $table->jsonb('name');
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['financer_id']);
            $table->index(['apideck_id']);
            $table->index(['deleted_at']);
            $table->index(['created_at']);
            $table->index(['financer_id', 'deleted_at']); // Composite index for common queries
        });

        Schema::table('contract_types', function (Blueprint $table): void {
            $table->foreign('financer_id')->references('id')->on('financers');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_types');
    }
};
