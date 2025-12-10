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
        Schema::create('contract_type_user', function (Blueprint $table): void {
            $table->uuid('contract_type_id');
            $table->uuid('user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contract_type_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_type_user');
    }
};
