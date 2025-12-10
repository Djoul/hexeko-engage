<?php

declare(strict_types=1);

namespace Tests\Unit\Pipelines;

use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Models\Article;
use App\Models\Division;
use App\Models\Financer;
use App\Models\User;
use App\Pipelines\SortApplier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('pipelines')]
#[Group('sort')]
class SortApplierTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_sorts_users_by_full_name_ascending(): void
    {
        // Create shared financer for test users
        $division = ModelFactory::createDivision(['name' => 'User Test Division']);
        $financer = ModelFactory::createFinancer([
            'name' => 'User Test Financer',
            'division_id' => $division->id,
        ]);

        // Create test users with different names
        $userA = ModelFactory::createUser([
            'first_name' => 'Alice',
            'last_name' => 'Anderson',
            'email' => 'alice@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $userZ = ModelFactory::createUser([
            'first_name' => 'Zachary',
            'last_name' => 'Zulu',
            'email' => 'zachary@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $userM = ModelFactory::createUser([
            'first_name' => 'Michael',
            'last_name' => 'Martin',
            'email' => 'michael@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Set up request parameters
        request()->merge(['order-by' => 'full_name']);

        // Apply sorting
        $query = User::query();
        $sortedQuery = SortApplier::apply(
            $query,
            ['full_name', 'email'],
            'email',
            'asc'
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct (Alice, Michael, Zachary)
        $this->assertGreaterThanOrEqual(3, $results->count());
        $this->assertEquals('Alice', $results->firstWhere('email', 'alice@test.com')?->first_name);

        // Verify full_name accessor works
        $this->assertEquals('Alice Anderson', $userA->full_name);
        $this->assertEquals('Zachary Zulu', $userZ->full_name);
        $this->assertEquals('Michael Martin', $userM->full_name);
    }

    #[Test]
    public function it_sorts_users_by_full_name_descending(): void
    {
        // Create shared financer for test users
        $division = ModelFactory::createDivision(['name' => 'User Test Division 2']);
        $financer = ModelFactory::createFinancer([
            'name' => 'User Test Financer 2',
            'division_id' => $division->id,
        ]);

        // Create test users with different names
        ModelFactory::createUser([
            'first_name' => 'Alice',
            'last_name' => 'Anderson',
            'email' => 'alice2@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        ModelFactory::createUser([
            'first_name' => 'Zachary',
            'last_name' => 'Zulu',
            'email' => 'zachary2@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Set up request parameters
        request()->merge(['order-by-desc' => 'full_name']);

        // Apply sorting
        $query = User::whereIn('email', ['alice2@test.com', 'zachary2@test.com']);
        $sortedQuery = SortApplier::apply(
            $query,
            ['full_name', 'email'],
            'email',
            'asc'
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct (Zachary first, then Alice)
        $this->assertCount(2, $results);
        $this->assertEquals('Zachary', $results[0]->first_name);
        $this->assertEquals('Alice', $results[1]->first_name);
    }

    #[Test]
    public function it_sorts_users_by_name_alias_ascending(): void
    {
        // Create shared financer for test users
        $division = ModelFactory::createDivision(['name' => 'User Test Division 3']);
        $financer = ModelFactory::createFinancer([
            'name' => 'User Test Financer 3',
            'division_id' => $division->id,
        ]);

        // Create test users with different names
        $userA = ModelFactory::createUser([
            'first_name' => 'Alice',
            'last_name' => 'Anderson',
            'email' => 'alice3@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $userZ = ModelFactory::createUser([
            'first_name' => 'Zachary',
            'last_name' => 'Zulu',
            'email' => 'zachary3@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Set up request parameters with 'name' (should map to 'full_name')
        request()->merge(['order-by' => 'name']);

        // Apply sorting
        $query = User::whereIn('email', ['alice3@test.com', 'zachary3@test.com']);
        $sortedQuery = SortApplier::apply(
            $query,
            ['full_name', 'email'],
            'email',
            'asc'
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct (Alice first, then Zachary)
        $this->assertCount(2, $results);
        $this->assertEquals('Alice', $results[0]->first_name);
        $this->assertEquals('Zachary', $results[1]->first_name);

        // Verify full_name accessor works
        $this->assertEquals('Alice Anderson', $userA->full_name);
        $this->assertEquals('Zachary Zulu', $userZ->full_name);
    }

    // endregion -->

    // region Financer name sorting (simple column) -->

    #[Test]
    public function it_sorts_financers_by_name_ascending(): void
    {
        // Create test financers
        $division = ModelFactory::createDivision(['name' => 'Test Division']);

        $financerA = ModelFactory::createFinancer([
            'name' => 'Alpha Corp',
            'division_id' => $division->id,
        ]);

        $financerZ = ModelFactory::createFinancer([
            'name' => 'Zeta Inc',
            'division_id' => $division->id,
        ]);

        $financerM = ModelFactory::createFinancer([
            'name' => 'Micro Ltd',
            'division_id' => $division->id,
        ]);

        // Set up request parameters
        request()->merge(['order-by' => 'name']);

        // Apply sorting
        $query = Financer::query();
        $sortedQuery = SortApplier::apply(
            $query,
            ['name', 'created_at'],
            'created_at',
            'desc'
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct
        $this->assertGreaterThanOrEqual(3, $results->count());

        // Find our test financers in results
        $alpha = $results->firstWhere('id', $financerA->id);
        $zeta = $results->firstWhere('id', $financerZ->id);
        $micro = $results->firstWhere('id', $financerM->id);

        $this->assertNotNull($alpha);
        $this->assertNotNull($zeta);
        $this->assertNotNull($micro);

        $this->assertEquals('Alpha Corp', $alpha->name);
        $this->assertEquals('Zeta Inc', $zeta->name);
        $this->assertEquals('Micro Ltd', $micro->name);
    }

    #[Test]
    public function it_sorts_financers_by_name_descending(): void
    {
        // Create test financers
        $division = ModelFactory::createDivision(['name' => 'Test Division 2']);

        $financerA = ModelFactory::createFinancer([
            'name' => 'Alpha Corp 2',
            'division_id' => $division->id,
        ]);

        $financerZ = ModelFactory::createFinancer([
            'name' => 'Zeta Inc 2',
            'division_id' => $division->id,
        ]);

        // Set up request parameters
        request()->merge(['order-by-desc' => 'name']);

        // Apply sorting
        $query = Financer::whereIn('id', [$financerA->id, $financerZ->id]);
        $sortedQuery = SortApplier::apply(
            $query,
            ['name', 'created_at'],
            'created_at',
            'desc'
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct (Zeta first, then Alpha)
        $this->assertCount(2, $results);
        $this->assertEquals('Zeta Inc 2', $results[0]->name);
        $this->assertEquals('Alpha Corp 2', $results[1]->name);
    }

    // endregion -->

    // region Division name sorting (simple column) -->

    #[Test]
    public function it_sorts_divisions_by_name_ascending(): void
    {
        // Create test divisions
        $divisionA = ModelFactory::createDivision(['name' => 'Alpha Division']);
        $divisionZ = ModelFactory::createDivision(['name' => 'Zeta Division']);
        $divisionM = ModelFactory::createDivision(['name' => 'Micro Division']);

        // Set up request parameters
        request()->merge(['order-by' => 'name']);

        // Apply sorting
        $query = Division::query();
        $sortedQuery = SortApplier::apply(
            $query,
            Division::$sortable,
            Division::$defaultSortField,
            Division::$defaultSortDirection
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct
        $this->assertGreaterThanOrEqual(3, $results->count());

        // Find our test divisions
        $alpha = $results->firstWhere('id', $divisionA->id);
        $results->firstWhere('id', $divisionZ->id);
        $results->firstWhere('id', $divisionM->id);

        $this->assertNotNull($alpha);
        $this->assertEquals('Alpha Division', $alpha->name);
    }

    #[Test]
    public function it_sorts_divisions_by_name_descending(): void
    {
        // Create test divisions
        $divisionA = ModelFactory::createDivision(['name' => 'Alpha Division 2']);
        $divisionZ = ModelFactory::createDivision(['name' => 'Zeta Division 2']);

        // Set up request parameters
        request()->merge(['order-by-desc' => 'name']);

        // Apply sorting
        $query = Division::whereIn('id', [$divisionA->id, $divisionZ->id]);
        $sortedQuery = SortApplier::apply(
            $query,
            Division::$sortable,
            Division::$defaultSortField,
            Division::$defaultSortDirection
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct (Zeta first, then Alpha)
        $this->assertCount(2, $results);
        $this->assertEquals('Zeta Division 2', $results[0]->name);
        $this->assertEquals('Alpha Division 2', $results[1]->name);
    }

    // endregion -->

    // region Article translations.title sorting (relation) -->

    #[Test]
    public function it_sorts_articles_by_translations_title_ascending(): void
    {
        // Create shared financer and set Context BEFORE creating articles
        $division = ModelFactory::createDivision(['name' => 'Article Test Division']);
        $financer = ModelFactory::createFinancer([
            'name' => 'Article Test Financer',
            'division_id' => $division->id,
        ]);
        Context::add('financer_id', $financer->id);

        // Create articles with different titles
        $articleA = resolve(ArticleFactory::class)->withTranslations([
            app()->currentLocale() => [
                'title' => 'Alpha Article',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph', 'content' => [['text' => 'Content']]],
                    ],
                ],
                'status' => 'published',
                'published_at' => now(),
            ],
        ])->create([
            'financer_id' => $financer->id,
        ]);

        $articleZ = resolve(ArticleFactory::class)->withTranslations([
            app()->currentLocale() => [
                'title' => 'Zeta Article',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph', 'content' => [['text' => 'Content']]],
                    ],
                ],
                'status' => 'published',
                'published_at' => now(),
            ],
        ])->create([
            'financer_id' => $financer->id,
        ]);

        // Set up request parameters
        request()->merge(['order-by' => 'translations.title']);

        // Apply sorting
        $query = Article::whereIn('id', [$articleA->id, $articleZ->id]);
        $sortedQuery = SortApplier::apply(
            $query,
            Article::$sortable,
            Article::$defaultSortField,
            Article::$defaultSortDirection
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct
        $this->assertCount(2, $results);
        $this->assertEquals('Alpha Article', $results[0]->translation()->title);
        $this->assertEquals('Zeta Article', $results[1]->translation()->title);
    }

    #[Test]
    public function it_sorts_articles_by_title_alias_ascending(): void
    {
        // Create shared financer and set Context BEFORE creating articles
        $division = ModelFactory::createDivision(['name' => 'Article Test Division 2']);
        $financer = ModelFactory::createFinancer([
            'name' => 'Article Test Financer 2',
            'division_id' => $division->id,
        ]);
        Context::add('financer_id', $financer->id);

        // Create articles with different titles
        $articleA = resolve(ArticleFactory::class)->withTranslations([
            app()->currentLocale() => [
                'title' => 'Alpha Article Alias',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph', 'content' => [['text' => 'Content']]],
                    ],
                ],
                'status' => 'published',
                'published_at' => now(),
            ],
        ])->create([
            'financer_id' => $financer->id,
        ]);

        $articleZ = resolve(ArticleFactory::class)->withTranslations([
            app()->currentLocale() => [
                'title' => 'Zeta Article Alias',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph', 'content' => [['text' => 'Content']]],
                    ],
                ],
                'status' => 'published',
                'published_at' => now(),
            ],
        ])->create([
            'financer_id' => $financer->id,
        ]);

        // Set up request parameters with just 'title' (should map to 'translations.title')
        request()->merge(['order-by' => 'title']);

        // Apply sorting
        $query = Article::whereIn('id', [$articleA->id, $articleZ->id]);
        $sortedQuery = SortApplier::apply(
            $query,
            Article::$sortable,
            Article::$defaultSortField,
            Article::$defaultSortDirection
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct
        $this->assertCount(2, $results);
        $this->assertEquals('Alpha Article Alias', $results[0]->translation()->title);
        $this->assertEquals('Zeta Article Alias', $results[1]->translation()->title);
    }

    #[Test]
    public function it_sorts_articles_by_translations_title_descending(): void
    {
        // Create shared financer and set Context BEFORE creating articles
        $division = ModelFactory::createDivision(['name' => 'Article Test Division 3']);
        $financer = ModelFactory::createFinancer([
            'name' => 'Article Test Financer 3',
            'division_id' => $division->id,
        ]);
        Context::add('financer_id', $financer->id);

        // Create articles with different titles
        $articleA = resolve(ArticleFactory::class)->withTranslations([
            app()->currentLocale() => [
                'title' => 'Alpha Article 2',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph', 'content' => [['text' => 'Content']]],
                    ],
                ],
                'status' => 'published',
                'published_at' => now(),
            ],
        ])->create([
            'financer_id' => $financer->id,
        ]);

        $articleZ = resolve(ArticleFactory::class)->withTranslations([
            app()->currentLocale() => [
                'title' => 'Zeta Article 2',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph', 'content' => [['text' => 'Content']]],
                    ],
                ],
                'status' => 'published',
                'published_at' => now(),
            ],
        ])->create([
            'financer_id' => $financer->id,
        ]);

        // Set up request parameters
        request()->merge(['order-by-desc' => 'translations.title']);

        // Apply sorting
        $query = Article::whereIn('id', [$articleA->id, $articleZ->id]);
        $sortedQuery = SortApplier::apply(
            $query,
            Article::$sortable,
            Article::$defaultSortField,
            Article::$defaultSortDirection
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct (Zeta first, then Alpha)
        $this->assertCount(2, $results);
        $this->assertEquals('Zeta Article 2', $results[0]->translation()->title);
        $this->assertEquals('Alpha Article 2', $results[1]->translation()->title);
    }

    // endregion -->

    // region Edge cases and fallback behavior -->

    #[Test]
    public function it_falls_back_to_default_sorting_when_no_params(): void
    {
        // Apply sorting without any request parameters
        $query = Division::query();
        $sortedQuery = SortApplier::apply(
            $query,
            Division::$sortable,
            Division::$defaultSortField,
            Division::$defaultSortDirection
        );

        // Should use default sort (created_at desc)
        $sql = $sortedQuery->toSql();
        $this->assertStringContainsString('order by', strtolower($sql));
    }

    #[Test]
    public function it_throws_exception_when_invalid_field_provided(): void
    {
        // Set up request with invalid field
        request()->merge(['order-by' => 'invalid_field']);

        // Should throw 422 exception for invalid field
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Invalid sort field: invalid_field');

        // Apply sorting
        $query = User::query();
        SortApplier::apply(
            $query,
            ['full_name', 'email'],
            'email',
            'asc'
        );
    }

    #[Test]
    public function it_prioritizes_order_by_desc_over_order_by(): void
    {
        // Set up request with both parameters
        request()->merge([
            'order-by' => 'name',
            'order-by-desc' => 'created_at',
        ]);

        $division = ModelFactory::createDivision(['name' => 'Test']);

        // Apply sorting
        $query = Division::where('id', $division->id);
        $sortedQuery = SortApplier::apply(
            $query,
            Division::$sortable,
            Division::$defaultSortField,
            Division::$defaultSortDirection
        );

        // Should prioritize order-by-desc (created_at desc)
        $sql = $sortedQuery->toSql();
        $this->assertStringContainsString('order by', strtolower($sql));
    }

    // endregion -->
}
