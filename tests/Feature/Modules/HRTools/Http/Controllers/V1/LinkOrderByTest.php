<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\HRTools\Http\Controllers\V1;

use App\Integrations\HRTools\Database\factories\LinkFactory;
use Context;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['int_hr_tools_links', 'int_hr_tools_link_categories'], scope: 'test')]
#[Group('hr-tools')]
class LinkOrderByTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clean up Laravel Context to ensure test isolation
        Context::flush();
    }

    protected function tearDown(): void
    {
        // Clean up Laravel Context after each test
        Context::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_sorts_by_name_ascending(): void
    {
        $response = $this->getLinks(['order-by' => 'name']);
        $response->assertOk();
        // Assert using raw translations to avoid locale-dependent empty strings
        $names = $this->pluckLocaleFromName($response->json('data'), 'nl-BE');
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $names);
    }

    #[Test]
    public function it_sorts_by_name_descending(): void
    {
        $response = $this->getLinks(['order-by-desc' => 'name']);

        $response->assertOk();
        $names = $this->pluckLocaleFromName($response->json('data'), 'fr-BE');
        $this->assertSame(['Charlie (fr-BE)', 'Bravo (fr-BE)', 'Alpha (fr-BE)'], $names);
    }

    #[Test]
    public function it_sorts_by_position_ascending(): void
    {
        $response = $this->getLinks(['order-by' => 'position']);
        $response->assertOk();
        $names = $this->pluckLocaleFromName($response->json('data'), 'nl-BE');
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $names);
    }

    #[Test]
    public function it_sorts_by_position_descending(): void
    {
        $response = $this->getLinks(['order-by-desc' => 'position']);
        $response->assertOk();
        $names = $this->pluckLocaleFromName($response->json('data'), 'nl-BE');
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $names);
    }

    #[Test]
    public function it_prioritizes_order_by_desc_when_both_params_are_present(): void
    {
        $response = $this->getLinks(['order-by' => 'name', 'order-by-desc' => 'created_at']);
        $response->assertOk();
        $names = array_column($response->json('data'), 'name_raw');
        $this->assertSame(
            [
                ['fr-BE' => 'Charlie (fr-BE)', 'nl-BE' => 'Charlie'],
                ['fr-BE' => 'Bravo (fr-BE)', 'nl-BE' => 'Bravo'],
                ['fr-BE' => 'Alpha (fr-BE)', 'nl-BE' => 'Alpha'],
            ],
            $names
        );
    }

    #[Test]
    public function it_returns_422_when_invalid_field_provided(): void
    {
        $response = $this->getLinks(['order-by' => 'invalid_field']);
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Invalid sort field: invalid_field']);
    }

    #[Test]
    public function it_falls_back_to_default_sorting_when_no_params(): void
    {
        $response = $this->getLinks();
        $response->assertOk();
        $names = array_column($response->json('data'), 'name_raw');
        // Default is position asc
        $this->assertSame(
            [
                ['fr-BE' => 'Alpha (fr-BE)', 'nl-BE' => 'Alpha'],
                ['fr-BE' => 'Bravo (fr-BE)', 'nl-BE' => 'Bravo'],
                ['fr-BE' => 'Charlie (fr-BE)', 'nl-BE' => 'Charlie'],
            ],
            $names
        );
    }

    private function getLinks(array $params = []): TestResponse
    {
        $user = $this->createAuthUser(
            withContext: true,
            returnDetails: true
        );

        // Set user locale
        $user->locale = 'fr-BE';
        $user->save();

        // Create links with the same financer as the user
        $financerId = $this->currentFinancer->id;

        // Set financer_id in context for test-specific needs
        Context::add('financer_id', $financerId);

        resolve(LinkFactory::class)->create([
            'name' => ['fr-BE' => 'Alpha (fr-BE)', 'nl-BE' => 'Alpha'],
            'position' => 1,
            'created_at' => now()->subDays(2),
            'financer_id' => $financerId,
        ]);
        resolve(LinkFactory::class)->create([
            'name' => ['fr-BE' => 'Bravo (fr-BE)', 'nl-BE' => 'Bravo'],
            'position' => 2,
            'created_at' => now()->subDay(),
            'financer_id' => $financerId,
        ]);
        resolve(LinkFactory::class)->create([
            'name' => ['fr-BE' => 'Charlie (fr-BE)', 'nl-BE' => 'Charlie'],
            'position' => 3,
            'created_at' => now(),
            'financer_id' => $financerId,
        ]);

        app()->setLocale('fr-BE');

        return $this->actingAs($user)->getJson(route('links.index', $params));
    }

    /**
     * @param  array<int, array<string, mixed>>  $data
     * @return array<int, string|null>
     */
    private function pluckLocaleFromName(array $data, string $locale): array
    {
        return array_map(
            fn (array $row): mixed => $row['name_raw'][$locale] ?? null,
            $data
        );
    }
}
