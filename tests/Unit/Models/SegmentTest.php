<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('segment')]
class SegmentTest extends TestCase
{
    use DatabaseTransactions;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        Context::flush();

        $this->financer = ModelFactory::createFinancer();

        Context::add('financer_id', $this->financer->id);
        Context::add('accessible_financers', [$this->financer->id]);
    }

    protected function tearDown(): void
    {
        Context::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_uses_uuid_as_primary_key(): void
    {
        // Arrange
        $segment = Segment::factory()->make();
        $segmentIncrementing = $segment->getIncrementing();
        $segmentKeyType = $segment->getKeyType();

        // Act

        // Assert
        $this->assertFalse($segmentIncrementing);
        $this->assertEquals('string', $segmentKeyType);
    }

    #[Test]
    public function it_casts_filters_attribute_to_array(): void
    {
        // Arrange
        $filters = ['status' => 'active', 'country' => 'FR'];

        // Act
        $segment = Segment::factory()->create([
            'name' => 'Segment with filters',
            'financer_id' => $this->financer->id,
            'filters' => $filters,
        ]);

        // Assert
        $this->assertSame($filters, $segment->filters);
        $this->assertIsArray($segment->getAttribute('filters'));
    }

    #[Test]
    public function it_belongs_to_a_financer(): void
    {
        // Arrange
        $segment = Segment::factory()->create([
            'name' => 'Segment with Financer',
            'financer_id' => $this->financer->id,
            'filters' => [],
        ]);

        // Act
        $relatedFinancer = $segment->financer;

        // Assert
        $this->assertInstanceOf(Financer::class, $segment->financer);
        $this->assertSame($this->financer->id, $relatedFinancer->id);
    }

    #[Test]
    public function it_can_attach_users_through_relation(): void
    {
        // Arrange
        $segment = Segment::factory()->create([
            'name' => 'Segment with users',
            'financer_id' => $this->financer->id,
            'filters' => [],
        ]);

        $users = User::factory()->count(3)->create();

        // Act
        foreach ($users as $user) {
            $this->financer->users()->attach($user->id, [
                'active' => true,
                'role' => RoleDefaults::BENEFICIARY,
                'from' => now(),
            ]);

            $segment->users()->attach($user->id);
        }

        $segment->load('users');

        // Assert
        $this->assertCount(3, $segment->users);

        foreach ($users as $user) {
            $this->assertDatabaseHas('segment_user', [
                'segment_id' => $segment->id,
                'user_id' => $user->id,
            ]);
        }
    }

    #[Test]
    public function it_computes_users_count_using_financer_scope(): void
    {
        // Arrange
        $segment = Segment::factory()->create([
            'name' => 'Segment with Computed Users',
            'financer_id' => $this->financer->id,
            'filters' => [],
        ]);

        $activeUsers = User::factory()->count(2)->create();
        $inactiveUser = User::factory()->create();

        foreach ($activeUsers as $user) {
            $this->financer->users()->attach($user->id, [
                'active' => true,
                'role' => RoleDefaults::BENEFICIARY,
                'from' => now(),
            ]);
        }

        $this->financer->users()->attach($inactiveUser->id, [
            'active' => false,
            'role' => RoleDefaults::BENEFICIARY,
            'from' => now(),
        ]);

        // Act
        $computedUsersCount = $segment->computed_users_count;

        // Assert
        $this->assertSame(2, $computedUsersCount);
    }

    #[Test]
    public function it_sets_created_by_when_user_authenticated(): void
    {
        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->financer],
            ],
        ]);

        Auth::login($user);

        // Act
        $segment = Segment::factory()->create([
            'name' => 'Segment with Creator',
            'financer_id' => $this->financer->id,
            'filters' => [],
        ]);

        // Assert
        $this->assertSame($user->id, $segment->created_by);
        $this->assertDatabaseHas('segments', [
            'id' => $segment->id,
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_set_created_by_when_no_user_authenticated(): void
    {
        Auth::logout();

        // Act
        $segment = Segment::factory()->create([
            'name' => 'Segment without Creator',
            'financer_id' => $this->financer->id,
            'filters' => [],
        ]);

        // Assert
        $this->assertNull($segment->created_by);
        $this->assertDatabaseHas('segments', [
            'id' => $segment->id,
            'created_by' => null,
        ]);
    }
}
