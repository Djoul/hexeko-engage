<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation\ThirdPartyApis;

use App\Documentation\ThirdPartyApis\BaseApiDoc;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[Group('third-party-api-doc')]
class BaseApiDocTest extends TestCase
{
    private TestableApiDoc $apiDoc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiDoc = new TestableApiDoc;
    }

    #[Test]
    public function it_requires_api_version_implementation(): void
    {
        $version = $this->apiDoc->getApiVersion();

        $this->assertIsString($version);
        $this->assertNotEmpty($version);
        $this->assertEquals('v1-test', $version);
    }

    #[Test]
    public function it_requires_last_verified_date_implementation(): void
    {
        $date = $this->apiDoc->getLastVerified();

        $this->assertIsString($date);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
        $this->assertEquals('2025-08-07', $date);
    }

    #[Test]
    public function it_requires_provider_name_implementation(): void
    {
        $provider = $this->apiDoc->getProviderName();

        $this->assertIsString($provider);
        $this->assertNotEmpty($provider);
        $this->assertEquals('testable', $provider);
    }

    #[Test]
    public function it_loads_response_files_correctly(): void
    {
        // Create test response file
        $testDir = app_path('Documentation/ThirdPartyApis/responses/testable');
        if (! is_dir($testDir)) {
            mkdir($testDir, 0755, true);
        }

        $testData = ['status' => 'success', 'data' => ['id' => 123]];
        file_put_contents(
            $testDir.'/test-response.json',
            json_encode($testData, JSON_PRETTY_PRINT)
        );

        $response = $this->apiDoc->loadResponseFile('test-response.json');

        $this->assertIsArray($response);
        $this->assertEquals($testData, $response);

        // Cleanup
        unlink($testDir.'/test-response.json');
        if (is_dir($testDir)) {
            rmdir($testDir);
        }
    }

    #[Test]
    public function it_throws_exception_for_missing_response_file(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response file not found: non-existent.json');

        $this->apiDoc->loadResponseFile('non-existent.json');
    }

    #[Test]
    public function it_discovers_all_endpoint_methods(): void
    {
        $endpoints = $this->apiDoc->getAllEndpoints();

        $this->assertIsArray($endpoints);
        $this->assertContains('testEndpoint', $endpoints);
        $this->assertNotContains('getApiVersion', $endpoints);
        $this->assertNotContains('getLastVerified', $endpoints);
        $this->assertNotContains('getProviderName', $endpoints);
        $this->assertNotContains('getAllEndpoints', $endpoints);
        $this->assertNotContains('loadResponse', $endpoints);
    }

    #[Test]
    public function it_provides_endpoint_documentation_structure(): void
    {
        $doc = $this->apiDoc->testEndpoint();

        $this->assertIsArray($doc);
        $this->assertArrayHasKey('description', $doc);
        $this->assertArrayHasKey('endpoint', $doc);
        $this->assertArrayHasKey('method', $doc);
        $this->assertEquals('Test endpoint for unit testing', $doc['description']);
        $this->assertEquals('/api/test', $doc['endpoint']);
        $this->assertEquals('GET', $doc['method']);
    }
}

/**
 * Concrete implementation for testing abstract BaseApiDoc
 */
class TestableApiDoc extends BaseApiDoc
{
    public static function getApiVersion(): string
    {
        return 'v1-test';
    }

    public static function getLastVerified(): string
    {
        return '2025-08-07';
    }

    public static function getProviderName(): string
    {
        return 'testable';
    }

    public static function testEndpoint(): array
    {
        return [
            'description' => 'Test endpoint for unit testing',
            'endpoint' => '/api/test',
            'method' => 'GET',
            'parameters' => [],
            'responses' => [
                '200' => ['status' => 'success'],
            ],
        ];
    }

    // Expose protected method for testing
    public function loadResponseFile(string $file): array
    {
        return self::loadResponse($file);
    }
}
