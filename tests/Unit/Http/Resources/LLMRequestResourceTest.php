<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\LLMRequest\LLMRequestResource;
use App\Models\LLMRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('ai')]
#[Group('llm')]
#[Group('resources')]
class LLMRequestResourceTest extends TestCase
{
    #[Test]
    public function it_parses_xml_format_response(): void
    {
        $xmlResponse = '<opening>Hello</opening><title>My Title</title><content>Article content here</content><closing>Goodbye</closing>';

        $llmRequest = new LLMRequest([
            'id' => 'test-id',
            'prompt' => 'Test prompt',
            'response' => $xmlResponse,
            'prompt_system' => 'System prompt',
            'financer_id' => 'financer-id',
            'tokens_used' => 100,
            'engine_used' => 'TestEngine',
        ]);

        $resource = new LLMRequestResource($llmRequest);
        $array = $resource->toArray(request());

        $this->assertEquals([
            'opening' => 'Hello',
            'title' => 'My Title',
            'content' => 'Article content here',
            'closing' => 'Goodbye',
            'full' => $xmlResponse,
        ], $array['response']);
    }

    #[Test]
    public function it_parses_xml_format_with_whitespace(): void
    {
        $xmlResponse = <<<'XML'
<opening>
    Hello world
</opening>
<title>  Article Title  </title>
<content>
Content with multiple lines
and whitespace
</content>
<closing>
    Thank you!
</closing>
XML;

        $llmRequest = new LLMRequest([
            'id' => 'test-id',
            'prompt' => 'Test prompt',
            'response' => $xmlResponse,
            'prompt_system' => 'System prompt',
            'financer_id' => 'financer-id',
            'tokens_used' => 100,
            'engine_used' => 'TestEngine',
        ]);

        $resource = new LLMRequestResource($llmRequest);
        $array = $resource->toArray(request());

        $this->assertEquals('Hello world', $array['response']['opening']);
        $this->assertEquals('Article Title', $array['response']['title']);
        $this->assertStringContainsString('Content with multiple lines', $array['response']['content']);
        $this->assertEquals('Thank you!', $array['response']['closing']);
    }

    #[Test]
    public function it_correctly_splits_legacy_response_with_four_parts(): void
    {
        $llmRequest = new LLMRequest([
            'id' => 'test-id',
            'prompt' => 'Test prompt',
            'response' => 'Part 1§Part 2§Part 3§Part 4',
            'prompt_system' => 'System prompt',
            'financer_id' => 'financer-id',
            'tokens_used' => 100,
            'engine_used' => 'TestEngine',
        ]);

        $resource = new LLMRequestResource($llmRequest);
        $array = $resource->toArray(request());

        $this->assertEquals([
            'opening' => 'Part 1',
            'title' => 'Part 2',
            'content' => 'Part 3',
            'closing' => 'Part 4',
            'full' => 'Part 1§Part 2§Part 3§Part 4',
        ], $array['response']);
    }

    #[Test]
    public function it_handles_legacy_response_with_fewer_parts(): void
    {
        $llmRequest = new LLMRequest([
            'id' => 'test-id',
            'prompt' => 'Test prompt',
            'response' => 'Part 1§Part 2',
            'prompt_system' => 'System prompt',
            'financer_id' => 'financer-id',
            'tokens_used' => 100,
            'engine_used' => 'TestEngine',
        ]);

        $resource = new LLMRequestResource($llmRequest);
        $array = $resource->toArray(request());

        $this->assertEquals([
            'opening' => null,
            'title' => null,
            'content' => null,
            'closing' => null,
            'full' => 'Part 1§Part 2',
        ], $array['response']);
    }

    #[Test]
    public function it_handles_legacy_response_with_more_parts(): void
    {
        $llmRequest = new LLMRequest([
            'id' => 'test-id',
            'prompt' => 'Test prompt',
            'response' => 'Part 1§Part 2§Part 3§Part 4§Part 5',
            'prompt_system' => 'System prompt',
            'financer_id' => 'financer-id',
            'tokens_used' => 100,
            'engine_used' => 'TestEngine',
        ]);

        $resource = new LLMRequestResource($llmRequest);
        $array = $resource->toArray(request());

        $this->assertEquals([
            'opening' => 'Part 1',
            'title' => 'Part 2',
            'content' => 'Part 3',
            'closing' => 'Part 4',
            'full' => 'Part 1§Part 2§Part 3§Part 4§Part 5',
        ], $array['response']);
    }

    #[Test]
    public function it_handles_empty_response(): void
    {
        $llmRequest = new LLMRequest([
            'id' => 'test-id',
            'prompt' => 'Test prompt',
            'response' => '',
            'prompt_system' => 'System prompt',
            'financer_id' => 'financer-id',
            'tokens_used' => 100,
            'engine_used' => 'TestEngine',
        ]);

        $resource = new LLMRequestResource($llmRequest);
        $array = $resource->toArray(request());

        $this->assertEquals([
            'opening' => null,
            'title' => null,
            'content' => null,
            'closing' => null,
            'full' => '',
        ], $array['response']);
    }

    #[Test]
    public function it_handles_null_response(): void
    {
        $llmRequest = new LLMRequest([
            'id' => 'test-id',
            'prompt' => 'Test prompt',
            'response' => null,
            'prompt_system' => 'System prompt',
            'financer_id' => 'financer-id',
            'tokens_used' => 100,
            'engine_used' => 'TestEngine',
        ]);

        $resource = new LLMRequestResource($llmRequest);
        $array = $resource->toArray(request());

        $this->assertEquals([
            'opening' => null,
            'title' => null,
            'content' => null,
            'closing' => null,
            'full' => '',
        ], $array['response']);
    }

    #[Test]
    public function it_handles_plain_text_response(): void
    {
        $llmRequest = new LLMRequest([
            'id' => 'test-id',
            'prompt' => 'Test prompt',
            'response' => 'Just plain text without any markers',
            'prompt_system' => 'System prompt',
            'financer_id' => 'financer-id',
            'tokens_used' => 100,
            'engine_used' => 'TestEngine',
        ]);

        $resource = new LLMRequestResource($llmRequest);
        $array = $resource->toArray(request());

        $this->assertEquals([
            'opening' => null,
            'title' => null,
            'content' => null,
            'closing' => null,
            'full' => 'Just plain text without any markers',
        ], $array['response']);
    }
}
