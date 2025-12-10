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
        Schema::table('int_communication_rh_articles', function (Blueprint $table): void {
            $table->foreign(['author_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['financer_id'])->references(['id'])->on('financers')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_communication_rh_articles', function (Blueprint $table): void {
            $table->dropForeign('int_communication_rh_articles_author_id_foreign');
            $table->dropForeign('int_communication_rh_articles_financer_id_foreign');
        });
    }
};
