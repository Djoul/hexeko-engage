<?php

namespace Tests\Unit\Rules;

use App\Enums\IDP\PermissionDefaults;
use App\Models\Financer;
use App\Models\User;
use App\Rules\ConditionalFinancerIdRule;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('validation')]
#[Group('rules')]
class ConditionalFinancerIdRuleTest extends TestCase
{
    private User $user;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = Mockery::mock(User::class);
        $this->financer = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_allows_empty_financer_id_when_user_has_manage_any_financer_permission(): void
    {
        // Arrange - User with MANAGE_ANY_FINANCER permission
        Auth::shouldReceive('user')->andReturn($this->user);
        $this->user->shouldReceive('hasPermissionTo')
            ->with(PermissionDefaults::MANAGE_ANY_FINANCER)
            ->andReturn(true);

        $rule = new ConditionalFinancerIdRule;
        $validator = Validator::make(['financer_id' => null], ['financer_id' => [$rule]]);

        // Assert
        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_validates_financer_access_when_financer_id_is_provided(): void
    {
        // Arrange - User without MANAGE_ANY_FINANCER permission
        Auth::shouldReceive('user')->andReturn($this->user);
        $this->user->shouldReceive('hasPermissionTo')
            ->with(PermissionDefaults::MANAGE_ANY_FINANCER)
            ->andReturn(false);

        // Mock financers relationship to return user's financers
        $financersRelation = Mockery::mock(BelongsToMany::class);
        $financersRelation->shouldReceive('pluck')
            ->with('financers.id')
            ->andReturn(collect([$this->financer->id]));

        $this->user->shouldReceive('financers')
            ->andReturn($financersRelation);

        $rule = new ConditionalFinancerIdRule;
        $validator = Validator::make(['financer_id' => $this->financer->id], ['financer_id' => [$rule]]);

        // Assert
        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_rejects_financer_id_when_user_does_not_have_access_to_that_financer(): void
    {
        // Arrange - User without MANAGE_ANY_FINANCER permission
        $otherFinancer = ModelFactory::createFinancer();

        Auth::shouldReceive('user')->andReturn($this->user);
        $this->user->shouldReceive('hasPermissionTo')
            ->with(PermissionDefaults::MANAGE_ANY_FINANCER)
            ->andReturn(false);

        // Mock financers relationship to return user's financers (not including otherFinancer)
        $financersRelation = Mockery::mock(BelongsToMany::class);
        $financersRelation->shouldReceive('pluck')
            ->with('financers.id')
            ->andReturn(collect([$this->financer->id]));

        $this->user->shouldReceive('financers')
            ->andReturn($financersRelation);

        $rule = new ConditionalFinancerIdRule;
        $validator = Validator::make(['financer_id' => $otherFinancer->id], ['financer_id' => [$rule]]);

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('financer_id', $validator->errors()->toArray());
        $this->assertStringContainsString('assigned financers', $validator->errors()->first('financer_id'));
    }

    #[Test]
    public function it_allows_any_financer_id_when_user_has_manage_any_financer_permission(): void
    {
        // Arrange - User with MANAGE_ANY_FINANCER permission
        $otherFinancer = ModelFactory::createFinancer();

        Auth::shouldReceive('user')->andReturn($this->user);
        $this->user->shouldReceive('hasPermissionTo')
            ->with(PermissionDefaults::MANAGE_ANY_FINANCER)
            ->andReturn(true);

        $rule = new ConditionalFinancerIdRule;
        $validator = Validator::make(['financer_id' => $otherFinancer->id], ['financer_id' => [$rule]]);

        // Assert
        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_fails_when_user_is_not_authenticated(): void
    {
        // Arrange - No authenticated user
        Auth::shouldReceive('user')->andReturn(null);

        $rule = new ConditionalFinancerIdRule;
        $validator = Validator::make(['financer_id' => $this->financer->id], ['financer_id' => [$rule]]);

        // Assert
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('financer_id', $validator->errors()->toArray());
        $this->assertStringContainsString('authenticated', $validator->errors()->first('financer_id'));
    }
}
