<?php

namespace Database\Factories;

use App\Models\EngagementLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EngagementLogFactory extends Factory
{
    protected $model = EngagementLog::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement([
                'ArticleViewed',
                'LinkClicked',
                'SurveyAnswered',
                'EcardGenerated',
            ]),
            'target' => $this->faker->randomElement([
                'article:42',
                'link:Teams',
                'survey:13',
                'ecard:5',
            ]),
            'metadata' => [
                'ip' => $this->faker->ipv4,
                'device' => $this->faker->userAgent,
                'duration' => $this->faker->numberBetween(1, 300),
            ],
            'logged_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
