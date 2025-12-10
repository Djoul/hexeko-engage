<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('llm_requests', function (Blueprint $table): void {
            $table->json('messages')->nullable()->after('prompt_system');
        });
    }
};
