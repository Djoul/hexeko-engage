<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Media;

use App\Actions\Media\ConvertHeicToJpgAction;
use App\Services\Media\HeicImageConversionService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

#[Group('media')]
#[Group('images')]
class HeicImageConversionServiceTest extends TestCase
{
    private HeicImageConversionService $service;

    private MockObject $convertAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->convertAction = $this->createMock(ConvertHeicToJpgAction::class);
        $this->service = new HeicImageConversionService($this->convertAction);
    }

    #[Test]
    public function it_detects_heic_image_from_data_uri(): void
    {
        // Test various HEIC data URI formats
        $heicFormats = [
            'data:image/heic;base64,'.base64_encode('fake_image_data'),
            'data:image/HEIC;base64,'.base64_encode('fake_image_data'),
            'data:image/heif;base64,'.base64_encode('fake_image_data'),
            'data:image/HEIF;base64,'.base64_encode('fake_image_data'),
        ];

        foreach ($heicFormats as $heicImage) {
            $this->assertTrue(
                $this->service->isHeicImage($heicImage),
                "Failed to detect HEIC format: $heicImage"
            );
        }
    }

    #[Test]
    public function it_does_not_detect_non_heic_images(): void
    {
        $nonHeicFormats = [
            'data:image/jpeg;base64,'.base64_encode('fake_image_data'),
            'data:image/png;base64,'.base64_encode('fake_image_data'),
            'data:image/gif;base64,'.base64_encode('fake_image_data'),
            'data:image/webp;base64,'.base64_encode('fake_image_data'),
        ];

        foreach ($nonHeicFormats as $nonHeicImage) {
            $this->assertFalse(
                $this->service->isHeicImage($nonHeicImage),
                "Incorrectly detected as HEIC: $nonHeicImage"
            );
        }
    }

    #[Test]
    public function it_converts_heic_image_to_jpg(): void
    {
        $heicImage = 'data:image/heic;base64,'.base64_encode('fake_heic_data');
        $expectedJpgImage = 'data:image/jpeg;base64,'.base64_encode('fake_jpg_data');

        $this->convertAction
            ->expects($this->once())
            ->method('execute')
            ->with($heicImage)
            ->willReturn($expectedJpgImage);

        $result = $this->service->processImage($heicImage);

        $this->assertEquals($expectedJpgImage, $result);
    }

    #[Test]
    public function it_returns_non_heic_image_unchanged(): void
    {
        $jpegImage = 'data:image/jpeg;base64,'.base64_encode('fake_jpeg_data');

        // Convert action should NOT be called for non-HEIC images
        $this->convertAction
            ->expects($this->never())
            ->method('execute');

        $result = $this->service->processImage($jpegImage);

        $this->assertEquals($jpegImage, $result);
    }

    #[Test]
    public function it_propagates_conversion_exceptions(): void
    {
        $heicImage = 'data:image/heic;base64,'.base64_encode('fake_heic_data');

        $this->convertAction
            ->expects($this->once())
            ->method('execute')
            ->willThrowException(new RuntimeException('Conversion failed'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Conversion failed');

        $this->service->processImage($heicImage);
    }

    #[Test]
    public function it_extracts_base64_data_from_data_uri(): void
    {
        $base64Data = base64_encode('test_data');
        $dataUri = "data:image/jpeg;base64,$base64Data";

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('extractBase64Data');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $dataUri);

        $this->assertEquals($base64Data, $result);
    }

    #[Test]
    public function it_returns_data_unchanged_if_not_data_uri(): void
    {
        $plainBase64 = base64_encode('test_data');

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('extractBase64Data');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $plainBase64);

        $this->assertEquals($plainBase64, $result);
    }
}
