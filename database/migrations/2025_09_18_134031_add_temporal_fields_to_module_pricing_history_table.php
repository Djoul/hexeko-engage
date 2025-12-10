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
        Schema::table('module_pricing_history', function (Blueprint $table): void {
            // Add temporal validity fields
            $table->date('valid_from')->after('reason')->nullable();
            $table->date('valid_until')->after('valid_from')->nullable();

            // Add indexes for temporal queries
            $table->index(['entity_id', 'entity_type', 'valid_from']);
            $table->index(['module_id', 'valid_from', 'valid_until']);
        });
    }
};
