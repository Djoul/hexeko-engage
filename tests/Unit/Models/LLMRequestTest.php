<?php

namespace Tests\Unit\Models;

use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Models\Article;
use App\Models\Financer;
use App\Models\LLMRequest;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ai')]
#[Group('llm')]
#[Group('LLMRequest')]
class LLMRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_can_create_llm_request(): void
    {
        $financer = Financer::factory()->create();
        $article = resolve(ArticleFactory::class)->create(['financer_id' => $financer->id]);

        LLMRequest::create([
            'prompt' => 'Generate an article about employee engagement.',
            'response' => 'Here is the generated content...',
            'prompt_system' => 'You are an AI assistant that helps with article generation.',
            'tokens_used' => 180,
            'engine_used' => 'openAI',
            'financer_id' => $financer->id,
            'requestable_id' => $article->id,
            'requestable_type' => Article::class,
        ]);

        $this->assertDatabaseHas('llm_requests', [
            'prompt' => 'Generate an article about employee engagement.',
            'prompt_system' => 'You are an AI assistant that helps with article generation.',
            'engine_used' => 'openAI',
            'tokens_used' => 180,
            'financer_id' => $financer->id,
            'requestable_id' => $article->id,
            'requestable_type' => Article::class,
        ]);
    }
}
