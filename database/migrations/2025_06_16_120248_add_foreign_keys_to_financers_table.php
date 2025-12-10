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
        Schema::table('financers', function (Blueprint $table): void {
            $table->foreign(['division_id'])->references(['id'])->on('divisions')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['representative_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financers', function (Blueprint $table): void {
            $table->dropForeign('financers_division_id_foreign');
            $table->dropForeign('financers_representative_id_foreign');
        });
    }
};
