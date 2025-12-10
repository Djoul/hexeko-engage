<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Segment;

use App\Actions\Segment\UpdateSegmentAction;
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
class UpdateSegmentActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateSegmentAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdateSegmentAction;
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
    public function it_updates_segment_fields_successfully(): void
    {
        // Arrange
        $segment = Segment::factory()->create([
            'financer_id' => $this->financer->id,
            'name' => 'Initial Segment',
            'description' => 'Initial description',
            'filters' => [],
        ]);

        $updateData = [
            'name' => 'Updated Segment',
            'description' => 'Updated description',
            'filters' => [
                [
                    'type' => 'email',
                    'operator' => 'contains',
                    'value' => 'example.com',
                ],
            ],
        ];

        // Act
        $result = $this->action->execute($segment, $updateData);

        // Assert
        $this->assertInstanceOf(Segment::class, $result);
        $this->assertTrue($result->is($segment));
        $this->assertSame($updateData['name'], $result->name);
        $this->assertSame($updateData['description'], $result->description);
        $expectedFilters = $updateData['filters'];
        $actualFilters = $result->filters;

        array_walk($expectedFilters, static fn (array &$filter): bool => ksort($filter));
        array_walk($actualFilters, static fn (&$filter): bool => ksort($filter));

        $this->assertSame($expectedFilters, $actualFilters);
    }

    #[Test]
    public function it_dispatches_sync_job_when_segment_has_matching_users_after_update(): void
    {
        // Arrange
        Bus::fake();

        $segment = Segment::factory()->create([
            'financer_id' => $this->financer->id,
            'filters' => [],
        ]);

        $user = User::factory()->create([
            'email' => 'john@example.com',
        ]);
        $this->financer->users()->attach($user->id, [
            'active' => true,
            'role' => 'beneficiary',
            'from' => now(),
        ]);

        $updateData = [
            'filters' => [
                [
                    'type' => 'email',
                    'operator' => 'contains',
                    'value' => 'example.com',
                ],
            ],
        ];

        // Act
        $this->action->execute($segment, $updateData);

        // Assert
        Bus::assertDispatched(SyncSegmentUsersJob::class, function (SyncSegmentUsersJob $job) use ($segment): bool {
            /** @var Segment $dispatchedSegment */
            $dispatchedSegment = $this->getPrivateProperty($job, 'segment');

            return $dispatchedSegment->is($segment);
        });
    }

    #[Test]
    public function it_does_not_dispatch_sync_job_when_segment_has_no_matching_users_after_update(): void
    {
        // Arrange
        Bus::fake();

        $segment = Segment::factory()->create([
            'financer_id' => $this->financer->id,
            'filters' => [],
        ]);

        $updateData = [
            'description' => 'Updated description without matching users',
            'filters' => [
                [
                    'type' => 'email',
                    'operator' => 'contains',
                    'value' => 'no-match.com',
                ],
            ],
        ];

        // Act
        $this->action->execute($segment, $updateData);

        // Assert
        Bus::assertNotDispatched(SyncSegmentUsersJob::class);
    }

    private function getPrivateProperty(object $object, string $propertyName): mixed
    {
        $ref = new ReflectionClass($object);
        $property = $ref->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }
}
