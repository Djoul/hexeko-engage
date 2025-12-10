<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('divisions', 'contract_start_date')) {
            return;
        }

        Schema::table('divisions', function (Blueprint $table): void {
            $table->date('contract_start_date')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('divisions', 'contract_start_date')) {
            return;
        }

        Schema::table('divisions', function (Blueprint $table): void {
            $table->dropColumn('contract_start_date');
        });
    }
};
