<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class TeamsTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     */
    public function run(): void
    {

        DB::table('teams')->delete();

        DB::table('teams')->insert([
            0 => [
                'id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
                'name' => 'Global Team',
                'slug' => 'global-team',
                'type' => 'glo',
                'created_at' => '2025-09-03 10:21:55',
                'updated_at' => '2025-09-03 10:21:55',
                'deleted_at' => null,
            ],
        ]);

    }
}
