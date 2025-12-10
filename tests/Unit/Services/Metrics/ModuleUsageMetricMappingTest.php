<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Metrics;

use App\Enums\FinancerMetricType;
use App\Enums\IDP\RoleDefaults;
use App\Enums\MetricPeriod;
use App\Enums\ModulesCategories;
use App\Models\EngagementLog;
use App\Models\Financer;
use App\Models\FinancerUser;
use App\Models\Module;
use App\Models\User;
use App\Services\Metrics\MetricService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

#[Group('metrics')]
#[Group('bug-fix')]
class ModuleUsageMetricMappingTest extends TestCase
{
    use DatabaseTransactions;

    private MetricService $service;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MetricService::class);

        /** @var Financer $financer */
        $financer = Financer::factory()->create();
        $this->financer = $financer;
    }

    #[Test]
    public function it_correctly_generates_translation_keys_from_module_names(): void
    {
        // Arrange: Create a module with realistic translatable names
        $moduleId = Uuid::uuid7()->toString();
        Module::factory()->create([
            'id' => $moduleId,
            'name' => [
                'en-US' => 'Vouchers',
                'fr-FR' => 'Bons d\'achat',
                'fr-BE' => 'Bons d\'achat',
            ],
            'active' => true,
            'category' => ModulesCategories::PURCHASING_POWER,
        ]);

        // Create a user and financer relationship
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        // Create engagement logs (use yesterday because metrics exclude current day)
        $yesterday = Carbon::now()->subDay();
        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'ModuleAccessed',
            'target' => $moduleId,
            'logged_at' => $yesterday,
            'created_at' => $yesterday,
            'metadata' => ['session_id' => Uuid::uuid4()->toString(), 'financer_id' => $this->financer->id],
        ]);

        // Act: Get the module usage metric
        $result = $this->service->getMetric(
            $this->financer->id,
            FinancerMetricType::MODULE_USAGE,
            ['period' => MetricPeriod::SEVEN_DAYS]
        );

        // Assert: Check that the translation key is correctly generated
        $this->assertNotEmpty($result->datasets);

        // Find the dataset for our module
        $dataset = collect($result->datasets)->first();
        $this->assertNotNull($dataset);
        $this->assertIsArray($dataset);
        $this->assertArrayHasKey('label', $dataset);

        /** @var string $label */
        $label = $dataset['label'];
        $this->assertIsString($label);

        // The label should be a translation key in format "interface.module.{camelCase}"
        $this->assertEquals(
            'interface.module.vouchers',
            $label,
            "Module label should be 'interface.module.vouchers' (camelCase translation key), but got '{$label}'"
        );

        // Ensure it follows the correct pattern
        $this->assertStringStartsWith('interface.module.', $label);
        $this->assertStringNotContainsString(' ', $label); // No spaces in camelCase
        $this->assertStringNotContainsString('-', $label); // No hyphens in camelCase
    }

    #[Test]
    public function it_handles_modules_with_missing_translations_gracefully(): void
    {
        // Arrange: Create a module with only one language
        $moduleId = Uuid::uuid7()->toString();
        Module::factory()->create([
            'id' => $moduleId,
            'name' => [
                'fr-FR' => 'Module de Test',
            ],
            'active' => true,
            'category' => ModulesCategories::ENTERPRISE_LIFE,
        ]);

        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $yesterday = Carbon::now()->subDay();
        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'ModuleAccessed',
            'target' => $moduleId,
            'logged_at' => $yesterday,
            'created_at' => $yesterday,
            'metadata' => ['financer_id' => $this->financer->id],
        ]);

        // Act
        $result = $this->service->getMetric(
            $this->financer->id,
            FinancerMetricType::MODULE_USAGE,
            ['period' => MetricPeriod::SEVEN_DAYS]
        );

        // Assert: Should fallback to the first available translation and generate camelCase key
        $dataset = collect($result->datasets)->first();
        $this->assertNotNull($dataset);
        $this->assertIsArray($dataset);
        $this->assertArrayHasKey('label', $dataset);

        /** @var string $label */
        $label = $dataset['label'];
        $this->assertIsString($label);

        // "Module de Test" → "moduleDeTest"
        $this->assertEquals('interface.module.moduleDeTest', $label);
    }

    #[Test]
    public function it_uses_fallback_for_modules_without_name(): void
    {
        // Arrange: Create a module with empty name (edge case)
        $moduleId = Uuid::uuid7()->toString();
        Module::factory()->create([
            'id' => $moduleId,
            'name' => [],
            'active' => true,
            'category' => ModulesCategories::WELLBEING,
        ]);

        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $yesterday = Carbon::now()->subDay();
        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'ModuleAccessed',
            'target' => $moduleId,
            'logged_at' => $yesterday,
            'created_at' => $yesterday,
            'metadata' => ['financer_id' => $this->financer->id],
        ]);

        // Act
        $result = $this->service->getMetric(
            $this->financer->id,
            FinancerMetricType::MODULE_USAGE,
            ['period' => MetricPeriod::SEVEN_DAYS]
        );

        // Assert: Should use technical fallback "interface.module.module{id}"
        $dataset = collect($result->datasets)->first();
        $this->assertNotNull($dataset);
        $this->assertIsArray($dataset);
        $this->assertArrayHasKey('label', $dataset);

        /** @var string $label */
        $label = $dataset['label'];
        $this->assertIsString($label);

        $this->assertStringStartsWith('interface.module.module', $label);
        $this->assertStringContainsString($moduleId, $label);
    }
}
