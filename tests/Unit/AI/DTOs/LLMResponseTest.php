<?php

namespace Tests\Unit\AI\DTOs;

use App\AI\DTOs\LLMResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('ai')]
#[Group('llm')]
class LLMResponseTest extends TestCase
{
    public function test_can_instantiate_llm_response(): void
    {
        $chatResponse = 'This is a generated article.';
        $tokensUsed = 150;

        $response = new LLMResponse(
            $chatResponse,
            $tokensUsed
        );

        $this->assertEquals($chatResponse, $response->chatResponse);
        $this->assertEquals($tokensUsed, $response->tokensUsed);
        $this->assertNull($response->canvasContent);
    }

    public function test_can_instantiate_llm_response_with_canvas_content(): void
    {
        $chatResponse = 'This is a chat response.';
        $canvasContent = 'This is canvas content.';
        $tokensUsed = 200;

        $response = new LLMResponse(
            $chatResponse,
            $tokensUsed,
            $canvasContent
        );

        $this->assertEquals($chatResponse, $response->chatResponse);
        $this->assertEquals($canvasContent, $response->canvasContent);
        $this->assertEquals($tokensUsed, $response->tokensUsed);
    }
}
