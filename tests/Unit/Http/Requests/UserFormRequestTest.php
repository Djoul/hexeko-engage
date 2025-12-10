<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\UserFormRequest;
use App\Models\Department;
use App\Models\Financer;
use App\Models\Site;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('user')]
class UserFormRequestTest extends TestCase
{
    use DatabaseTransactions;

    private Financer $financer;

    private Financer $otherFinancer;

    private Department $department;

    private Department $otherDepartment;

    private Site $site;

    private Site $otherSite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financer = Financer::factory()->create();
        $this->otherFinancer = Financer::factory()->create();

        $this->department = Department::factory()->create(['financer_id' => $this->financer->id]);
        $this->otherDepartment = Department::factory()->create(['financer_id' => $this->otherFinancer->id]);

        $this->site = Site::factory()->create(['financer_id' => $this->financer->id]);
        $this->otherSite = Site::factory()->create(['financer_id' => $this->otherFinancer->id]);

        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_validates_departments_belong_to_current_financer(): void
    {
        // Arrange
        $request = new UserFormRequest;
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'departments' => [$this->department->id], // Belongs to current financer
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_rejects_departments_from_other_financer(): void
    {
        // Arrange
        $request = new UserFormRequest;
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'departments' => [$this->otherDepartment->id], // Belongs to other financer
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('departments.0', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_sites_belong_to_current_financer(): void
    {
        // Arrange
        $request = new UserFormRequest;
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'sites' => [$this->site->id], // Belongs to current financer
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_rejects_sites_from_other_financer(): void
    {
        // Arrange
        $request = new UserFormRequest;
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'sites' => [$this->otherSite->id], // Belongs to other financer
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('sites.0', $validator->errors()->toArray());
    }

    #[Test]
    public function it_allows_mixed_valid_and_invalid_departments(): void
    {
        // Arrange
        $request = new UserFormRequest;
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'departments' => [
                $this->department->id,      // Valid (current financer)
                $this->otherDepartment->id, // Invalid (other financer)
            ],
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('departments.1', $validator->errors()->toArray());
        $this->assertArrayNotHasKey('departments.0', $validator->errors()->toArray());
    }

    #[Test]
    public function it_fails_when_no_financer_context(): void
    {
        // Arrange
        Context::forget('financer_id');
        $request = new UserFormRequest;
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'departments' => [$this->department->id],
        ];

        // Act
        $validator = Validator::make($data, $request->rules());

        // Assert
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('departments.0', $validator->errors()->toArray());
    }
}
