<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if column already exists (idempotency)
        if (Schema::hasColumn('financer_user', 'language')) {
            return;
        }

        Schema::table('financer_user', function (Blueprint $table): void {
            $table->string('language', 5)
                ->nullable()
                ->after('roles')
                ->comment('User language preference for this financer context');
        });

        // Seed immédiat depuis user.locale
        $this->seedLanguageFromUserLocale();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if column exists before dropping (idempotency)
        if (Schema::hasColumn('financer_user', 'language')) {
            Schema::table('financer_user', function (Blueprint $table): void {
                $table->dropColumn('language');
            });
        }
    }

    /**
     * Seed the language column from existing user.locale values
     */
    private function seedLanguageFromUserLocale(): void
    {
        // Use the seeder via Artisan to maintain consistency between migration and fresh seed
        Artisan::call('db:seed', [
            '--class' => 'FinancerUserLanguageSeeder',
            '--force' => true, // Force in production
        ]);
    }
};
