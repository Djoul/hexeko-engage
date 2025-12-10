<?php

namespace Tests\Unit\Resolvers;

use App\Models\Audit;
use App\Models\User;
use Context;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('audit')]
class SimpleAuditTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_creates_audit_for_user_creation(): void
    {
        // Enable auditing during tests and for this model
        Config::set('audit.enabled', true);
        Config::set('audit.console', true);
        User::enableAuditing();

        // Test with InvitedUser since we know it creates audits
        $financer = ModelFactory::createFinancer();

        // Provide financer_id in Context for the resolver
        Context::add('financer_id', $financer->id);

        $initialCount = Audit::count();

        $user = ModelFactory::createUser([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $this->assertGreaterThan($initialCount, Audit::count());

        $audit = Audit::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->latest()
            ->first();
        $this->assertEquals(User::class, $audit->auditable_type);
        $this->assertEquals($user->id, $audit->auditable_id);
        $this->assertEquals('created', $audit->event);

        // Check if financer_id was added
        $this->assertEquals($financer->id, $audit->financer_id);
    }
}
