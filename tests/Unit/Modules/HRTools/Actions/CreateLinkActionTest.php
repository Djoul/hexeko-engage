<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\HRTools\Actions;

use App\Integrations\HRTools\Actions\CreateLinkAction;
use App\Integrations\HRTools\Models\Link;
use App\Integrations\HRTools\Services\HRToolsLinkService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('hr-tools')]
class CreateLinkActionTest extends TestCase
{
    use DatabaseTransactions;

    private CreateLinkAction $action;

    private MockInterface $linkService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->linkService = $this->mock(HRToolsLinkService::class);
        $this->action = new CreateLinkAction($this->linkService);
    }

    #[Test]
    public function it_creates_a_link_successfully(): void
    {
        $data = [
            'name' => ['en' => 'HR Tool', 'fr' => 'Outil RH'],
            'description' => ['en' => 'Description', 'fr' => 'Description FR'],
            'url' => ['en' => 'https://example.com', 'fr' => 'https://example.fr'],
            'financer_id' => 'test-financer-id',
            'is_active' => true,
        ];

        $expectedLink = Link::factory()->make($data);

        $this->linkService
            ->shouldReceive('storeLink')
            ->with($data)
            ->once()
            ->andReturn($expectedLink);

        $result = $this->action->execute($data);

        $this->assertInstanceOf(Link::class, $result);
        $this->assertEquals($expectedLink->id, $result->id);
    }

    #[Test]
    public function it_creates_a_link_with_logo(): void
    {
        $data = [
            'name' => ['en' => 'HR Tool'],
            'url' => ['en' => 'https://example.com'],
            'financer_id' => 'test-financer-id',
            'logo' => 'base64-encoded-image-data',
        ];

        $expectedLink = Link::factory()->make($data);

        $this->linkService
            ->shouldReceive('storeLink')
            ->with($data)
            ->once()
            ->andReturn($expectedLink);

        $result = $this->action->execute($data);

        $this->assertInstanceOf(Link::class, $result);
    }

    #[Test]
    public function it_creates_a_link_with_minimal_data(): void
    {
        $data = [
            'name' => ['en' => 'HR Tool'],
            'url' => ['en' => 'https://example.com'],
            'financer_id' => 'test-financer-id',
        ];

        $expectedLink = Link::factory()->make($data);

        $this->linkService
            ->shouldReceive('storeLink')
            ->with($data)
            ->once()
            ->andReturn($expectedLink);

        $result = $this->action->execute($data);

        $this->assertInstanceOf(Link::class, $result);
    }

    #[Test]
    public function it_handles_service_exception(): void
    {
        $data = [
            'name' => ['en' => 'HR Tool'],
            'url' => ['en' => 'https://example.com'],
            'financer_id' => 'test-financer-id',
        ];

        $this->linkService
            ->shouldReceive('storeLink')
            ->with($data)
            ->once()
            ->andThrow(new Exception('Service error'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Service error');

        $this->action->execute($data);
    }
}
