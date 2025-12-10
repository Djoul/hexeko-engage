<?php

namespace Tests\Unit\Http\Resources\LLMRequest;

use App\Http\Resources\LLMRequest\LLMRequestResourceCollection;
use App\Models\LlmRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('ai')]
#[Group('llm')]
#[Group('resources')]
class LLMRequestResourceCollectionTest extends TestCase
{
    #[Test]
    public function it_handles_null_collection_gracefully(): void
    {
        // Create a collection resource with null
        $resource = new LLMRequestResourceCollection(null);

        // Create a mock request
        $request = Request::create('/test');

        // Transform to array
        $result = $resource->toArray($request);

        // Assert it returns an empty collection instead of causing an error
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertInstanceOf(Collection::class, $result['data']);
        $this->assertCount(0, $result['data']);
    }

    #[Test]
    public function it_handles_empty_collection(): void
    {
        // Create a collection resource with empty collection
        $resource = new LLMRequestResourceCollection(collect());

        // Create a mock request
        $request = Request::create('/test');

        // Transform to array
        $result = $resource->toArray($request);

        // Assert it returns an empty collection
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertInstanceOf(Collection::class, $result['data']);
        $this->assertCount(0, $result['data']);
    }

    #[Test]
    public function it_sorts_collection_by_created_at(): void
    {
        // Create mock LLM requests with different created_at dates
        $request1 = new LlmRequest(['created_at' => now()->addDays(2)]);
        $request2 = new LlmRequest(['created_at' => now()]);
        $request3 = new LlmRequest(['created_at' => now()->addDay()]);

        $collection = collect([$request1, $request2, $request3]);

        // Create a collection resource
        $resource = new LLMRequestResourceCollection($collection);

        // Create a mock request
        $request = Request::create('/test');

        // Transform to array
        $result = $resource->toArray($request);

        // Assert collection is sorted by created_at
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertInstanceOf(Collection::class, $result['data']);

        $sortedData = $result['data']->values();
        $this->assertEquals($request2->created_at, $sortedData[0]->created_at);
        $this->assertEquals($request3->created_at, $sortedData[1]->created_at);
        $this->assertEquals($request1->created_at, $sortedData[2]->created_at);
    }
}
