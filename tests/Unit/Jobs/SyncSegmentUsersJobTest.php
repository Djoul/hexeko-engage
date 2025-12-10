<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\SyncSegmentUsersJob;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('segment')]
class SyncSegmentUsersJobTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_adds_missing_users_to_segment(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        $segment = Segment::factory()->create([
            'financer_id' => $financer->id,
            'filters' => [],
        ]);

        $users = User::factory()->count(2)->create();

        foreach ($users as $user) {
            $financer->users()->attach($user->id, [
                'active' => true,
                'role' => 'beneficiary',
                'from' => now(),
            ]);
        }

        // Act
        (new SyncSegmentUsersJob($segment))->handle();

        // Assert
        $segment->refresh()->load('users');

        foreach ($users as $user) {
            $this->assertDatabaseHas('segment_user', [
                'segment_id' => $segment->id,
                'user_id' => $user->id,
            ]);
        }

        $this->assertCount(2, $segment->users);
        $this->assertSame(2, $segment->computed_users_count);
    }

    #[Test]
    public function it_removes_users_that_no_longer_match_segment_filters(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        $segment = Segment::factory()->create([
            'financer_id' => $financer->id,
            'filters' => [],
        ]);

        $matchingUser = User::factory()->create();
        $staleUser = User::factory()->create();

        $financer->users()->attach($matchingUser->id, [
            'active' => true,
            'role' => 'beneficiary',
            'from' => now(),
        ]);

        $segment->users()->attach($staleUser->id);

        // Act
        (new SyncSegmentUsersJob($segment))->handle();

        // Assert
        $segment->refresh()->load('users');

        $this->assertDatabaseMissing('segment_user', [
            'segment_id' => $segment->id,
            'user_id' => $staleUser->id,
        ]);

        $this->assertDatabaseHas('segment_user', [
            'segment_id' => $segment->id,
            'user_id' => $matchingUser->id,
        ]);

        $this->assertCount(1, $segment->users);
        $this->assertSame(1, $segment->computed_users_count);
    }

    #[Test]
    public function it_does_not_create_duplicate_links_when_segment_is_already_synced(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        $segment = Segment::factory()->create([
            'financer_id' => $financer->id,
            'filters' => [],
        ]);

        $user = User::factory()->create();

        $financer->users()->attach($user->id, [
            'active' => true,
            'role' => 'beneficiary',
            'from' => now(),
        ]);

        $segment->users()->attach($user->id);

        // Act
        (new SyncSegmentUsersJob($segment))->handle();

        // Assert
        $segment->refresh()->load('users');

        $this->assertCount(1, $segment->users);
        $this->assertSame(1, $segment->computed_users_count);
        $this->assertDatabaseHas('segment_user', [
            'segment_id' => $segment->id,
            'user_id' => $user->id,
        ]);
    }
}
