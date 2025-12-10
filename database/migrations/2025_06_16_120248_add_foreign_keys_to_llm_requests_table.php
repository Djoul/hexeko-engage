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
        Schema::table('llm_requests', function (Blueprint $table): void {
            $table->foreign(['financer_id'])->references(['id'])->on('financers')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('llm_requests', function (Blueprint $table): void {
            $table->dropForeign('llm_requests_financer_id_foreign');
        });
    }
};
