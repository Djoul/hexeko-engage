<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Segment;

use App\Actions\Segment\CreateSegmentAction;
use App\Jobs\SyncSegmentUsersJob;
use App\Models\Financer;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('segment')]
class CreateSegmentActionTest extends TestCase
{
    use DatabaseTransactions;

    private CreateSegmentAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CreateSegmentAction;
        $this->financer = ModelFactory::createFinancer();

        Context::flush();
        Context::add('financer_id', $this->financer->id);
        Context::add('accessible_financers', [$this->financer->id]);
    }

    protected function tearDown(): void
    {
        Context::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_creates_a_segment_successfully(): void
    {
        // Arrange
        $data = [
            'name' => 'Customer Engagement',
            'description' => 'Track engaged customers',
            'financer_id' => $this->financer->id,
            'filters' => [],
        ];

        // Act
        $segment = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Segment::class, $segment);
        $this->assertTrue($segment->exists);
        $this->assertSame($data['name'], $segment->name);
        $this->assertSame($data['description'], $segment->description);
        $this->assertSame($data['filters'], $segment->filters);
        $this->assertSame($this->financer->id, $segment->financer_id);
        $this->assertDatabaseHas('segments', [
            'id' => $segment->id,
            'financer_id' => $this->financer->id,
        ]);
    }

    #[Test]
    public function it_dispatches_sync_job_when_segment_has_matching_users(): void
    {
        // Arrange
        Bus::fake();

        $user = User::factory()->create();
        $this->financer->users()->attach($user->id, [
            'active' => true,
            'role' => 'beneficiary',
            'from' => now(),
        ]);

        $data = [
            'name' => 'Segment With Users',
            'description' => 'Segment containing active users',
            'financer_id' => $this->financer->id,
            'filters' => [],
        ];

        // Act
        $segment = $this->action->execute($data);

        // Assert
        Bus::assertDispatched(SyncSegmentUsersJob::class, function (SyncSegmentUsersJob $job) use ($segment): bool {
            /** @var Segment $dispatchedSegment */
            $dispatchedSegment = $this->getProperty($job, 'segment');

            return $dispatchedSegment->is($segment);
        });
    }

    #[Test]
    public function it_does_not_dispatch_sync_job_when_segment_has_no_users(): void
    {
        // Arrange
        Bus::fake();

        $data = [
            'name' => 'Segment Without Users',
            'description' => 'No users should match this segment',
            'financer_id' => $this->financer->id,
            'filters' => [],
        ];

        // Act
        $this->action->execute($data);

        // Assert
        Bus::assertNotDispatched(SyncSegmentUsersJob::class);
    }

    private function getProperty(object $object, string $propertyName): mixed
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }
}
