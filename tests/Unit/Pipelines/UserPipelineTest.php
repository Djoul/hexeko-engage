<?php

namespace Tests\Unit\Pipelines;

use App\Models\User;
use App\QueryFilters\ModelSpecific\User\EmailFilter;
use App\QueryFilters\ModelSpecific\User\FirstNameFilter;
use App\QueryFilters\ModelSpecific\User\LastNameFilter;
use App\QueryFilters\ModelSpecific\User\RoleFilter;
use App\QueryFilters\ModelSpecific\User\TeamIdFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use Tests\TestCase;

#[Group('user')]
class UserPipelineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();

        // Set a dummy financer_id in Context for User model global scope queries
        Context::add('financer_id', 'test-financer-id');
    }

    /**
     * Appelle une méthode protégée ou privée d'un objet.
     *
     * @param  object  $object  L'objet sur lequel appeler la méthode
     * @param  string  $methodName  Le nom de la méthode à appeler
     * @param  array  $parameters  Les paramètres à passer à la méthode
     * @return mixed Le résultat de l'appel de méthode
     */
    protected function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function test_email_filter_applies_correctly(): void
    {
        // Arrange
        $query = User::query();
        $email = 'test@example.com';

        // Mock request with email parameter
        $this->instance(
            Request::class,
            Mockery::mock(Request::class, function (MockInterface $mock) use ($email): void {
                $mock->shouldReceive('query')
                    ->with('email')
                    ->andReturn($email);
            })
        );

        // Act
        $filter = new EmailFilter;
        $result = $this->invokeMethod($filter, 'applyFilter', [$query, $email]);

        // Assert
        $this->assertInstanceOf(Builder::class, $result);
        $this->assertStringContainsString(
            'where "email"',
            $result->toSql()
        );
        $this->assertStringContainsString(
            'ilike',
            $result->toSql()
        );
    }

    public function test_first_name_filter_applies_correctly(): void
    {
        // Arrange
        $query = User::query();
        $firstName = 'John';

        // Mock request with first_name parameter
        $this->instance(
            Request::class,
            Mockery::mock(Request::class, function (MockInterface $mock) use ($firstName): void {
                $mock->shouldReceive('query')
                    ->with('first_name')
                    ->andReturn($firstName);
            })
        );

        // Act
        $filter = new FirstNameFilter;
        $result = $this->invokeMethod($filter, 'applyFilter', [$query, $firstName]);

        // Assert
        $this->assertInstanceOf(Builder::class, $result);
        $this->assertStringContainsString(
            'where "first_name"',
            $result->toSql()
        );
        $this->assertStringContainsString(
            'ilike',
            $result->toSql()
        );
    }

    public function test_last_name_filter_applies_correctly(): void
    {
        // Arrange
        $query = User::query();
        $lastName = 'Doe';

        // Mock request with last_name parameter
        $this->instance(
            Request::class,
            Mockery::mock(Request::class, function (MockInterface $mock) use ($lastName): void {
                $mock->shouldReceive('query')
                    ->with('last_name')
                    ->andReturn($lastName);
            })
        );

        // Act
        $filter = new LastNameFilter;
        $result = $this->invokeMethod($filter, 'applyFilter', [$query, $lastName]);

        // Assert
        $this->assertInstanceOf(Builder::class, $result);
        $this->assertStringContainsString(
            'where "last_name"',
            $result->toSql()
        );
        $this->assertStringContainsString(
            'ilike',
            $result->toSql()
        );
    }

    public function test_team_id_filter_applies_correctly(): void
    {
        // Arrange
        $query = User::query();
        $teamId = '123';

        // Mock request with team_id parameter
        $this->instance(
            Request::class,
            Mockery::mock(Request::class, function (MockInterface $mock) use ($teamId): void {
                $mock->shouldReceive('query')
                    ->with('team_id')
                    ->andReturn($teamId);
            })
        );

        // Act
        $filter = new TeamIdFilter;
        $result = $this->invokeMethod($filter, 'applyFilter', [$query, $teamId]);

        // Assert
        $this->assertInstanceOf(Builder::class, $result);
        $this->assertStringContainsString(
            'where "team_id" =',
            $result->toSql()
        );
    }

    public function test_role_filter_applies_correctly(): void
    {
        // Arrange
        $query = User::query();
        $role = 'admin';

        // Mock request with role parameter
        $this->instance(
            Request::class,
            Mockery::mock(Request::class, function (MockInterface $mock) use ($role): void {
                $mock->shouldReceive('query')
                    ->with('role')
                    ->andReturn($role);
            })
        );

        // Act
        $filter = new RoleFilter;
        $result = $this->invokeMethod($filter, 'applyFilter', [$query, $role]);

        // Assert
        $this->assertInstanceOf(Builder::class, $result);
        $this->assertStringContainsString(
            'exists',
            $result->toSql()
        );
        $this->assertStringContainsString(
            'roles',
            $result->toSql()
        );
    }
}
