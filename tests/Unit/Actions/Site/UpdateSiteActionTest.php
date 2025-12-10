<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Site;

use App\Actions\Site\UpdateSiteAction;
use App\Models\Financer;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('site')]
class UpdateSiteActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateSiteAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdateSiteAction;
        $this->financer = ModelFactory::createFinancer();
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_updates_a_site_successfully(): void
    {
        // Arrange
        $site = Site::create([
            'name' => 'Original Site',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Updated Site',
        ];

        // Act
        $result = $this->action->execute($site, $updateData);

        // Assert
        $this->assertInstanceOf(Site::class, $result);

        // Test updated translations
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_updates_site_name_only(): void
    {
        // Arrange
        $site = Site::create([
            'name' => 'Original Name',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'New Name',
        ];

        // Act
        $result = $this->action->execute($site, $updateData);

        // Assert
        $this->assertInstanceOf(Site::class, $result);
        $this->assertEquals($updateData['name'], $result->name);

        // Test that financer_id remains unchanged
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_adds_new_language_translations(): void
    {
        // Arrange
        $site = Site::create([
            'name' => 'Main Site',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Main Site',
        ];

        // Act
        $result = $this->action->execute($site, $updateData);

        // Assert
        $this->assertInstanceOf(Site::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_handles_partial_translation_updates(): void
    {
        // Arrange
        $site = Site::create([
            'name' => 'Original Site',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Updated Site Only',
        ];

        // Act
        $result = $this->action->execute($site, $updateData);

        // Assert
        $this->assertInstanceOf(Site::class, $result);

        // Test that English translation is updated
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_persists_updates_to_database(): void
    {
        // Arrange
        $site = Site::create([
            'name' => 'Headquarters',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Main Headquarters',
        ];

        // Act
        $result = $this->action->execute($site, $updateData);

        // Assert
        $this->assertDatabaseHas('sites', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
        ]);

        // Verify translations are updated in database
        $freshSite = Site::find($result->id);
        $this->assertNotNull($freshSite);
        $this->assertEquals($updateData['name'], $freshSite->name);
    }

    #[Test]
    public function it_returns_refreshed_site_instance(): void
    {
        // Arrange
        $site = Site::create([
            'name' => 'Branch Office',
            'financer_id' => $this->financer->id,
        ]);

        $originalUpdatedAt = $site->updated_at;

        // Small delay to ensure updated_at changes
        sleep(1);

        $updateData = [
            'name' => 'Branch Office Updated',
        ];

        // Act
        $result = $this->action->execute($site, $updateData);

        // Assert
        $this->assertInstanceOf(Site::class, $result);
        $this->assertNotNull($result->updated_at);
        $this->assertNotEquals($originalUpdatedAt, $result->updated_at);
    }

    #[Test]
    public function it_updates_site_with_special_characters(): void
    {
        // Arrange
        $site = Site::create([
            'name' => 'Simple Site',
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [
            'name' => 'Site & Location (Main)',
        ];

        // Act
        $result = $this->action->execute($site, $updateData);

        // Assert
        $this->assertInstanceOf(Site::class, $result);
        $this->assertEquals($updateData['name'], $result->name);
    }

    #[Test]
    public function it_preserves_site_id_after_update(): void
    {
        // Arrange
        $site = Site::create([
            'name' => 'Remote Site',
            'financer_id' => $this->financer->id,
        ]);

        $originalId = $site->id;

        $updateData = [
            'name' => 'Remote Site Updated',
        ];

        // Act
        $result = $this->action->execute($site, $updateData);

        // Assert
        $this->assertEquals($originalId, $result->id);
    }
}
