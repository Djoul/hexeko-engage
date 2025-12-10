<?php

namespace Tests\Unit\AI\Contracts;

use App\AI\Contracts\LLMClientInterface;
use App\AI\DTOs\LLMResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('ai')]
#[Group('llm')]
class LLMClientInterfaceTest extends TestCase
{
    public function test_interface_can_be_mocked_and_used(): void
    {
        $mock = $this->createMock(LLMClientInterface::class);

        $mockResponse = new LLMResponse(
            'Hello world',
            42
        );

        $mock->method('sendPrompt')
            ->willReturn($mockResponse);

        $mock->method('getName')
            ->willReturn('mock-engine');

        $response = $mock->sendPrompt(['user_input' => 'Write something']);
        $this->assertInstanceOf(LLMResponse::class, $response);
        $this->assertEquals('Hello world', $response->chatResponse);
        $this->assertEquals(42, $response->tokensUsed);
        $this->assertEquals('mock-engine', $mock->getName());
    }
}
