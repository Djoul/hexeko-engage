<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\HRTools\Actions;

use App\Integrations\HRTools\Actions\UpdateLinkAction;
use App\Integrations\HRTools\Models\Link;
use App\Integrations\HRTools\Services\HRToolsLinkService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('hr-tools')]
class UpdateLinkActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateLinkAction $action;

    private MockInterface $linkService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->linkService = $this->mock(HRToolsLinkService::class);
        $this->action = new UpdateLinkAction($this->linkService);
    }

    #[Test]
    public function it_updates_a_link_successfully(): void
    {
        $linkId = 'test-link-id';
        $data = [
            'name' => ['en' => 'Updated HR Tool', 'fr' => 'Outil RH Mis à jour'],
            'description' => ['en' => 'Updated Description'],
            'url' => ['en' => 'https://updated.com'],
        ];

        $updatedLink = Link::factory()->make(array_merge($data, ['id' => $linkId]));

        $this->linkService
            ->shouldReceive('updateLink')
            ->with($data, $linkId)
            ->once()
            ->andReturn($updatedLink);

        $result = $this->action->execute($data, $linkId);

        $this->assertInstanceOf(Link::class, $result);
        $this->assertEquals($linkId, $result->id);
    }

    #[Test]
    public function it_updates_only_provided_fields(): void
    {
        $linkId = 'test-link-id';
        $data = [
            'position' => 5,
        ];

        $updatedLink = Link::factory()->make(['id' => $linkId, 'position' => 5]);

        $this->linkService
            ->shouldReceive('updateLink')
            ->with($data, $linkId)
            ->once()
            ->andReturn($updatedLink);

        $result = $this->action->execute($data, $linkId);

        $this->assertInstanceOf(Link::class, $result);
        $this->assertEquals(5, $result->position);
    }

    #[Test]
    public function it_updates_link_with_logo(): void
    {
        $linkId = 'test-link-id';
        $data = [
            'name' => ['en' => 'Updated HR Tool'],
            'logo' => 'new-base64-encoded-image',
        ];

        $updatedLink = Link::factory()->make(array_merge($data, ['id' => $linkId]));

        $this->linkService
            ->shouldReceive('updateLink')
            ->with($data, $linkId)
            ->once()
            ->andReturn($updatedLink);

        $result = $this->action->execute($data, $linkId);

        $this->assertInstanceOf(Link::class, $result);
    }

    #[Test]
    public function it_handles_link_not_found_exception(): void
    {
        $linkId = 'non-existent-id';
        $data = ['name' => ['en' => 'Test']];

        $this->linkService
            ->shouldReceive('updateLink')
            ->with($data, $linkId)
            ->once()
            ->andThrow(new Exception('Link not found'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Link not found');

        $this->action->execute($data, $linkId);
    }
}
