<?php

namespace Tests\Unit\Rules;

use App\Models\Department;
use App\Models\Financer;
use App\Models\Site;
use App\Rules\BelongsToCurrentFinancer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('financer')]
class BelongsToCurrentFinancerTest extends TestCase
{
    use DatabaseTransactions;

    private $auth;

    private Financer $financer;

    private Financer $otherFinancer;

    private Department $department;

    private Department $otherDepartment;

    private Site $site;

    private Site $otherSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auth = ModelFactory::createUser();
        $this->financer = ModelFactory::createFinancer();
        $this->auth->financers()->attach($this->financer->id);
        $this->otherFinancer = Financer::factory()->create();

        $this->department = Department::factory()->create(['financer_id' => $this->financer->id]);
        $this->otherDepartment = Department::factory()->create(['financer_id' => $this->otherFinancer->id]);

        $this->site = Site::factory()->create(['financer_id' => $this->financer->id]);
        $this->otherSite = Site::factory()->create(['financer_id' => $this->otherFinancer->id]);

        authorizationContext()->hydrateFromRequest($this->auth);
    }

    #[Test]
    public function it_passes_when_department_belongs_to_current_financer(): void
    {
        // Arrange
        $rule = new BelongsToCurrentFinancer('departments');

        // Act
        $result = $rule->passes('departments.0', $this->department->id);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_fails_when_department_belongs_to_other_financer(): void
    {
        // Arrange
        $rule = new BelongsToCurrentFinancer('departments');

        // Act
        $result = $rule->passes('departments.0', $this->otherDepartment->id);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function it_passes_when_site_belongs_to_current_financer(): void
    {
        // Arrange
        $rule = new BelongsToCurrentFinancer('sites');

        // Act
        $result = $rule->passes('sites.0', $this->site->id);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_fails_when_site_belongs_to_other_financer(): void
    {
        // Arrange
        $rule = new BelongsToCurrentFinancer('sites');

        // Act
        $result = $rule->passes('sites.0', $this->otherSite->id);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function it_passes_when_value_is_null(): void
    {
        // Arrange
        $rule = new BelongsToCurrentFinancer('departments');

        // Act
        $result = $rule->passes('departments.0', null);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_fails_when_entity_does_not_exist(): void
    {
        // Arrange
        $rule = new BelongsToCurrentFinancer('departments');
        $nonExistentId = '123e4567-e89b-12d3-a456-426614174000';

        // Act
        $result = $rule->passes('departments.0', $nonExistentId);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function it_uses_custom_foreign_key(): void
    {
        // Arrange
        $rule = new BelongsToCurrentFinancer('departments', 'custom_financer_id');

        // Act & Assert
        $this->expectException(QueryException::class);
        $rule->passes('departments.0', $this->department->id);
    }

    #[Test]
    public function it_returns_correct_error_message(): void
    {
        // Arrange
        $rule = new BelongsToCurrentFinancer('departments');

        // Act
        $message = $rule->message();

        // Assert
        $this->assertEquals('The selected :attribute does not belong to the current financer.', $message);
    }
}
