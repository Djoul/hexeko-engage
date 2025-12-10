<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\IDP\RoleDefaults;
use App\Http\Resources\User\UserIndexResource;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('resources')]
#[Group('user')]
#[Group('UE-729')]
class UserIndexResourceEntryDateTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_returns_started_at_as_entry_date_when_available(): void
    {
        $startedAt = Carbon::parse('2023-01-15');
        $from = Carbon::parse('2024-06-01');

        $authUser = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN, withContext: true);
        $financer = $authUser->financers->first();
        $this->assertInstanceOf(Financer::class, $financer);

        $user = User::factory()->create();
        $user->financers()->attach($financer->id, [
            'active' => true,
            'from' => $from,
            'started_at' => $startedAt,
            'role' => 'beneficiary',
        ]);

        $userWithRelation = User::with('financers')->find($user->id);

        $this->actingAs($authUser);

        $resource = new UserIndexResource($userWithRelation);
        $result = $resource->toArray(request());

        $this->assertArrayHasKey('entry_date', $result);
        $this->assertEquals($startedAt->toISOString(), $result['entry_date']);
    }

    #[Test]
    public function it_returns_from_as_entry_date_when_started_at_is_null(): void
    {
        $from = Carbon::parse('2024-06-01');

        $authUser = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN, withContext: true);
        $financer = $authUser->financers->first();
        $this->assertInstanceOf(Financer::class, $financer);

        $user = User::factory()->create();
        $user->financers()->attach($financer->id, [
            'active' => true,
            'from' => $from,
            'started_at' => null,
            'role' => 'beneficiary',
        ]);

        $userWithRelation = User::with('financers')->find($user->id);

        $this->actingAs($authUser);

        $resource = new UserIndexResource($userWithRelation);
        $result = $resource->toArray(request());

        $this->assertArrayHasKey('entry_date', $result);
        $this->assertEquals($from->toISOString(), $result['entry_date']);
    }

    #[Test]
    public function it_returns_from_as_fallback_when_started_at_is_null(): void
    {
        // Note: When 'from' is null at attach time, FinancerUser model automatically sets it to now()
        // So this test verifies the fallback chain: started_at (null) -> from (auto-set) -> created_at
        $authUser = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN, withContext: true);
        $financer = $authUser->financers->first();
        $this->assertInstanceOf(Financer::class, $financer);

        $createdAt = Carbon::parse('2024-01-01');
        $user = User::factory()->create([
            'created_at' => $createdAt,
        ]);

        // Attach without 'from' - it will be auto-set to now() by FinancerUser model
        $user->financers()->attach($financer->id, [
            'active' => true,
            'started_at' => null,
            'role' => 'beneficiary',
        ]);

        $userWithRelation = User::with('financers')->find($user->id);

        $this->actingAs($authUser);

        $resource = new UserIndexResource($userWithRelation);
        $result = $resource->toArray(request());

        $this->assertArrayHasKey('entry_date', $result);
        // Since 'from' is auto-set to now(), entry_date should NOT be created_at
        // It should be the auto-generated 'from' date (today)
        $this->assertNotEquals($createdAt->toISOString(), $result['entry_date']);
        $this->assertNotNull($result['entry_date']);
    }

    #[Test]
    public function it_returns_user_created_at_when_no_accessible_financers(): void
    {
        $createdAt = Carbon::parse('2024-03-15');
        $user = User::factory()->create([
            'created_at' => $createdAt,
        ]);

        $resource = new UserIndexResource($user);
        $result = $resource->toArray(request());

        $this->assertArrayHasKey('entry_date', $result);
        $this->assertEquals($createdAt->toISOString(), $result['entry_date']);
    }

    #[Test]
    public function it_prioritizes_started_at_over_from_in_entry_date_calculation(): void
    {
        $startedAt = Carbon::parse('2020-01-01');
        $from = Carbon::parse('2024-12-01');

        $authUser = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN, withContext: true);
        $financer = $authUser->financers->first();
        $this->assertInstanceOf(Financer::class, $financer);

        $user = User::factory()->create();
        $user->financers()->attach($financer->id, [
            'active' => true,
            'from' => $from,
            'started_at' => $startedAt,
            'role' => 'beneficiary',
        ]);

        $userWithRelation = User::with('financers')->find($user->id);

        $this->actingAs($authUser);

        $resource = new UserIndexResource($userWithRelation);
        $result = $resource->toArray(request());

        $this->assertEquals(
            $startedAt->toISOString(),
            $result['entry_date'],
            'started_at should take priority over from'
        );
        $this->assertNotEquals(
            $from->toISOString(),
            $result['entry_date'],
            'from should not be used when started_at is available'
        );
    }
}
