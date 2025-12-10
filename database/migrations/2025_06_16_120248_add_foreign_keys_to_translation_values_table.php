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
        Schema::table('translation_values', function (Blueprint $table): void {
            $table->foreign(['translation_key_id'])->references(['id'])->on('translation_keys')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('translation_values', function (Blueprint $table): void {
            $table->dropForeign('translation_values_translation_key_id_foreign');
        });
    }
};
