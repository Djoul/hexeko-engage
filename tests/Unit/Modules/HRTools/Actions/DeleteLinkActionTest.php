<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\HRTools\Actions;

use App\Integrations\HRTools\Actions\DeleteLinkAction;
use App\Integrations\HRTools\Services\HRToolsLinkService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('hr-tools')]
class DeleteLinkActionTest extends TestCase
{
    use DatabaseTransactions;

    private DeleteLinkAction $action;

    private MockInterface $linkService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->linkService = $this->mock(HRToolsLinkService::class);
        $this->action = new DeleteLinkAction($this->linkService);
    }

    #[Test]
    public function it_deletes_a_link_successfully(): void
    {
        $linkId = 'test-link-id';

        $this->linkService
            ->shouldReceive('deleteLink')
            ->with($linkId)
            ->once()
            ->andReturn(true);

        $result = $this->action->execute($linkId);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_when_link_not_found(): void
    {
        $linkId = 'non-existent-id';

        $this->linkService
            ->shouldReceive('deleteLink')
            ->with($linkId)
            ->once()
            ->andThrow(new Exception('Link not found'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Link not found');

        $this->action->execute($linkId);
    }

    #[Test]
    public function it_handles_service_exception(): void
    {
        $linkId = 'test-link-id';

        $this->linkService
            ->shouldReceive('deleteLink')
            ->with($linkId)
            ->once()
            ->andThrow(new Exception('Service error'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Service error');

        $this->action->execute($linkId);
    }
}
