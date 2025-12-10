<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Media;

use App\Actions\Media\ConvertHeicToJpgAction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

#[Group('media')]
#[Group('images')]
class ConvertHeicToJpgActionTest extends TestCase
{
    private ConvertHeicToJpgAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new ConvertHeicToJpgAction;
    }

    #[Test]
    public function it_extracts_base64_data_from_data_uri(): void
    {
        $base64Data = base64_encode('test_data');
        $dataUri = "data:image/heic;base64,$base64Data";

        $reflection = new ReflectionClass($this->action);
        $method = $reflection->getMethod('extractBase64Data');
        $method->setAccessible(true);

        $result = $method->invoke($this->action, $dataUri);

        $this->assertEquals($base64Data, $result);
    }

    #[Test]
    public function it_returns_data_unchanged_if_not_data_uri(): void
    {
        $plainBase64 = base64_encode('test_data');

        $reflection = new ReflectionClass($this->action);
        $method = $reflection->getMethod('extractBase64Data');
        $method->setAccessible(true);

        $result = $method->invoke($this->action, $plainBase64);

        $this->assertEquals($plainBase64, $result);
    }

    #[Test]
    public function it_creates_temp_file_with_correct_extension(): void
    {
        $binaryData = 'test binary data';
        $extension = 'heic';

        $reflection = new ReflectionClass($this->action);
        $method = $reflection->getMethod('createTempFile');
        $method->setAccessible(true);

        $tempPath = $method->invoke($this->action, $binaryData, $extension);

        // Verify file exists
        $this->assertFileExists($tempPath);

        // Verify extension
        $this->assertStringEndsWith(".{$extension}", $tempPath);

        // Verify content
        $this->assertEquals($binaryData, file_get_contents($tempPath));

        // Cleanup
        @unlink($tempPath);
    }

    #[Test]
    public function it_throws_exception_when_base64_decode_fails(): void
    {
        $invalidBase64 = 'data:image/heic;base64,!!!invalid-base64!!!';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to decode base64 HEIC data');

        $this->action->execute($invalidBase64);
    }

    /**
     * Note: Full integration test with actual HEIC conversion requires a real HEIC file
     * This is covered in Feature tests with real files
     */
}
