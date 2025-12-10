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
        Schema::table('int_outils_rh_link_user', function (Blueprint $table): void {
            $table->foreign(['link_id'])->references(['id'])->on('int_outils_rh_links')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['user_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('int_outils_rh_link_user', function (Blueprint $table): void {
            $table->dropForeign('int_outils_rh_link_user_link_id_foreign');
            $table->dropForeign('int_outils_rh_link_user_user_id_foreign');
        });
    }
};
