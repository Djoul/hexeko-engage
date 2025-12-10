<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\HRTools\Actions;

use App\Integrations\HRTools\Actions\ReorderLinksAction;
use App\Integrations\HRTools\Services\HRToolsLinkService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('hr-tools')]
class ReorderLinksActionTest extends TestCase
{
    use DatabaseTransactions;

    private ReorderLinksAction $action;

    private MockInterface $linkService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->linkService = $this->mock(HRToolsLinkService::class);
        $this->action = new ReorderLinksAction($this->linkService);
    }

    #[Test]
    public function it_reorders_links_successfully(): void
    {
        $links = [
            ['id' => 'link-1', 'position' => 2],
            ['id' => 'link-2', 'position' => 1],
            ['id' => 'link-3', 'position' => 3],
        ];

        $this->linkService
            ->shouldReceive('reorderLinks')
            ->with($links)
            ->once()
            ->andReturn(true);

        $result = $this->action->execute($links);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_empty_links_array(): void
    {
        $links = [];

        $this->linkService
            ->shouldReceive('reorderLinks')
            ->with($links)
            ->once()
            ->andReturn(true);

        $result = $this->action->execute($links);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_service_exception(): void
    {
        $links = [
            ['id' => 'link-1', 'position' => 1],
        ];

        $this->linkService
            ->shouldReceive('reorderLinks')
            ->with($links)
            ->once()
            ->andThrow(new Exception('Reorder failed'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Reorder failed');

        $this->action->execute($links);
    }

    #[Test]
    public function it_handles_invalid_link_id(): void
    {
        $links = [
            ['id' => 'invalid-id', 'position' => 1],
        ];

        $this->linkService
            ->shouldReceive('reorderLinks')
            ->with($links)
            ->once()
            ->andThrow(new ModelNotFoundException);

        $this->expectException(ModelNotFoundException::class);

        $this->action->execute($links);
    }
}
