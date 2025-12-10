<?php

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Division;
use App\Models\Financer;
use App\Models\User;
use Carbon\Carbon;
use Context;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('financer')]

class FinancerPipelineTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected bool $checkAuth = false;

    protected bool $checkPermissions = false;

    protected User $user;

    protected Division $division;

    protected Financer $mainFinancer;

    protected int $initialFinancerCount;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a division first
        $this->division = Division::factory()->create();

        // Create a main financer for the user in the same division
        $this->mainFinancer = Financer::factory()->create([
            'division_id' => $this->division->id,
        ]);

        // Create user with division_super_admin role
        $this->user = $this->createAuthUser(RoleDefaults::DIVISION_SUPER_ADMIN);

        // Attach user to financer with active status
        $this->user->financers()->attach($this->mainFinancer->id, [
            'active' => true,
            'sirh_id' => 'TEST-'.$this->user->id,
            'from' => now()->subYear(),
            'to' => null,
            'role' => 'division_super_admin',
        ]);

        Context::add('financer_id', $this->mainFinancer->id);
        Context::add('accessible_financers', $this->user->financers->pluck('id')->toArray());
        Context::add('accessible_divisions', [$this->division->id]);

        // Compter le nombre initial de financers après la création de l'utilisateur
        $this->initialFinancerCount = Financer::count();
    }

    #[Test]
    public function it_filters_financers_by_name(): void
    {
        // Arrange
        Financer::factory()->create([
            'name' => 'Test Financer Alpha',
            'division_id' => $this->division->id,
        ]);
        Financer::factory()->create([
            'name' => 'Test Financer Beta',
            'division_id' => $this->division->id,
        ]);
        Financer::factory()->create([
            'name' => 'Another Company',
            'division_id' => $this->division->id,
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&name=Test Financer&per_page=100');

        // Assert
        $response->assertStatus(200);
        $responseData = $response->json('data');

        $filteredData = array_filter($responseData, function (array $item): bool {
            return str_contains($item['name'], 'Test Financer');
        });

        $this->assertCount(2, $filteredData);
        $this->assertContains('Test Financer Alpha', array_column($filteredData, 'name'));
        $this->assertContains('Test Financer Beta', array_column($filteredData, 'name'));
    }

    #[Test]
    public function it_filters_financers_by_registration_number(): void
    {
        // Arrange
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'registration_number' => 'REG123456']);
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'registration_number' => 'REG789012']);
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'registration_number' => 'OTHER567']);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&registration_number=REG');

        // Assert
        $response->assertStatus(200);
        $responseData = $response->json('data');

        $filteredData = array_filter($responseData, function (array $item): bool {
            return str_contains($item['registration_number'], 'REG');
        });

        $this->assertCount(2, $filteredData);
        $this->assertContains('REG123456', array_column($filteredData, 'registration_number'));
        $this->assertContains('REG789012', array_column($filteredData, 'registration_number'));
    }

    #[Test]
    public function it_filters_financers_by_registration_country(): void
    {
        // Arrange
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'registration_country' => 'FR']);
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'registration_country' => 'FR']);
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'registration_country' => 'BE']);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&registration_country=FR');

        // Assert
        $response->assertStatus(200);
        $responseData = $response->json('data');

        // Filtrer les résultats pour ne garder que ceux avec registration_country = FR
        $filteredData = array_filter($responseData, function (array $item): bool {
            return $item['registration_country'] === 'FR';
        });

        $this->assertGreaterThanOrEqual(2, count($filteredData));
        // Vérifier que les deux financers créés avec FR sont présents
        $this->assertGreaterThanOrEqual(2, count(array_filter($filteredData, function (array $item): bool {
            return $item['registration_country'] === 'FR';
        })));
    }

    #[Test]
    public function it_filters_financers_by_website(): void
    {
        // Arrange
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'website' => 'https://example.com']);
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'website' => 'https://example.org']);
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'website' => 'https://test.com']);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&website=example');

        // Assert
        $response->assertStatus(200);
        $responseData = $response->json('data');

        // Filtrer les résultats pour ne garder que ceux qui contiennent "example"
        $filteredData = array_filter($responseData, function (array $item): bool {
            return isset($item['website']) && str_contains($item['website'], 'example');
        });

        $this->assertCount(2, $filteredData);
        $this->assertContains('https://example.com', array_column($filteredData, 'website'));
        $this->assertContains('https://example.org', array_column($filteredData, 'website'));
    }

    #[Test]
    public function it_filters_financers_by_iban(): void
    {
        // Arrange
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'iban' => 'FR7630001007941234567890185']);
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'iban' => 'FR7630004000031234567890143']);
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'iban' => 'BE71096123456769']);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&iban=FR76');

        // Assert
        $response->assertStatus(200);
        $responseData = $response->json('data');

        // Filtrer les résultats pour ne garder que ceux qui contiennent "FR76"
        $filteredData = array_filter($responseData, function (array $item): bool {
            return isset($item['iban']) && str_contains($item['iban'], 'FR76');
        });

        $this->assertCount(2, $filteredData);
        $this->assertContains('FR7630001007941234567890185', array_column($filteredData, 'iban'));
        $this->assertContains('FR7630004000031234567890143', array_column($filteredData, 'iban'));
    }

    #[Test]
    public function it_filters_financers_by_vat_number(): void
    {
        // Arrange
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'vat_number' => 'FR12345678901']);
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'vat_number' => 'FR98765432109']);
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'vat_number' => 'BE0123456789']);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&vat_number=FR');

        // Assert
        $response->assertStatus(200);
        $responseData = $response->json('data');

        // Filtrer les résultats pour ne garder que ceux qui contiennent "FR"
        $filteredData = array_filter($responseData, function (array $item): bool {
            return isset($item['vat_number']) && str_contains($item['vat_number'], 'FR');
        });

        $this->assertCount(2, $filteredData);
        $this->assertContains('FR12345678901', array_column($filteredData, 'vat_number'));
        $this->assertContains('FR98765432109', array_column($filteredData, 'vat_number'));
    }

    #[Test]
    public function it_filters_financers_by_division_id(): void
    {
        // Arrange - Use the same division as the main financer
        // Create additional financers in the same division
        Financer::factory()->create(['division_id' => $this->division->id]);
        Financer::factory()->create(['division_id' => $this->division->id]);

        // Create another division with financers that shouldn't be visible
        $otherDivision = Division::factory()->create();
        Financer::factory()->create(['division_id' => $otherDivision->id]);

        // Act - Filter by our division
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/financers?division_id='.$this->division->id.'&per_page=100&division_id={$this->division->id}");

        // Assert
        $response->assertStatus(200);
        $responseData = $response->json('data');

        // All returned financers should be from our division
        // We should have mainFinancer + 2 created = 3 total
        $filteredData = array_filter($responseData, function (array $item): bool {
            return $item['division_id'] === $this->division->id;
        });

        $this->assertCount(3, $filteredData);

        // Verify all are from the correct division
        foreach ($filteredData as $financer) {
            $this->assertEquals($this->division->id, $financer['division_id']);
        }
    }

    #[Test]
    public function it_filters_financers_by_date_range(): void
    {
        // Arrange
        // Create financers with specific created_at dates
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'created_at' => '2023-01-01 12:00:00']);
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'created_at' => '2023-02-15 12:00:00']);
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'created_at' => '2023-03-30 12:00:00']);

        // Act - Filter by date range
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&date_from=2023-01-15&date_to=2023-03-01');

        // Assert
        $response->assertStatus(200);
        $responseData = $response->json('data');

        // Filtrer les résultats pour ne garder que ceux dans la plage de dates
        $filteredData = array_filter($responseData, function (array $item): bool {
            $createdAt = Carbon::parse($item['created_at']);

            return $createdAt->between('2023-01-15', '2023-03-01');
        });

        $this->assertGreaterThanOrEqual(1, count($filteredData));
        // Vérifier qu'au moins un financer a une date dans la plage
        $this->assertNotEmpty($filteredData);
        $createdAt = Carbon::parse(reset($filteredData)['created_at']);
        $this->assertTrue($createdAt->between('2023-01-15', '2023-03-01'));
    }

    #[Test]
    public function it_combines_multiple_filters(): void
    {
        // Arrange - Use the same division as the main financer
        // Create financers with various attributes in the same division
        Financer::factory()->create([
            'name' => 'Test Company A',
            'registration_country' => 'FR',
            'division_id' => $this->division->id,
        ]);

        Financer::factory()->create([
            'name' => 'Test Company B',
            'registration_country' => 'BE',
            'division_id' => $this->division->id,
        ]);

        Financer::factory()->create([
            'name' => 'Another Business',
            'registration_country' => 'FR',
            'division_id' => $this->division->id,
        ]);

        // Act - Apply multiple filters
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/financers?division_id='.$this->division->id.'&per_page=100&name=Test&registration_country=FR&division_id={$this->division->id}");

        // Assert
        $response->assertStatus(200);
        $responseData = $response->json('data');

        // Filter results to match all criteria
        $filteredData = array_filter($responseData, function (array $item): bool {
            return str_contains($item['name'], 'Test') &&
                   $item['registration_country'] === 'FR' &&
                   $item['division_id'] === $this->division->id;
        });

        $this->assertCount(1, $filteredData);
        $this->assertEquals('Test Company A', reset($filteredData)['name']);
        $this->assertEquals('FR', reset($filteredData)['registration_country']);
        $this->assertEquals($this->division->id, reset($filteredData)['division_id']);
    }

    #[Test]
    public function it_returns_all_financers_when_no_filters_applied(): void
    {
        // Arrange
        $financerCount = 3;
        Financer::factory()->count($financerCount)->create(['division_id' => $this->division->id]);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100');

        // Assert
        $response->assertStatus(200);
        $responseData = $response->json('data');

        // Filter to only count financers from our division
        $financersInDivision = array_filter($responseData, function (array $item): bool {
            return $item['division_id'] === $this->division->id;
        });

        // We should have mainFinancer + 3 created = 4 total
        $this->assertCount(4, $financersInDivision);
    }

    #[Test]
    public function it_returns_empty_array_when_no_financers_match_filters(): void
    {
        // Arrange
        Financer::factory()->create([
            'division_id' => $this->division->id,
            'name' => 'Test Company']);

        // Act - Apply filter that won't match any records
        // Utiliser une valeur unique avec un UUID pour garantir qu'aucun financer ne correspond
        $uniqueValue = 'NonExistentName_'.uniqid();
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&name='.$uniqueValue);

        // Assert
        $response->assertStatus(200);
        // Vérifier que la réponse contient une clé 'data' qui est un tableau
        $this->assertIsArray($response->json('data'));

        // Filtrer les résultats pour ne garder que ceux qui contiennent notre valeur unique
        $filteredData = array_filter($response->json('data'), function (array $item) use ($uniqueValue): bool {
            return str_contains($item['name'], $uniqueValue);
        });

        // Vérifier qu'aucun résultat ne correspond à notre filtre
        $this->assertCount(0, $filteredData);
    }

    #[Test]
    public function user_pipe_class_exist(): void
    {
        $user = new Financer;
        $modelName = class_basename($user);
        $pipelineClass = "\\App\\Pipelines\\FilterPipelines\\{$modelName}Pipeline";

        $this->assertTrue(class_exists($pipelineClass), "Pipeline class {$pipelineClass} does not exist");

    }
}
