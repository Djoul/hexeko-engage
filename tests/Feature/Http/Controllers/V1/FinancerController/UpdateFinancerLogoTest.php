<?php

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('financer')]
#[Group('media')]
class UpdateFinancerLogoTest extends ProtectedRouteTestCase
{
    const URI = '/api/v1/financers/';

    private function getBase64Image(): string
    {
        // Simple 1x1 PNG image in base64
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    }

    #[Test]
    public function it_can_upload_logo_to_financer(): void
    {
        Storage::fake('local');
        config(['media-library.disk_name' => 'local']);

        $financer = ModelFactory::createFinancer();

        $logo = $this->getBase64Image();

        $response = $this->putJson(self::URI.$financer->id, [
            'name' => $financer->name,
            'division_id' => $financer->division_id,
            'logo' => $logo,
        ]);

        $response->assertStatus(200);

        // Assert media was added
        $financer->refresh();
        $this->assertNotNull($financer->getFirstMediaUrl('logo'));
        $this->assertCount(1, $financer->getMedia('logo'));
    }

    #[Test]
    public function it_replaces_existing_logo_when_uploading_new_one(): void
    {
        Storage::fake('local');
        config(['media-library.disk_name' => 'local']);

        $financer = ModelFactory::createFinancer();

        // Upload first logo
        $firstLogo = $this->getBase64Image();
        $financer->addMediaFromBase64($firstLogo)->toMediaCollection('logo');

        $this->assertCount(1, $financer->getMedia('logo'));

        // Upload second logo (should replace first)
        $secondLogo = $this->getBase64Image();

        $response = $this->putJson(self::URI.$financer->id, [
            'name' => $financer->name,
            'division_id' => $financer->division_id,
            'logo' => $secondLogo,
        ]);

        $response->assertStatus(200);

        // Should still have only 1 logo (replaced)
        $financer->refresh();
        $this->assertCount(1, $financer->getMedia('logo'));
    }

    #[Test]
    public function it_validates_logo_must_be_string(): void
    {
        $financer = ModelFactory::createFinancer();

        $response = $this->putJson(self::URI.$financer->id, [
            'name' => $financer->name,
            'division_id' => $financer->division_id,
            'logo' => 12345, // Invalid: not a string
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['logo']);
    }

    #[Test]
    public function it_can_update_financer_without_logo(): void
    {
        $financer = ModelFactory::createFinancer(['name' => 'Old Name']);

        $response = $this->putJson(self::URI.$financer->id, [
            'name' => 'New Name',
            'division_id' => $financer->division_id,
        ]);

        $response->assertStatus(200);

        $financer->refresh();
        $this->assertEquals('New Name', $financer->name);
        $this->assertCount(0, $financer->getMedia('logo'));
    }

    #[Test]
    public function it_can_remove_logo_by_passing_null(): void
    {
        Storage::fake('local');
        config(['media-library.disk_name' => 'local']);

        $financer = ModelFactory::createFinancer();

        // Add a logo first
        $logo = $this->getBase64Image();
        $financer->addMediaFromBase64($logo)->toMediaCollection('logo');

        $this->assertCount(1, $financer->getMedia('logo'));

        // Remove logo by passing null
        $response = $this->putJson(self::URI.$financer->id, [
            'name' => $financer->name,
            'division_id' => $financer->division_id,
            'logo' => null,
        ]);

        $response->assertStatus(200);

        $financer->refresh();
        $this->assertCount(0, $financer->getMedia('logo'));
    }

    #[Test]
    public function it_returns_logo_url_in_response(): void
    {
        Storage::fake('local');
        config(['media-library.disk_name' => 'local']);

        $financer = ModelFactory::createFinancer();

        // Upload logo
        $logo = $this->getBase64Image();

        $response = $this->putJson(self::URI.$financer->id, [
            'name' => $financer->name,
            'division_id' => $financer->division_id,
            'logo' => $logo,
        ]);

        $response->assertStatus(200);

        // Check if logo_url is in response
        $logoUrl = $response->json('data.logo_url');
        $this->assertNotNull($logoUrl);
        $this->assertStringContainsString($financer->id, $logoUrl);
    }
}
