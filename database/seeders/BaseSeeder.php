<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class BaseSeeder extends Seeder
{
    public function __construct(protected Team $globalTeam) {}
}
