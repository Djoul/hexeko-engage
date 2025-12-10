<?php

declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Integrations\InternalCommunication\Actions\CreateDefaultTagsAction;
use App\Integrations\Survey\Actions\CreateDefaultSurveyDataAction;
use App\Models\Financer;
use App\Observers\FinancerObserver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('observer')]
#[Group('financer')]
#[Group('tag')]
class FinancerObserverTest extends TestCase
{
    use DatabaseTransactions;

    private FinancerObserver $observer;

    private MockInterface $createDefaultTagsAction;

    private MockInterface $createDefaultSurveyDataAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createDefaultTagsAction = Mockery::mock(CreateDefaultTagsAction::class);
        $this->createDefaultSurveyDataAction = Mockery::mock(CreateDefaultSurveyDataAction::class);
        $this->observer = new FinancerObserver(
            $this->createDefaultTagsAction,
            $this->createDefaultSurveyDataAction
        );
    }

    #[Test]
    public function it_calls_create_default_tags_action_on_financer_created(): void
    {
        // Arrange
        $financer = Financer::factory()->make(['id' => 'test-uuid']);

        $this->createDefaultTagsAction
            ->shouldReceive('handle')
            ->once()
            ->with(Mockery::on(function ($arg) use ($financer): bool {
                return $arg instanceof Financer && $arg->id === $financer->id;
            }))
            ->andReturnNull();

        // Survey data action should not be called in tests (environment check)
        $this->createDefaultSurveyDataAction
            ->shouldNotReceive('execute');

        // Act
        $this->observer->created($financer);

        // Assert - Mockery verifies expectations automatically
        $this->assertTrue(true);
    }

    #[Test]
    public function it_does_not_call_action_on_financer_updated(): void
    {
        // Arrange
        $financer = Financer::factory()->create();

        $this->createDefaultTagsAction
            ->shouldNotReceive('handle');

        $this->createDefaultSurveyDataAction
            ->shouldNotReceive('execute');

        // Act
        if (method_exists($this->observer, 'updated')) {
            $this->observer->updated($financer);
        }

        // Assert - Mockery verifies expectations automatically
        $this->assertTrue(true);
    }

    #[Test]
    public function it_does_not_call_action_on_financer_deleted(): void
    {
        // Arrange
        $financer = Financer::factory()->create();

        $this->createDefaultTagsAction
            ->shouldNotReceive('handle');

        $this->createDefaultSurveyDataAction
            ->shouldNotReceive('execute');

        // Act
        if (method_exists($this->observer, 'deleted')) {
            $this->observer->deleted($financer);
        }

        // Assert - Mockery verifies expectations automatically
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
