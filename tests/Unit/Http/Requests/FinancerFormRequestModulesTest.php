<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\FinancerFormRequest;
use App\Models\Division;
use App\Models\Module;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('financer')]
#[Group('module')]
class FinancerFormRequestModulesTest extends TestCase
{
    use DatabaseTransactions;

    private FinancerFormRequest $request;

    private Division $division;

    private array $baseData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new FinancerFormRequest;

        // Create test data
        $this->division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'status' => 'active',
        ]);

        $this->baseData = [
            'name' => 'Test Financer',
            'company_number' => 'BE123456789',
            'division_id' => $this->division->id,
        ];
    }

    #[Test]
    public function it_validates_modules_array_structure(): void
    {
        $module = Module::factory()->create(['is_core' => false]);

        $data = array_merge($this->baseData, [
            'modules' => [
                [
                    'id' => $module->id,
                    'active' => true,
                    'promoted' => false,
                    'price_per_beneficiary' => 500,
                ],
            ],
        ]);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Validation should pass for valid modules structure');
    }

    #[Test]
    public function it_accepts_null_modules_parameter(): void
    {
        $data = array_merge($this->baseData, [
            'modules' => null,
        ]);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Validation should pass with null modules');
    }

    #[Test]
    public function it_accepts_empty_modules_array(): void
    {
        $data = array_merge($this->baseData, [
            'modules' => [],
        ]);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Validation should pass with empty modules array');
    }

    #[Test]
    public function it_validates_module_id_is_required_when_modules_present(): void
    {
        $data = array_merge($this->baseData, [
            'modules' => [
                [
                    'active' => true, // Missing 'id'
                ],
            ],
        ]);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('modules.0.id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_module_active_is_required_when_modules_present(): void
    {
        $data = array_merge($this->baseData, [
            'modules' => [
                [
                    'id' => 'module-uuid',
                    // Missing 'active'
                ],
            ],
        ]);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('modules.0.active', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_module_id_exists_in_database(): void
    {
        $module = Module::factory()->create();

        $data = array_merge($this->baseData, [
            'modules' => [
                [
                    'id' => $module->id,
                    'active' => true,
                ],
                [
                    'id' => 'non-existent-uuid',
                    'active' => true,
                ],
            ],
        ]);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('modules.1.id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_module_active_is_boolean(): void
    {
        $module = Module::factory()->create();

        $data = array_merge($this->baseData, [
            'modules' => [
                [
                    'id' => $module->id,
                    'active' => 'yes', // Should be boolean
                ],
            ],
        ]);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('modules.0.active', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_promoted_is_boolean_when_provided(): void
    {
        $module = Module::factory()->create();

        $data = array_merge($this->baseData, [
            'modules' => [
                [
                    'id' => $module->id,
                    'active' => true,
                    'promoted' => 'yes', // Should be boolean
                ],
            ],
        ]);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('modules.0.promoted', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_price_per_beneficiary_is_integer(): void
    {
        $module = Module::factory()->create();

        $data = array_merge($this->baseData, [
            'modules' => [
                [
                    'id' => $module->id,
                    'active' => true,
                    'price_per_beneficiary' => 99.99, // Should be integer (cents)
                ],
            ],
        ]);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('modules.0.price_per_beneficiary', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_price_per_beneficiary_minimum(): void
    {
        $module = Module::factory()->create();

        $data = array_merge($this->baseData, [
            'modules' => [
                [
                    'id' => $module->id,
                    'active' => true,
                    'price_per_beneficiary' => -100, // Negative price not allowed
                ],
            ],
        ]);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('modules.0.price_per_beneficiary', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_price_per_beneficiary_maximum(): void
    {
        $module = Module::factory()->create();

        $data = array_merge($this->baseData, [
            'modules' => [
                [
                    'id' => $module->id,
                    'active' => true,
                    'price_per_beneficiary' => 10000000, // Exceeds maximum
                ],
            ],
        ]);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('modules.0.price_per_beneficiary', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_modules_is_array_not_string(): void
    {
        $data = array_merge($this->baseData, [
            'modules' => 'not-an-array',
        ]);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('modules', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_maximum_modules_limit(): void
    {
        // Create more than 100 modules (assuming 100 is the limit)
        $modules = [];
        for ($i = 0; $i < 101; $i++) {
            $modules[] = [
                'id' => "module-{$i}",
                'active' => true,
            ];
        }

        $data = array_merge($this->baseData, [
            'modules' => $modules,
        ]);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('modules', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_core_module_cannot_be_deactivated(): void
    {
        $coreModule = Module::factory()->create(['is_core' => true]);

        $data = array_merge($this->baseData, [
            'modules' => [
                [
                    'id' => $coreModule->id,
                    'active' => false, // Trying to deactivate core module
                ],
            ],
        ]);

        // Create a request instance with the data
        $request = new FinancerFormRequest;
        $request->merge($data);
        $validator = Validator::make($data, $request->rules());

        // Call withValidator to add custom validations
        $request->withValidator($validator);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('modules.0.active', $validator->errors()->toArray());
        $errors = $validator->errors()->get('modules.0.active');
        $this->assertContains('Core module cannot be deactivated', $errors);
    }

    #[Test]
    public function it_validates_core_module_price_must_be_null(): void
    {
        $coreModule = Module::factory()->create(['is_core' => true]);

        $data = array_merge($this->baseData, [
            'modules' => [
                [
                    'id' => $coreModule->id,
                    'active' => true,
                    'price_per_beneficiary' => 500, // Core modules must have null price
                ],
            ],
        ]);

        // Create a request instance with the data
        $request = new FinancerFormRequest;
        $request->merge($data);
        $validator = Validator::make($data, $request->rules());

        // Call withValidator to add custom validations
        $request->withValidator($validator);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('modules.0.price_per_beneficiary', $validator->errors()->toArray());
    }

    #[Test]
    public function it_accepts_valid_modules_with_all_optional_fields(): void
    {
        $module1 = Module::factory()->create(['is_core' => false]);
        $module2 = Module::factory()->create(['is_core' => false]);

        $data = array_merge($this->baseData, [
            'modules' => [
                [
                    'id' => $module1->id,
                    'active' => true,
                    'promoted' => true,
                    'price_per_beneficiary' => 299,
                ],
                [
                    'id' => $module2->id,
                    'active' => false, // Can deactivate non-core module
                    'promoted' => false,
                    'price_per_beneficiary' => null, // Can have null price
                ],
            ],
        ]);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->passes(), 'Validation should pass for valid modules with all fields');
    }

    public static function invalidModuleDataProvider(): array
    {
        return [
            'non-uuid id' => [
                ['id' => 'not-a-uuid', 'active' => true],
                'modules.0.id',
            ],
            'string active' => [
                ['id' => 'valid-uuid', 'active' => 'yes'],
                'modules.0.active',
            ],
            'string price' => [
                ['id' => 'valid-uuid', 'active' => true, 'price_per_beneficiary' => 'fifty'],
                'modules.0.price_per_beneficiary',
            ],
            'float price' => [
                ['id' => 'valid-uuid', 'active' => true, 'price_per_beneficiary' => 49.99],
                'modules.0.price_per_beneficiary',
            ],
        ];
    }

    #[Test]
    #[DataProvider('invalidModuleDataProvider')]
    public function it_rejects_invalid_module_data(array $moduleData, string $expectedErrorKey): void
    {
        $data = array_merge($this->baseData, [
            'modules' => [$moduleData],
        ]);

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey($expectedErrorKey, $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_price_required_for_active_non_core_modules(): void
    {
        $nonCoreModule = Module::factory()->create(['is_core' => false]);

        $data = array_merge($this->baseData, [
            'modules' => [
                [
                    'id' => $nonCoreModule->id,
                    'active' => true,
                    'price_per_beneficiary' => null, // Missing price for active non-core module
                ],
            ],
        ]);

        // Create a request instance with the data
        $request = new FinancerFormRequest;
        $request->merge($data);
        $validator = Validator::make($data, $request->rules());

        // Call withValidator to add custom validations
        $request->withValidator($validator);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('modules.0.price_per_beneficiary', $validator->errors()->toArray());
        $errors = $validator->errors()->get('modules.0.price_per_beneficiary');
        $this->assertContains('Active non-core modules must have a price', $errors);
    }

    #[Test]
    public function it_allows_null_price_for_inactive_non_core_modules(): void
    {
        $nonCoreModule = Module::factory()->create(['is_core' => false]);

        $data = array_merge($this->baseData, [
            'modules' => [
                [
                    'id' => $nonCoreModule->id,
                    'active' => false, // Inactive module
                    'price_per_beneficiary' => null, // Null price is OK for inactive
                ],
            ],
        ]);

        // Create a request instance with the data
        $request = new FinancerFormRequest;
        $request->merge($data);
        $validator = Validator::make($data, $request->rules());

        // Call withValidator to add custom validations
        $request->withValidator($validator);

        // Should pass validation - inactive non-core modules can have null price
        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_prevents_setting_any_price_on_core_module_even_with_active_true(): void
    {
        $coreModule = Module::factory()->create(['is_core' => true]);

        $data = array_merge($this->baseData, [
            'modules' => [
                [
                    'id' => $coreModule->id,
                    'active' => true, // Active
                    'price_per_beneficiary' => 100, // Any price on core module is invalid
                ],
            ],
        ]);

        // Create a request instance with the data
        $request = new FinancerFormRequest;
        $request->merge($data);
        $validator = Validator::make($data, $request->rules());

        // Call withValidator to add custom validations
        $request->withValidator($validator);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('modules.0.price_per_beneficiary', $validator->errors()->toArray());
        $errors = $validator->errors()->get('modules.0.price_per_beneficiary');
        $this->assertContains('Core module price must always be null (included in core package price)', $errors);
    }
}
