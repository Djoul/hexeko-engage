<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\Security\AuthorizationMode;
use App\Models\Financer;
use App\Models\NotificationTopic;
use App\Models\User;
use App\Policies\NotificationTopicPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('notification-topics')]
class NotificationTopicPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private NotificationTopicPolicy $policy;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new NotificationTopicPolicy;
        $this->financer = Financer::factory()->create([
            'division_id' => ModelFactory::createDivision()->id,
        ]);

        $this->resetAuthorizationContext();
    }

    protected function tearDown(): void
    {
        $this->resetAuthorizationContext();
        parent::tearDown();
    }

    #[Test]
    public function it_allows_viewing_global_topics(): void
    {
        $user = User::factory()->create();
        $topic = NotificationTopic::factory()->create([
            'financer_id' => null,
        ]);

        $this->assertTrue($this->policy->view($user, $topic));
    }

    #[Test]
    public function it_allows_viewing_topics_within_authorized_financer(): void
    {
        $user = User::factory()->create();
        $topic = NotificationTopic::factory()->create([
            'financer_id' => $this->financer->id,
            'is_active' => true,
        ]);

        $this->hydrateAuthorizationContext([$this->financer]);

        $this->assertTrue($this->policy->view($user, $topic));
        $this->assertTrue($this->policy->subscribe($user, $topic));
    }

    #[Test]
    public function it_denies_viewing_topics_outside_authorized_scope(): void
    {
        $user = User::factory()->create();
        $otherFinancer = Financer::factory()->create([
            'division_id' => ModelFactory::createDivision()->id,
        ]);
        $topic = NotificationTopic::factory()->create([
            'financer_id' => $otherFinancer->id,
        ]);

        $this->hydrateAuthorizationContext([$this->financer]);

        $this->assertFalse($this->policy->view($user, $topic));
        $this->assertFalse($this->policy->subscribe($user, $topic));
    }

    #[Test]
    public function it_denies_subscribe_to_inactive_topics(): void
    {
        $user = User::factory()->create();
        $topic = NotificationTopic::factory()->create([
            'financer_id' => $this->financer->id,
            'is_active' => false,
        ]);

        $this->hydrateAuthorizationContext([$this->financer]);

        $this->assertFalse($this->policy->subscribe($user, $topic));
    }

    /**
     * @param  array<int, Financer>  $financers
     */
    private function hydrateAuthorizationContext(array $financers): void
    {
        $financerIds = collect($financers)->pluck('id')->filter()->values()->all();
        $divisionIds = collect($financers)->pluck('division_id')->filter()->values()->all();

        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            $financerIds,
            $divisionIds,
            [],
            $financerIds[0] ?? null
        );
    }

    private function resetAuthorizationContext(): void
    {
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [],
            [],
            [],
            null
        );
    }
}
