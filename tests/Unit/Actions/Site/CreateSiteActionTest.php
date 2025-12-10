<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Site;

use App\Actions\Site\CreateSiteAction;
use App\Models\Financer;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('site')]
class CreateSiteActionTest extends TestCase
{
    use DatabaseTransactions;

    private CreateSiteAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CreateSiteAction;
        $this->financer = ModelFactory::createFinancer();
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_creates_a_site_successfully(): void
    {
        // Arrange
        $data = [
            'name' => 'Test Site',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Site::class, $result);
        $this->assertTrue($result->exists);

        // Test name translations using getTranslation method
        $this->assertEquals($data['name'], $result->name);

        // Test financer_id
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_creates_a_site_with_minimum_data(): void
    {
        // Arrange
        $data = [
            'name' => 'Minimal Site',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Site::class, $result);
        $this->assertTrue($result->exists);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_persists_site_to_database(): void
    {
        // Arrange
        $data = [
            'name' => 'Headquarters',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertDatabaseHas('sites', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
        ]);

        // Verify translations are stored as JSONB
        $site = Site::find($result->id);
        $this->assertNotNull($site);
        $this->assertEquals($data['name'], $site->name);
    }

    #[Test]
    public function it_returns_refreshed_site_instance(): void
    {
        // Arrange
        $data = [
            'name' => 'Branch Office',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Site::class, $result);
        $this->assertNotNull($result->id);
        $this->assertNotNull($result->created_at);
        $this->assertNotNull($result->updated_at);
    }

    #[Test]
    public function it_creates_site_for_different_financer(): void
    {
        // Arrange
        $anotherFinancer = ModelFactory::createFinancer();
        $data = [
            'name' => 'Remote Site',
            'financer_id' => $anotherFinancer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Site::class, $result);
        $this->assertEquals($anotherFinancer->id, $result->financer_id);
        $this->assertNotEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_handles_special_characters_in_name(): void
    {
        // Arrange
        $data = [
            'name' => 'Site & Location (Main)',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Site::class, $result);
        $this->assertEquals($data['name'], $result->name);
    }
}
