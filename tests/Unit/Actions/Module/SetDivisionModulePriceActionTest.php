<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Module;

use App\Actions\Module\RecordPricingHistoryAction;
use App\Actions\Module\SetDivisionModulePriceAction;
use App\Models\Division;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('module')]
#[Group('pricing')]
#[Group('division')]
#[Group('actions')]
class SetDivisionModulePriceActionTest extends TestCase
{
    use DatabaseTransactions;

    private SetDivisionModulePriceAction $action;

    private Division $division;

    private Module $module;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new SetDivisionModulePriceAction(
            new RecordPricingHistoryAction
        );

        $this->division = Division::factory()->create();
        $this->module = Module::factory()->create();
        $this->user = User::factory()->create();
        Auth::login($this->user);
    }

    #[Test]
    public function it_sets_module_price_for_division(): void
    {
        // Arrange
        $price = 1500; // €15.00

        // Act
        $this->action->execute($this->division, $this->module, $price);

        // Assert
        $pivot = $this->division->modules()
            ->where('module_id', $this->module->id)
            ->first();

        $this->assertNotNull($pivot);
        $this->assertEquals($price, $pivot->pivot->price_per_beneficiary);
    }

    #[Test]
    public function it_updates_existing_module_price(): void
    {
        // Arrange
        $this->division->modules()->attach($this->module->id, [
            'active' => true,
            'price_per_beneficiary' => 1000,
        ]);

        $newPrice = 2000;

        // Act
        $this->action->execute($this->division, $this->module, $newPrice);

        // Assert
        $pivot = $this->division->modules()
            ->where('module_id', $this->module->id)
            ->first();

        $this->assertEquals($newPrice, $pivot->pivot->price_per_beneficiary);
        $this->assertTrue($pivot->pivot->active); // Should preserve active status
    }

    #[Test]
    public function it_records_pricing_history(): void
    {
        // Arrange
        $this->division->modules()->attach($this->module->id, [
            'active' => true,
            'price_per_beneficiary' => 1000,
        ]);

        $newPrice = 1500;

        // Act
        $this->action->execute($this->division, $this->module, $newPrice, 'Annual adjustment');

        // Assert
        $this->assertDatabaseHas('module_pricing_history', [
            'module_id' => $this->module->id,
            'entity_id' => $this->division->id,
            'entity_type' => 'division',
            'old_price' => 1000,
            'new_price' => $newPrice,
            'price_type' => 'module_price',
            'changed_by' => $this->user->id,
            'reason' => 'Annual adjustment',
        ]);
    }

    #[Test]
    public function it_can_remove_price_by_setting_null(): void
    {
        // Arrange
        $this->division->modules()->attach($this->module->id, [
            'active' => true,
            'price_per_beneficiary' => 1000,
        ]);

        // Act
        $this->action->execute($this->division, $this->module, null);

        // Assert
        $pivot = $this->division->modules()
            ->where('module_id', $this->module->id)
            ->first();

        $this->assertNull($pivot->pivot->price_per_beneficiary);
    }

    #[Test]
    public function it_creates_pivot_if_not_exists(): void
    {
        // Arrange
        $price = 3000;

        // Act
        $this->action->execute($this->division, $this->module, $price);

        // Assert
        $pivot = $this->division->modules()
            ->where('module_id', $this->module->id)
            ->first();

        $this->assertNotNull($pivot);
        $this->assertEquals($price, $pivot->pivot->price_per_beneficiary);
        $this->assertFalse($pivot->pivot->active); // Should be inactive by default
    }
}
