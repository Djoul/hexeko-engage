<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\InternalCommunication\Pipeline;

use App;
use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Models\Article;
use App\Models\Financer;
use App\Pipelines\SortApplier;
use Context;
use DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\ProtectedRouteTestCase;

#[Group('unit')]
#[Group('internal-communication')]
#[Group('article')]
class ArticleSortApplierTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    /**
     * @var Article[]
     */
    private array $articles = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create financer and set Context for HasFinancer global scope
        $financer = Financer::factory()->create();
        Context::add('financer_id', $financer->id);

        DB::table('int_communication_rh_articles')->delete();
        // Create articles with different titles and dates for testing sorting
        $this->articles[] = resolve(ArticleFactory::class)->for($financer, 'financer')->withTranslations([
            App::currentLocale() => [
                'title' => 'Alpha',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph', 'content' => [['text' => 'Lorem ipsum dolor sit amet.']]],
                    ],
                ],
                'status' => 'draft',
                'published_at' => now()->subDays(2),
            ],
        ])->create([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(1),
        ]);

        $this->articles[] = resolve(ArticleFactory::class)->for($financer, 'financer')->withTranslations([
            App::currentLocale() => [
                'title' => 'Bravo',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph', 'content' => [['text' => 'Lorem ipsum dolor sit amet.']]],
                    ],
                ],
                'status' => 'published',
                'published_at' => now()->subDay(),
            ],
        ])->create([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->articles[] = resolve(ArticleFactory::class)->for($financer, 'financer')->withTranslations([
            App::currentLocale() => [
                'title' => 'Charlie',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph', 'content' => [['text' => 'Lorem ipsum dolor sit amet.']]],
                    ],
                ],
                'status' => 'pending',
                'published_at' => now(),
            ],
        ])->create([
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }

    #[Test]
    public function it_sorts_by_title_ascending(): void
    {
        // Set up request parameters
        request()->merge(['order-by' => 'translations.title']);

        // Apply sorting
        $query = Article::query();
        $sortedQuery = SortApplier::apply(
            $query,
            Article::$sortable,
            Article::$defaultSortField,
            Article::$defaultSortDirection
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct
        $this->assertCount(3, $results);
        $this->assertEquals('Alpha', $results[0]->translation()->title);
        $this->assertEquals('Bravo', $results[1]->translation()->title);
        $this->assertEquals('Charlie', $results[2]->translation()->title);
    }

    #[Test]
    public function it_sorts_by_title_descending(): void
    {
        // Set up request parameters
        request()->merge(['order-by-desc' => 'translations.title']);

        // Apply sorting
        $query = Article::query();
        $sortedQuery = SortApplier::apply(
            $query,
            Article::$sortable,
            Article::$defaultSortField,
            Article::$defaultSortDirection
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct
        $this->assertCount(3, $results);
        $this->assertEquals('Charlie', $results[0]->translation()->title);
        $this->assertEquals('Bravo', $results[1]->translation()->title);
        $this->assertEquals('Alpha', $results[2]->translation()->title);
    }

    #[Test]
    public function it_sorts_by_published_at_ascending(): void
    {
        // Set up request parameters
        request()->merge(['order-by' => 'translations.published_at']);

        // Apply sorting
        $query = Article::query();
        $sortedQuery = SortApplier::apply(
            $query,
            Article::$sortable,
            Article::$defaultSortField,
            Article::$defaultSortDirection
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct
        $this->assertCount(3, $results);
        $this->assertEquals('Alpha', $results[0]->translation()->title);
        $this->assertEquals('Bravo', $results[1]->translation()->title);
        $this->assertEquals('Charlie', $results[2]->translation()->title);
    }

    #[Test]
    public function it_sorts_by_published_at_descending(): void
    {
        // Set up request parameters
        request()->merge(['order-by-desc' => 'translations.published_at']);

        // Apply sorting
        $query = Article::query();
        $sortedQuery = SortApplier::apply(
            $query,
            Article::$sortable,
            Article::$defaultSortField,
            Article::$defaultSortDirection
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct
        $this->assertCount(3, $results);
        $this->assertEquals('Charlie', $results[0]->translation()->title);
        $this->assertEquals('Bravo', $results[1]->translation()->title);
        $this->assertEquals('Alpha', $results[2]->translation()->title);
    }

    #[Test]
    public function it_sorts_by_created_at_ascending(): void
    {
        // Set up request parameters
        request()->merge(['order-by' => 'created_at']);

        // Apply sorting
        $query = Article::query();
        $sortedQuery = SortApplier::apply(
            $query,
            Article::$sortable,
            Article::$defaultSortField,
            Article::$defaultSortDirection
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct
        $this->assertCount(3, $results);
        $this->assertEquals('Alpha', $results[0]->translation()->title);
        $this->assertEquals('Bravo', $results[1]->translation()->title);
        $this->assertEquals('Charlie', $results[2]->translation()->title);
    }

    #[Test]
    public function it_sorts_by_updated_at_descending(): void
    {
        // Set up request parameters
        request()->merge(['order-by-desc' => 'updated_at']);

        // Apply sorting
        $query = Article::query();
        $sortedQuery = SortApplier::apply(
            $query,
            Article::$sortable,
            Article::$defaultSortField,
            Article::$defaultSortDirection
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct
        $this->assertCount(3, $results);
        $this->assertEquals('Charlie', $results[0]->translation()->title);
        $this->assertEquals('Alpha', $results[1]->translation()->title);
        $this->assertEquals('Bravo', $results[2]->translation()->title);
    }

    #[Test]
    public function it_prioritizes_order_by_desc_when_both_params_are_present(): void
    {
        // Set up request parameters
        request()->merge([
            'order-by' => 'translations.title',
            'order-by-desc' => 'translations.published_at',
        ]);

        // Apply sorting - filter to only get our test articles
        $query = Article::whereIn('id', array_map(fn (Article $a) => $a->id, $this->articles));
        $sortedQuery = SortApplier::apply(
            $query,
            Article::$sortable,
            Article::$defaultSortField,
            Article::$defaultSortDirection
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct (should follow order-by-desc)
        $this->assertCount(3, $results);
        $this->assertEquals('Charlie', $results[0]->translation()->title);
        $this->assertEquals('Bravo', $results[1]->translation()->title);
        $this->assertEquals('Alpha', $results[2]->translation()->title);
    }

    #[Test]
    public function it_throws_exception_when_invalid_field_provided(): void
    {
        // Set up request parameters
        request()->merge(['order-by' => 'invalid_field']);

        // Should throw 422 exception for invalid field
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Invalid sort field: invalid_field');

        // Apply sorting - filter to only get our test articles
        $query = Article::whereIn('id', array_map(fn (Article $a) => $a->id, $this->articles));
        SortApplier::apply(
            $query,
            Article::$sortable,
            Article::$defaultSortField,
            Article::$defaultSortDirection
        );
    }

    #[Test]
    public function it_falls_back_to_default_sorting_when_no_params(): void
    {
        // Apply sorting without any request parameters - filter to only get our test articles
        $query = Article::whereIn('id', array_map(fn (Article $a) => $a->id, $this->articles));
        $sortedQuery = SortApplier::apply(
            $query,
            Article::$sortable,
            Article::$defaultSortField,
            Article::$defaultSortDirection
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert default order is used (published_at desc)
        $this->assertCount(3, $results);
        $this->assertEquals('Charlie', $results[0]->translation()->title);
        $this->assertEquals('Bravo', $results[1]->translation()->title);
        $this->assertEquals('Alpha', $results[2]->translation()->title);
    }

    #[Test]
    public function it_can_sort_by_translation_status(): void
    {
        // Set up request parameters
        request()->merge(['order-by' => 'translations.status']);

        // Apply sorting - filter to only get our test articles
        $query = Article::whereIn('id', array_map(fn (Article $a) => $a->id, $this->articles));
        $sortedQuery = SortApplier::apply(
            $query,
            Article::$sortable,
            Article::$defaultSortField,
            Article::$defaultSortDirection
        );

        // Get results
        $results = $sortedQuery->get();

        // Assert order is correct (draft, pending, published)
        $this->assertCount(3, $results);
        $this->assertEquals('draft', $results[0]->translation()->status);
        $this->assertEquals('pending', $results[1]->translation()->status);
        $this->assertEquals('published', $results[2]->translation()->status);
    }
}
