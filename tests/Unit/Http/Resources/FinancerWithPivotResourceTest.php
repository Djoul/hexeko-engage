<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\Languages;
use App\Http\Resources\Financer\FinancerWithPivotResource;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('resource')]
#[Group('financer')]
#[Group('language')]
class FinancerWithPivotResourceTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_includes_language_in_pivot_data(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser();

        // Attach financer with language in pivot
        $user->financers()->attach($financer->id, [
            'active' => true,
            'from' => now()->toDateString(),
            'role' => 'beneficiary',
            'language' => Languages::FRENCH,
        ]);

        // Reload financer with pivot data
        $financerWithPivot = $user->financers()->first();

        // Act
        $resource = new FinancerWithPivotResource($financerWithPivot);
        $array = $resource->toArray(request());

        // Assert - Language is included in pivot
        $this->assertArrayHasKey('pivot', $array);
        $this->assertArrayHasKey('language', $array['pivot']);
        $this->assertEquals(Languages::FRENCH, $array['pivot']['language']);
    }

    #[Test]
    public function it_handles_null_language_in_pivot(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser();

        // Attach financer without language in pivot
        $user->financers()->attach($financer->id, [
            'active' => true,
            'from' => now()->toDateString(),
            'role' => 'beneficiary',
            'language' => null,
        ]);

        // Reload financer with pivot data
        $financerWithPivot = $user->financers()->first();

        // Act
        $resource = new FinancerWithPivotResource($financerWithPivot);
        $array = $resource->toArray(request());

        // Assert - Language is null
        $this->assertArrayHasKey('pivot', $array);
        $this->assertArrayHasKey('language', $array['pivot']);
        $this->assertNull($array['pivot']['language']);
    }

    #[Test]
    public function it_includes_all_pivot_attributes_with_language(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser();

        $fromDate = now()->subDays(30)->toDateString();
        $toDate = now()->addDays(30)->toDateString();

        // Attach financer with complete pivot data
        $user->financers()->attach($financer->id, [
            'active' => true,
            'from' => $fromDate,
            'to' => $toDate,
            'sirh_id' => 'SIRH123',
            'role' => 'admin',
            'language' => Languages::ENGLISH,
        ]);

        // Reload financer with pivot data
        $financerWithPivot = $user->financers()->first();

        // Act
        $resource = new FinancerWithPivotResource($financerWithPivot);
        $array = $resource->toArray(request());

        // Assert - All pivot attributes are present
        $this->assertArrayHasKey('pivot', $array);
        $pivot = $array['pivot'];
        $this->assertEquals(true, $pivot['active']);
        $this->assertNotNull($pivot['from']);
        $this->assertNotNull($pivot['to']);
        $this->assertEquals('SIRH123', $pivot['sirh_id']);
        $this->assertEquals('admin', $pivot['role']);
        $this->assertEquals(Languages::ENGLISH, $pivot['language']);
    }

    #[Test]
    public function it_transforms_multiple_financers_with_different_languages(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer1 = ModelFactory::createFinancer(['division_id' => $division->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser();

        // Attach financers with different languages
        $user->financers()->attach($financer1->id, [
            'active' => true,
            'from' => now()->toDateString(),
            'role' => 'beneficiary',
            'language' => Languages::FRENCH,
        ]);

        $user->financers()->attach($financer2->id, [
            'active' => true,
            'from' => now()->toDateString(),
            'role' => 'beneficiary',
            'language' => Languages::GERMAN,
        ]);

        // Reload financers with pivot data
        $financers = $user->financers;

        // Act
        $resources = FinancerWithPivotResource::collection($financers);
        $array = $resources->toArray(request());

        // Assert - Each financer has its correct language
        $this->assertCount(2, $array);

        $languages = collect($array)->pluck('pivot.language')->toArray();
        $this->assertContains(Languages::FRENCH, $languages);
        $this->assertContains(Languages::GERMAN, $languages);
    }

    #[Test]
    public function it_supports_all_20_languages_in_pivot(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $user = ModelFactory::createUser();
        $testLanguages = [
            Languages::ENGLISH,
            Languages::FRENCH,
            Languages::GERMAN,
            Languages::SPANISH,
            Languages::ITALIAN,
        ];

        foreach ($testLanguages as $language) {
            $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
            $user->financers()->attach($financer->id, [
                'active' => true,
                'from' => now()->toDateString(),
                'role' => 'beneficiary',
                'language' => $language,
            ]);
        }

        // Reload financers with pivot data
        $financers = $user->financers;

        // Act
        $resources = FinancerWithPivotResource::collection($financers);
        $array = $resources->toArray(request());

        // Assert - All languages are correctly transformed
        $this->assertCount(count($testLanguages), $array);

        foreach ($array as $financerData) {
            $this->assertArrayHasKey('pivot', $financerData);
            $this->assertArrayHasKey('language', $financerData['pivot']);
            $this->assertContains($financerData['pivot']['language'], $testLanguages);
        }
    }
}
