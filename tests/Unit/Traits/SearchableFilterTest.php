<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Contracts\Searchable;
use App\Traits\SearchableFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('search')]
#[Group('filter')]
class SearchableFilterTest extends TestCase
{
    public Model $mockModel;

    public Builder $mockBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockModel = Mockery::mock(Model::class.','.Searchable::class);
        $this->mockBuilder = Mockery::mock(Builder::class);

        // Set up Cache facade mock
        Cache::shouldReceive('tags')->andReturnSelf();
        Cache::shouldReceive('flush')->andReturn(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_adds_global_search_filter_scope_to_model(): void
    {
        $filter = new class
        {
            use SearchableFilter;

            protected function getModel(): Model
            {
                return new class extends Model implements Searchable
                {
                    public function getSearchableFields(): array
                    {
                        return ['name', 'email'];
                    }

                    public function getSearchableRelations(): array
                    {
                        return [];
                    }
                };
            }
        };

        $this->assertTrue(method_exists($filter, 'handle'));
    }

    #[Test]
    public function it_applies_global_search_filter_through_pipeline(): void
    {
        $searchTerm = 'test search';
        request()->merge(['search' => $searchTerm]);

        $this->mockModel->shouldReceive('getSearchableFields')->andReturn(['name', 'email']);
        $this->mockModel->shouldReceive('getSearchableRelations')->andReturn([]);
        $this->mockModel->shouldReceive('getCacheTag')->andReturn('test-model');

        $this->mockBuilder->shouldReceive('getModel')->andReturn($this->mockModel);
        $this->mockBuilder->shouldReceive('where')->once()->with(Mockery::type('callable'))->andReturnSelf();

        // Cache expectations are set up in setUp() method

        $testCase = $this;
        $filter = new class($testCase)
        {
            use SearchableFilter;

            private $testCase;

            public function __construct($testCase)
            {
                $this->testCase = $testCase;
            }

            protected function getModel(): Model
            {
                return $this->testCase->mockModel;
            }
        };

        $next = fn ($builder) => $builder;
        $result = $filter->handle($this->mockBuilder, $next);

        $this->assertSame($this->mockBuilder, $result);
    }

    #[Test]
    public function it_validates_minimum_search_length(): void
    {
        request()->merge(['search' => 'a']); // Only 1 character

        $this->mockBuilder->shouldReceive('getModel')->andReturn($this->mockModel);

        // Should not apply any filters when search term is too short
        $this->mockBuilder->shouldNotReceive('where');

        $testCase = $this;
        $filter = new class($testCase)
        {
            use SearchableFilter;

            private $testCase;

            public function __construct($testCase)
            {
                $this->testCase = $testCase;
            }

            protected function getModel(): Model
            {
                return $this->testCase->mockModel;
            }
        };

        $next = fn ($builder) => $builder;
        $result = $filter->handle($this->mockBuilder, $next);

        $this->assertSame($this->mockBuilder, $result);
    }

    #[Test]
    public function it_returns_unmodified_query_when_no_search_term(): void
    {
        request()->merge(['search' => null]);

        $this->mockBuilder->shouldReceive('getModel')->andReturn($this->mockModel);
        $this->mockBuilder->shouldNotReceive('where');

        $testCase = $this;
        $filter = new class($testCase)
        {
            use SearchableFilter;

            private $testCase;

            public function __construct($testCase)
            {
                $this->testCase = $testCase;
            }

            protected function getModel(): Model
            {
                return $this->testCase->mockModel;
            }
        };

        $next = fn ($builder) => $builder;
        $result = $filter->handle($this->mockBuilder, $next);

        $this->assertSame($this->mockBuilder, $result);
    }

    #[Test]
    public function it_handles_relation_searches(): void
    {
        $searchTerm = 'test search';
        request()->merge(['search' => $searchTerm]);

        $this->mockModel->shouldReceive('getSearchableFields')->andReturn(['name']);
        $this->mockModel->shouldReceive('getSearchableRelations')->andReturn([
            'author' => ['first_name', 'last_name'],
            'category' => ['name'],
        ]);
        $this->mockModel->shouldReceive('getCacheTag')->andReturn('test-model');

        $this->mockBuilder->shouldReceive('getModel')->andReturn($this->mockModel);
        $this->mockBuilder->shouldReceive('where')->once()->with(Mockery::on(function ($callback): bool {
            // Test that the callback is a closure
            return is_callable($callback);
        }))->andReturnSelf();

        // Cache expectations are set up in setUp() method

        $testCase = $this;
        $filter = new class($testCase)
        {
            use SearchableFilter;

            private $testCase;

            public function __construct($testCase)
            {
                $this->testCase = $testCase;
            }

            protected function getModel(): Model
            {
                return $this->testCase->mockModel;
            }
        };

        $next = fn ($builder) => $builder;
        $result = $filter->handle($this->mockBuilder, $next);

        $this->assertSame($this->mockBuilder, $result);
    }
}
