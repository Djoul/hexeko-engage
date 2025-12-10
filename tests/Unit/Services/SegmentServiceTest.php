<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Department;
use App\Models\Financer;
use App\Models\User;
use App\Services\SegmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('segment')]
class SegmentServiceTest extends TestCase
{
    use DatabaseTransactions;

    private SegmentService $service;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SegmentService;
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
    public function it_returns_available_filters(): void
    {
        // Arrange

        // Act
        $filters = $this->service->getAvailableFilters();

        // Assert
        $this->assertArrayHasKey('email', $filters);
        $this->assertEquals('Email', $filters['email']['label']);
        $this->assertContains('contains', $filters['email']['operators']);
    }

    #[Test]
    public function it_returns_available_operators(): void
    {
        // Arrange

        // Act
        $operators = $this->service->getAvailableOperators();

        // Assert
        $this->assertArrayHasKey('equals', $operators);
        $this->assertEquals('Is equal to', $operators['equals']);
    }

    #[Test]
    public function it_applies_direct_filters_to_user_query(): void
    {
        // Arrange
        $matchingUser = User::factory()->create([
            'email' => 'alice@example.com',
        ]);
        $nonMatchingUser = User::factory()->create([
            'email' => 'bob@domain.com',
        ]);

        $query = User::query();

        // Act
        $resultIds = $this->service
            ->applyFiltersToQuery($query, [
                [
                    'type' => 'email',
                    'operator' => 'contains',
                    'value' => 'example.com',
                ],
            ])
            ->pluck('id')
            ->all();

        // Assert
        $this->assertContains($matchingUser->id, $resultIds);
        $this->assertNotContains($nonMatchingUser->id, $resultIds);
    }

    #[Test]
    public function it_applies_relation_filters_to_user_query(): void
    {
        // Arrange
        $department = Department::factory()->create(['financer_id' => $this->financer->id]);
        $matchingUser = User::factory()->create();
        $nonMatchingUser = User::factory()->create();

        $matchingUser->departments()->attach($department->id);

        $query = User::query();

        // Act
        $resultIds = $this->service
            ->applyFiltersToQuery($query, [
                [
                    'type' => 'departments',
                    'operator' => 'in',
                    'value' => (string) $department->id,
                ],
            ])
            ->pluck('id')
            ->all();

        // Assert
        $this->assertContains($matchingUser->id, $resultIds);
        $this->assertNotContains($nonMatchingUser->id, $resultIds);
    }

    #[Test]
    public function it_groups_filters_by_condition(): void
    {
        // Arrange
        $filters = [
            ['type' => 'email', 'operator' => 'contains', 'value' => 'example.com', 'condition' => 'AND'],
            ['type' => 'email', 'operator' => 'contains', 'value' => 'internal', 'condition' => 'OR'],
            ['type' => 'email', 'operator' => 'contains', 'value' => 'vip', 'condition' => 'AND'],
        ];

        // Act
        $groups = $this->getPrivateMethodResult('groupFiltersByCondition', $filters);

        // Assert
        $this->assertCount(2, $groups);
        $this->assertSame('example.com', $groups[0][0]['value']);
        $this->assertSame('vip', $groups[1][0]['value']);
    }

    #[Test]
    public function it_validates_filters_and_returns_errors(): void
    {
        // Arrange
        $filters = [
            [
                'type' => 'unknown',
                'operator' => 'equals',
                'value' => 'value',
            ],
            [
                'type' => 'email',
                'operator' => 'between',
                'value' => ['min' => 1],
            ],
        ];

        // Act
        $errors = $this->service->validateFilters($filters);

        // Assert
        $this->assertCount(3, $errors);
        $this->assertStringContainsString("Filter 'unknown' is invalid", $errors[0]);
        $this->assertStringContainsString("Operator 'between' is not valid", $errors[1]);
        $this->assertStringContainsString("'between' requires an array of 2 values", $errors[2]);
    }

    #[Test]
    public function it_parses_between_values_from_array_and_string(): void
    {
        // Arrange
        $arrayValue = ['min' => 1, 'max' => 5];
        $stringValue = '10,20';

        // Act
        $parsedArray = $this->callProtectedParseBetweenValue($arrayValue);
        $parsedString = $this->callProtectedParseBetweenValue($stringValue);

        // Assert
        $this->assertSame([1, 5], $parsedArray);
        $this->assertSame(['10', '20'], $parsedString);
    }

    private function getPrivateMethodResult(string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass($this->service);
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection->invoke($this->service, $arguments);
    }

    private function callProtectedParseBetweenValue(array|string $value): array
    {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('parseBetweenValue');
        $method->setAccessible(true);

        return $method->invoke($this->service, $value);
    }
}
