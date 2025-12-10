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
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('team_id')->nullable()->index('roles_team_foreign_key_index');
            $table->string('name');
            $table->string('guard_name');
            $table->boolean('is_protected')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'name', 'guard_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
