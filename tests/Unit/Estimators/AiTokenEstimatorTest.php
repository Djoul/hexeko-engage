<?php

namespace Tests\Unit\Estimators;

use App\AI\LLMRouterService;
use App\Estimators\AiTokenEstimator;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('ai')]
#[Group('llm')]
class AiTokenEstimatorTest extends TestCase
{
    #[Test]
    public function it_estimates_token_usage_based_on_prompt_length(): void
    {
        $estimator = new AiTokenEstimator;

        $request = Request::create('/ai/generate', 'POST', [
            'prompt' => 'Hello world, this is GPT.',
        ]);

        $this->app->bind(LLMRouterService::class, function (): LLMRouterService {
            return new LLMRouterService([]); // tableau vide ou mock
        });

        $estimated = $estimator->estimate($request);

        $this->assertEquals(ceil(strlen('Hello world, this is GPT.') / 4), $estimated);
    }

    #[Test]
    public function it_returns_minimum_one_token_if_prompt_is_empty(): void
    {
        $estimator = new AiTokenEstimator;

        $request = Request::create('/ai/generate', 'POST', [
            'prompt' => '',
        ]);

        $estimated = $estimator->estimate($request);

        $this->assertEquals(1, $estimated);
    }

    #[Test]
    public function it_handles_missing_prompt_field_gracefully(): void
    {
        $estimator = new AiTokenEstimator;

        $request = Request::create('/ai/generate', 'POST', []);

        $estimated = $estimator->estimate($request);

        $this->assertEquals(1, $estimated);
    }
}
