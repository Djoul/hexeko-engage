<?php

namespace Database\Seeders;

use App\Enums\Languages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FinancerUserLanguageSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('financer_user') || ! Schema::hasColumn('financer_user', 'language')) {
            return;
        }

        DB::statement('
            UPDATE financer_user
            SET language = COALESCE(u.locale, ?)
            FROM users u
            WHERE financer_user.user_id = u.id
            AND financer_user.language IS NULL
        ', [Languages::ENGLISH]);

        $updatedRecords = DB::table('financer_user')
            ->whereNotNull('language')
            ->count();

        Log::info('Seeded financer_user language values from user locale', [
            'updated_records' => $updatedRecords,
        ]);
    }
}
