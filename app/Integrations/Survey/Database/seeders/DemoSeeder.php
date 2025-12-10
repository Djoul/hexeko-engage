<?php

namespace App\Integrations\Survey\Database\seeders;

use App\Integrations\Survey\Actions\CreateDefaultSurveyDataAction;
use App\Models\Financer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function __construct(
        protected CreateDefaultSurveyDataAction $createDefaultSurveyDataAction
    ) {}

    public function run(): void
    {
        $financers = Financer::all();
        $financers->each(fn (Financer $financer) => $this->createDefaultSurveyDataAction->execute($financer));
    }
}
