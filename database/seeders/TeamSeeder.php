<?php

namespace Database\Seeders;

use App\Enums\IDP\TeamTypes;
use App\Models\Team;

class TeamSeeder extends BaseSeeder
{
    public function run(): void
    {
        $team = Team::create([
            'id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
            'name' => 'Global Team',
            'slug' => 'global-team',
            'type' => TeamTypes::GLOBAL,
        ]);

        setPermissionsTeamId($team->id);
        $this->globalTeam = $team;
    }
}
