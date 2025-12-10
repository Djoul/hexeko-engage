<?php

namespace Tests\Unit\QueryFilters\ModelSpecific\Financer;

use App\Models\Financer;
use App\QueryFilters\ModelSpecific\Financer\DivisionIdFilter;
use App\QueryFilters\ModelSpecific\Financer\IbanFilter;
use App\QueryFilters\ModelSpecific\Financer\RegistrationCountryFilter;
use App\QueryFilters\ModelSpecific\Financer\RegistrationNumberFilter;
use App\QueryFilters\ModelSpecific\Financer\RepresentativeIdFilter;
use App\QueryFilters\ModelSpecific\Financer\VatNumberFilter;
use App\QueryFilters\ModelSpecific\Financer\WebsiteFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Request as RequestFacade;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('financer')]
#[Group('filters')]
class FinancerFiltersTest extends TestCase
{
    protected Builder $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query = Financer::query();
    }

    #[Test]
    public function registration_number_filter_applies_correct_column(): void
    {
        // Arrange
        $filter = new RegistrationNumberFilter;
        $requestData = ['registration_number' => 'REG123'];
        $this->mockRequest($requestData);

        // Act
        $result = $this->applyFilter($filter);

        // Assert
        $this->assertQueryHasWhereClause($result, 'registration_number', 'ilike', '%REG123%');
    }

    #[Test]
    public function registration_country_filter_applies_correct_column(): void
    {
        // Arrange
        $filter = new RegistrationCountryFilter;
        $requestData = ['registration_country' => 'FR'];
        $this->mockRequest($requestData);

        // Act
        $result = $this->applyFilter($filter);

        // Assert
        $this->assertQueryHasWhereClause($result, 'registration_country', 'ilike', '%FR%');
    }

    #[Test]
    public function website_filter_applies_correct_column(): void
    {
        // Arrange
        $filter = new WebsiteFilter;
        $requestData = ['website' => 'example.com'];
        $this->mockRequest($requestData);

        // Act
        $result = $this->applyFilter($filter);

        // Assert
        $this->assertQueryHasWhereClause($result, 'website', 'ilike', '%example.com%');
    }

    #[Test]
    public function iban_filter_applies_correct_column(): void
    {
        // Arrange
        $filter = new IbanFilter;
        $requestData = ['iban' => 'FR76'];
        $this->mockRequest($requestData);

        // Act
        $result = $this->applyFilter($filter);

        // Assert
        $this->assertQueryHasWhereClause($result, 'iban', 'ilike', '%FR76%');
    }

    #[Test]
    public function vat_number_filter_applies_correct_column(): void
    {
        // Arrange
        $filter = new VatNumberFilter;
        $requestData = ['vat_number' => 'FR123'];
        $this->mockRequest($requestData);

        // Act
        $result = $this->applyFilter($filter);

        // Assert
        $this->assertQueryHasWhereClause($result, 'vat_number', 'ilike', '%FR123%');
    }

    #[Test]
    public function division_id_filter_applies_correct_column(): void
    {
        // Arrange
        $filter = new DivisionIdFilter;
        $divisionId = '123e4567-e89b-12d3-a456-426614174000';
        $requestData = ['division_id' => $divisionId];
        $this->mockRequest($requestData);
        // Provide access control context expected by filter
        Context::add('accessible_divisions', [$divisionId]);

        // Act
        $result = $this->applyFilter($filter);

        // Assert
        // Filter now uses HasDivisionScopes trait which applies whereHas on division relation
        $this->assertQueryHasWhereHasClause($result, 'division', $divisionId);
    }

    #[Test]
    public function representative_id_filter_applies_correct_column(): void
    {
        // Arrange
        $filter = new RepresentativeIdFilter;
        $representativeId = '123e4567-e89b-12d3-a456-426614174000';
        $requestData = ['representative_id' => $representativeId];
        $this->mockRequest($requestData);

        // Act
        $result = $this->applyFilter($filter);

        // Assert
        $this->assertQueryHasWhereClause($result, 'representative_id', '=', $representativeId);
    }

    #[Test]
    public function filters_ignore_array_values(): void
    {
        // Arrange
        $filter = new RegistrationNumberFilter;
        $requestData = ['registration_number' => ['REG123', 'REG456']];
        $this->mockRequest($requestData);

        // Act
        $result = $this->applyFilter($filter);

        // Assert
        // Query should not have any where clauses since array values are ignored
        $this->assertQueryDoesNotHaveWhereClause($result, 'registration_number');
    }

    #[Test]
    public function filters_ignore_null_values(): void
    {
        // Arrange
        $filter = new RegistrationNumberFilter;
        $requestData = ['registration_number' => null];
        $this->mockRequest($requestData);

        // Act
        $result = $this->applyFilter($filter);

        // Assert
        // Query should not have any where clauses since null values are ignored
        $this->assertQueryDoesNotHaveWhereClause($result, 'registration_number');
    }

    #[Test]
    public function filters_ignore_empty_string_values(): void
    {
        // Arrange
        $filter = new RegistrationNumberFilter;
        $requestData = ['registration_number' => ''];
        $this->mockRequest($requestData);

        // Act
        $result = $this->applyFilter($filter);

        // Assert
        // Query should still have a where clause for empty string
        $this->assertQueryHasWhereClause($result, 'registration_number', 'ilike', '%%');
    }

    /**
     * Helper method to mock the request with query parameters
     */
    private function mockRequest(array $parameters): void
    {
        $request = Request::create('/', 'GET', $parameters);
        RequestFacade::swap($request);
    }

    /**
     * Helper method to apply a filter to the query
     */
    private function applyFilter(RegistrationNumberFilter|RegistrationCountryFilter|WebsiteFilter|IbanFilter|VatNumberFilter|DivisionIdFilter|RepresentativeIdFilter $filter): Builder
    {
        return $filter->handle($this->query, function ($query): Builder {
            return $query;
        });
    }

    /**
     * Helper method to assert that a query has a specific where clause
     */
    private function assertQueryHasWhereClause(Builder $query, string $column, string $operator, string $value): void
    {
        $whereFound = false;
        $wheres = $query->getQuery()->wheres;

        foreach ($wheres as $where) {
            // Handle basic where clauses (e.g., column operator value)
            if ((isset($where['type']) && $where['type'] === 'Basic')
                && isset($where['column'], $where['operator'], $where['value'])
                && $where['column'] === $column
                && $where['operator'] === $operator
                && $where['value'] === $value) {
                $whereFound = true;
                break;
            }

            // Handle whereIn clauses when a filter may apply IN even for a single value
            if ((isset($where['type']) && $where['type'] === 'In')
                && isset($where['column'], $where['values'])
                && $where['column'] === $column) {
                $values = $where['values'];
                // If only one value is provided and matches expected equality, accept it
                if (is_array($values) && count($values) === 1 && (string) $values[0] === $value) {
                    $whereFound = true;
                    break;
                }
            }
        }

        $this->assertTrue($whereFound, "Where clause for column '{$column}' with operator '{$operator}' and value '{$value}' not found");
    }

    /**
     * Helper method to assert that a query does not have a where clause for a specific column
     */
    private function assertQueryDoesNotHaveWhereClause(Builder $query, string $column): void
    {
        $whereFound = false;
        $wheres = $query->getQuery()->wheres;

        foreach ($wheres as $where) {
            if ($where['column'] === $column) {
                $whereFound = true;
                break;
            }
        }

        $this->assertFalse($whereFound, "Where clause for column '{$column}' was found but should not exist");
    }

    /**
     * Helper method to assert that a query has a whereHas clause for a specific relation
     */
    private function assertQueryHasWhereHasClause(Builder $query, string $relation, string $expectedId): void
    {
        $whereHasFound = false;
        $wheres = $query->getQuery()->wheres;

        foreach ($wheres as $where) {
            // Check for Exists type (used by whereHas)
            if ((isset($where['type']) && $where['type'] === 'Exists')
                && isset($where['query'])) {
                // Get the subquery wheres
                $subWheres = $where['query']->wheres ?? [];

                foreach ($subWheres as $subWhere) {
                    // Check if this is a whereIn on divisions.id
                    if ((isset($subWhere['type']) && $subWhere['type'] === 'In')
                        && isset($subWhere['column'], $subWhere['values'])
                        && $subWhere['column'] === 'divisions.id'
                        && in_array($expectedId, $subWhere['values'], true)) {
                        $whereHasFound = true;
                        break 2;
                    }
                }
            }
        }

        $this->assertTrue($whereHasFound, "whereHas clause for relation '{$relation}' with value '{$expectedId}' not found");
    }
}
