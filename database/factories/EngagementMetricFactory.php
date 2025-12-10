<?php

namespace Database\Factories;

use App\Models\EngagementMetric;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EngagementMetricFactory extends Factory
{
    protected $model = EngagementMetric::class;

    public function definition(): array
    {
        $metric = $this->faker->randomElement([
            'average_session_time',
            'bounce_rate',
            'articles_per_employee',
            'tool_clicks',
            'survey_response_rate',
        ]);

        return [
            'id' => Str::uuid()->toString(),
            'date' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'metric' => $metric,
            'module' => $this->faker->randomElement([
                'general', 'communication-rh', 'tools', 'wellbeing', 'ecards',
            ]),
            'data' => [
                'value' => $this->faker->randomFloat(2, 0, 100),
                'count' => $this->faker->numberBetween(0, 1000),
            ],
        ];
    }
}
