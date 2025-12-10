<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TranslationManifestService;
use App\Services\TranslationMigrations\S3StorageService;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('admin-panel')]
#[Group('translation')]
#[Group('translation-migration')]
#[Group('translation-protection')]
class TranslationManifestServiceTest extends TestCase
{
    private TranslationManifestService $service;

    private MockInterface $s3Service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->s3Service = $this->mock(S3StorageService::class);
        $this->service = new TranslationManifestService($this->s3Service);
    }

    #[Test]
    public function it_retrieves_and_parses_manifest_from_s3(): void
    {
        // Arrange
        $interface = 'mobile';
        $manifestContent = json_encode([
            'interface' => 'mobile',
            'latest_version' => '2025-09-24_143022',
            'files' => [
                ['filename' => 'mobile_2025-09-24_143022.json', 'checksum' => 'abc123'],
                ['filename' => 'mobile_2025-09-24_140101.json', 'checksum' => 'def456'],
            ],
        ]);

        $this->s3Service->shouldReceive('getManifest')
            ->with($interface)
            ->once()
            ->andReturn($manifestContent);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        // Act
        $result = $this->service->getManifest($interface);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('mobile', $result['interface']);
        $this->assertEquals('2025-09-24_143022', $result['latest_version']);
        $this->assertCount(2, $result['files']);
    }

    #[Test]
    public function it_returns_null_when_manifest_does_not_exist(): void
    {
        // Arrange
        $interface = 'web';

        $this->s3Service->shouldReceive('getManifest')
            ->with($interface)
            ->once()
            ->andReturn(null);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        // Act
        $result = $this->service->getManifest($interface);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function it_writes_valid_json_manifest_to_s3(): void
    {
        // Arrange
        $interface = 'admin';
        $manifestData = [
            'interface' => 'admin',
            'latest_version' => '2025-09-25_100000',
            'files' => [
                ['filename' => 'admin_2025-09-25_100000.json', 'checksum' => 'xyz789'],
            ],
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $this->s3Service->shouldReceive('updateManifest')
            ->with($interface, json_encode($manifestData, JSON_PRETTY_PRINT))
            ->once()
            ->andReturn(true);

        Cache::shouldReceive('forget')
            ->with("translation_manifest_{$interface}")
            ->once();

        // Act
        $result = $this->service->updateManifest($interface, $manifestData);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_validates_file_against_manifest_approved_files(): void
    {
        // Arrange
        $interface = 'mobile';
        $filename = 'mobile_2025-09-24_143022.json';
        $manifest = [
            'files' => [
                ['filename' => 'mobile_2025-09-24_143022.json', 'checksum' => 'abc123'],
                ['filename' => 'mobile_2025-09-24_140101.json', 'checksum' => 'def456'],
            ],
        ];

        // Mock getManifest to return our test manifest
        $service = $this->getMockBuilder(TranslationManifestService::class)
            ->setConstructorArgs([$this->s3Service])
            ->onlyMethods(['getManifest'])
            ->getMock();

        $service->expects($this->once())
            ->method('getManifest')
            ->with($interface)
            ->willReturn($manifest);

        // Act
        $result = $service->validateAgainstManifest($interface, $filename);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_for_unapproved_files(): void
    {
        // Arrange
        $interface = 'web';
        $filename = 'web_2025-09-25_120000.json';
        $manifest = [
            'files' => [
                ['filename' => 'web_2025-09-24_143022.json', 'checksum' => 'abc123'],
            ],
        ];

        // Mock getManifest to return our test manifest
        $service = $this->getMockBuilder(TranslationManifestService::class)
            ->setConstructorArgs([$this->s3Service])
            ->onlyMethods(['getManifest'])
            ->getMock();

        $service->expects($this->once())
            ->method('getManifest')
            ->with($interface)
            ->willReturn($manifest);

        // Act
        $result = $service->validateAgainstManifest($interface, $filename);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function it_returns_true_when_no_manifest_exists(): void
    {
        // Arrange
        $interface = 'admin';
        $filename = 'admin_2025-09-25_150000.json';

        // Mock getManifest to return null (no manifest)
        $service = $this->getMockBuilder(TranslationManifestService::class)
            ->setConstructorArgs([$this->s3Service])
            ->onlyMethods(['getManifest'])
            ->getMock();

        $service->expects($this->once())
            ->method('getManifest')
            ->with($interface)
            ->willReturn(null);

        // Act
        $result = $service->validateAgainstManifest($interface, $filename);

        // Assert
        $this->assertTrue($result); // No manifest means no validation required
    }

    #[Test]
    public function it_handles_json_parsing_errors_gracefully(): void
    {
        // Arrange
        $interface = 'mobile';
        $invalidJson = 'not-valid-json{]';

        $this->s3Service->shouldReceive('getManifest')
            ->with($interface)
            ->once()
            ->andReturn($invalidJson);

        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        // Act
        $result = $this->service->getManifest($interface);

        // Assert
        $this->assertNull($result);
    }
}
