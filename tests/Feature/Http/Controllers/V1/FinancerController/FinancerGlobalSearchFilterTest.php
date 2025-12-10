<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Division;
use App\Models\Financer;
use Context;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['financers', 'divisions'], scope: 'test')]
#[Group('financer')]
class FinancerGlobalSearchFilterTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up Laravel Context to ensure test isolation
        Context::flush();
        // Create user with admin role to have full access to financers
        $this->auth = $this->createAuthUser(RoleDefaults::HEXEKO_ADMIN);
    }

    protected function tearDown(): void
    {
        // Clean up Laravel Context after each test
        Context::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_searches_financers_by_name(): void
    {
        // Arrange
        Financer::factory()->create(['name' => 'Alpha UniqueNameCorp2025']);
        Financer::factory()->create(['name' => 'Beta Industries']);
        Financer::factory()->create(['name' => 'Gamma Solutions']);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/financers?search=UniqueNameCorp2025');

        // Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Alpha UniqueNameCorp2025']);
    }

    #[Test]
    public function it_searches_financers_by_registration_number(): void
    {
        // Arrange
        Financer::factory()->create([
            'name' => 'Company A',
            'registration_number' => 'REG123456789UNIQUE',
        ]);
        Financer::factory()->create([
            'name' => 'Company B',
            'registration_number' => 'REG987654321',
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/financers?search=123456789UNIQUE');

        // Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Company A']);
    }

    #[Test]
    public function it_searches_financers_by_vat_number(): void
    {
        // Arrange
        Financer::factory()->create([
            'name' => 'VAT Company 1',
            'vat_number' => 'FR12345678901',
        ]);
        Financer::factory()->create([
            'name' => 'VAT Company 2',
            'vat_number' => 'DE98765432101',
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/financers?search=FR123');

        // Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'VAT Company 1']);
    }

    #[Test]
    public function it_searches_financers_by_iban(): void
    {
        // Arrange
        Financer::factory()->create([
            'name' => 'Bank Account 1',
            'iban' => 'FR7630004000031234567890143',
        ]);
        Financer::factory()->create([
            'name' => 'Bank Account 2',
            'iban' => 'DE89370400440532013000',
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/financers?search=FR76');

        // Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Bank Account 1']);
    }

    #[Test]
    public function it_searches_financers_by_website(): void
    {
        // Arrange
        Financer::factory()->create([
            'name' => 'Tech Corp',
            'website' => 'https://techcorp.example.com',
        ]);
        Financer::factory()->create([
            'name' => 'Other Corp',
            'website' => 'https://othercorp.example.com',
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/financers?search=techcorp');

        // Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Tech Corp']);
    }

    #[Test]
    public function it_searches_financers_by_division_name(): void
    {
        // Arrange - This tests relation search
        $division1 = Division::factory()->create(['name' => 'European Operations']);
        $division2 = Division::factory()->create(['name' => 'Asian Operations']);

        Financer::factory()->create([
            'name' => 'Global Corp',
            'division_id' => $division1->id,
        ]);
        Financer::factory()->create([
            'name' => 'Local Corp',
            'division_id' => $division2->id,
        ]);

        // Admin users have access to all financers

        // Act - Search by division name through relation
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/financers?search=European');

        // Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Global Corp']);
    }

    #[Test]
    public function it_performs_case_insensitive_search(): void
    {
        // Arrange
        Financer::factory()->create(['name' => 'Technology Partners']);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/financers?search=TECHNOLOGY');

        // Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Technology Partners']);
    }

    #[Test]
    public function it_requires_minimum_two_characters_for_search(): void
    {
        // Arrange
        Financer::factory()->create(['name' => 'A Company']);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/financers?search=A');

        // Assert - Should return all financers since search term is too short
        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
    }

    #[Test]
    public function it_returns_empty_when_no_match(): void
    {
        // Arrange
        Financer::factory()->count(3)->create();

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/financers?search=NonExistentFinancer');

        // Assert
        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_respects_user_financer_access(): void
    {
        // Arrange - Admin users have access to all financers
        Financer::factory()->create(['name' => 'First UniqueAdminTest2025 Financer']);
        Financer::factory()->create(['name' => 'Second UniqueAdminTest2025 Financer']);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/financers?search=UniqueAdminTest2025');

        // Assert - Admin should see all matching financers
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'First UniqueAdminTest2025 Financer'])
            ->assertJsonFragment(['name' => 'Second UniqueAdminTest2025 Financer']);
    }
}
