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
        Schema::create('int_communication_rh_tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('financer_id');
            $table->jsonb('label');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['financer_id', 'label'], 'int_communication_rh_tags_financer_label_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('int_communication_rh_tags');
    }
};
