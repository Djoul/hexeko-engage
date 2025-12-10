<?php

namespace Tests\Feature\Http\Controllers\V1\Apideck\WebhookController;

use App\Actions\User\InvitedUser\CreateInvitedUserAction;
use App\DTOs\ApideckEmployeeDTO;
use App\Enums\IDP\TeamTypes;
use App\Services\Apideck\ApideckService;
use Bus;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('apideck')]
class WebhookControllerTest extends ProtectedRouteTestCase
{
    public $financer;

    private ApideckService $apideckService;

    protected function setUp(): void
    {
        parent::setUp();
        ModelFactory::createTeam(['type' => TeamTypes::GLOBAL]);

        $this->financer = ModelFactory::createFinancer([
            'external_id' => ['sirh' => ['consumer_id' => 'valid-consumer-id']],
        ]);
        $this->apideckService = $this->mock(ApideckService::class);
    }

    #[Test]
    public function it_returns_404_if_financer_not_found(): void
    {
        $response = $this->postJson(route('webhooks.apideck'), [
            'payload' => ['consumer_id' => 'nonexistent', 'entity_id' => '12345'],
        ]);

        $response->assertStatus(404)
            ->assertJson(['message' => 'Financer not found']);
    }

    #[Test]
    public function it_returns_200_but_does_not_dispatch_job_if_employee_not_found(): void
    {
        // Mock initializeConsumerId which is called before getEmployee
        $this->apideckService
            ->shouldReceive('initializeConsumerId')
            ->once()
            ->with($this->financer->id)
            ->andReturnNull();

        // Simuler une réponse vide de ApideckService
        $this->apideckService
            ->shouldReceive('getEmployee')
            ->once()
            ->with('12345')
            ->andReturn(['data' => null]);

        Queue::fake();

        $response = $this->postJson(route('webhooks.apideck'), [
            'payload' => ['consumer_id' => 'valid-consumer-id', 'entity_id' => '12345'],
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Webhook received']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_dispatches_create_user_action_if_employee_found(): void
    {
        Bus::fake();

        $employeeData = ['id' => 'employee-123', 'name' => 'John Doe'];

        // Use partial mock to allow real methods except getEmployee
        $this->apideckService = Mockery::mock(ApideckService::class)->makePartial();

        $this->apideckService
            ->shouldReceive('getEmployee')
            ->once()
            ->with('12345')
            ->andReturn(['data' => $employeeData]);

        $this->app->instance(ApideckService::class, $this->apideckService);

        // Mock DTO transformation
        $dtoMock = Mockery::mock(ApideckEmployeeDTO::class, [$employeeData])
            ->shouldReceive('toUserModelArray')
            ->andReturn(['name' => 'John Doe', 'financers' => []])
            ->getMock();

        $this->app->instance(ApideckEmployeeDTO::class, $dtoMock);

        Queue::fake();

        $response = $this->postJson(route('webhooks.apideck'), [
            'payload' => ['consumer_id' => 'valid-consumer-id', 'entity_id' => '12345'],
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Webhook received']);

        Bus::assertDispatchedTimes(CreateInvitedUserAction::class, 1);
    }
}
