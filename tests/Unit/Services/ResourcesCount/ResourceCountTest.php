<?php

namespace Tests\Unit\Services\ResourcesCount;

use App\Integrations\HRTools\Models\Link;
use App\Integrations\InternalCommunication\Models\Article;
use App\Models\Financer;
use App\Models\Integration;
use App\Services\Integration\ResourceCountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('integration')]
class ResourceCountTest extends TestCase
{
    use DatabaseTransactions;

    private ResourceCountService $service;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ResourceCountService;

        // Create test financer with division
        $division = ModelFactory::createDivision();
        $this->financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Test Financer',
        ]);
    }

    #[Test]
    public function it_executes_resources_count_query_for_internal_links(): void
    {
        // Create test data
        Link::count();
        Link::factory()->count(3)->create([
            'financer_id' => $this->financer->id,
            'name' => 'Test Link',
            'url' => 'https://example.com',
        ]);
        // Create another financer for different links
        $otherFinancer = ModelFactory::createFinancer([
            'name' => 'Other Financer',
        ]);
        Link::factory()->count(2)->create([
            'financer_id' => $otherFinancer->id,
            'name' => 'Other Link',
            'url' => 'https://example.org',
        ]);

        // Create integration with query
        $integration = Integration::factory()->create([
            'name' => 'Internal Links',
            'resources_count_query' => 'SELECT COUNT(*) as count FROM int_outils_rh_links WHERE financer_id = :financer_id AND deleted_at IS NULL',
        ]);

        // Execute query
        $count = $this->service->executeCountQuery(
            $integration->resources_count_query,
            ['financer_id' => $this->financer->id]
        );

        $this->assertEquals(3, $count);
    }

    #[Test]
    public function it_executes_resources_count_query_for_internal_communication(): void
    {
        // Create test articles with translations
        Article::count();

        // Articles for our financer with published translations
        Article::factory()->count(2)->create(['financer_id' => $this->financer->id])
            ->each(function ($article): void {
                $article->translations()->create([
                    'language' => 'fr',
                    'status' => 'published',
                    'title' => 'Test Article',
                    'content' => 'Content',
                ]);
            });

        // Articles for our financer with draft translations (should not count)
        Article::factory()->create(['financer_id' => $this->financer->id])
            ->translations()->create([
                'language' => 'fr',
                'status' => 'draft',
                'title' => 'Draft Article',
                'content' => 'Draft Content',
            ]);

        // Create integration with query
        $integration = Integration::factory()->create([
            'name' => 'Internal Communication',
            'resources_count_query' => "
                SELECT COUNT(DISTINCT a.id) as count 
                FROM int_communication_rh_articles a
                INNER JOIN int_communication_rh_article_translations at ON a.id = at.article_id
                WHERE a.financer_id = :financer_id 
                AND at.status = 'published'
                AND at.language = :language
                AND a.deleted_at IS NULL
            ",
        ]);

        // Execute query
        $count = $this->service->executeCountQuery(
            $integration->resources_count_query,
            [
                'financer_id' => $this->financer->id,
                'language' => 'fr',
            ]
        );

        $this->assertEquals(2, $count);
    }

    #[Test]
    public function it_executes_resources_count_query_for_amilon(): void
    {
        // Create integration with query
        $integration = Integration::factory()->create([
            'name' => 'Amilon',
            'resources_count_query' => 'SELECT COUNT(*) as count FROM int_vouchers_amilon_merchants',
        ]);

        // Execute query (will return 0 if table doesn't exist or is empty)
        $count = $this->service->executeCountQuery(
            $integration->resources_count_query,
            []
        );

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    #[Test]
    public function it_returns_hardcoded_value_for_wellwo(): void
    {
        // Create integration with hardcoded value
        $integration = Integration::factory()->create([
            'name' => 'WellWo',
            'resources_count_query' => 'SELECT 20 as count',
        ]);

        // Execute query
        $count = $this->service->executeCountQuery(
            $integration->resources_count_query,
            []
        );

        $this->assertEquals(20, $count);
    }

    #[Test]
    public function it_handles_null_query_gracefully(): void
    {
        // Create integration without query
        $integration = Integration::factory()->create([
            'name' => 'Test Integration',
            'resources_count_query' => null,
        ]);

        // Execute query
        $count = $this->service->executeCountQuery(
            $integration->resources_count_query,
            []
        );

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_handles_invalid_query_gracefully(): void
    {
        // Create integration with invalid query
        $integration = Integration::factory()->create([
            'name' => 'Test Integration',
            'resources_count_query' => 'INVALID SQL QUERY',
        ]);

        // Execute query
        $count = $this->service->executeCountQuery(
            $integration->resources_count_query,
            []
        );

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_supports_dynamic_parameters(): void
    {
        // Create test links
        Link::factory()->count(5)->create([
            'financer_id' => $this->financer->id,
            'name' => 'FR Link',
            'url' => 'https://example.fr',
        ]);
        Link::factory()->count(3)->create([
            'financer_id' => $this->financer->id,
            'name' => 'US Link',
            'url' => 'https://example.us',
        ]);

        // Create integration with parameterized query
        $integration = Integration::factory()->create([
            'name' => 'Internal Links',
            'resources_count_query' => '
                SELECT COUNT(*) as count 
                FROM int_outils_rh_links 
                WHERE financer_id = :financer_id 
                AND deleted_at IS NULL
            ',
        ]);

        // Execute query with parameters
        $count = $this->service->executeCountQuery(
            $integration->resources_count_query,
            [
                'financer_id' => $this->financer->id,
            ]
        );

        $this->assertEquals(8, $count); // 5 + 3 = 8 total links for this financer
    }
}
