<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Module;

use App\Actions\Module\RecordPricingHistoryAction;
use App\Actions\Module\SetDivisionCorePriceAction;
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
class SetDivisionCorePriceActionTest extends TestCase
{
    use DatabaseTransactions;

    private SetDivisionCorePriceAction $action;

    private Division $division;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new SetDivisionCorePriceAction(
            new RecordPricingHistoryAction
        );

        $this->division = Division::factory()->create();
        $this->user = User::factory()->create();
        Auth::login($this->user);

        // Ensure at least one core module exists
        Module::factory()->create(['is_core' => true]);
    }

    #[Test]
    public function it_sets_core_package_price_for_division(): void
    {
        // Arrange
        $price = 5000; // €50.00

        // Act
        $this->action->execute($this->division, $price);

        // Assert
        $this->division->refresh();
        $this->assertEquals($price, $this->division->core_package_price);
    }

    #[Test]
    public function it_updates_existing_core_package_price(): void
    {
        // Arrange
        $this->division->core_package_price = 3000;
        $this->division->save();

        $newPrice = 4500;

        // Act
        $this->action->execute($this->division, $newPrice);

        // Assert
        $this->division->refresh();
        $this->assertEquals($newPrice, $this->division->core_package_price);
    }

    #[Test]
    public function it_records_pricing_history_for_core_package(): void
    {
        // Arrange
        $this->division->core_package_price = 2500;
        $this->division->save();

        $newPrice = 3500;
        $coreModule = Module::where('is_core', true)->first();

        // Act
        $this->action->execute($this->division, $newPrice, 'Annual pricing review');

        // Assert
        $this->assertDatabaseHas('module_pricing_history', [
            'module_id' => $coreModule->id,
            'entity_id' => $this->division->id,
            'entity_type' => 'division',
            'old_price' => 2500,
            'new_price' => $newPrice,
            'price_type' => 'core_package',
            'changed_by' => $this->user->id,
            'reason' => 'Annual pricing review',
        ]);
    }

    #[Test]
    public function it_can_set_core_price_from_null(): void
    {
        // Arrange
        $this->division->core_package_price = null;
        $this->division->save();

        $price = 6000;

        // Act
        $this->action->execute($this->division, $price);

        // Assert
        $this->division->refresh();
        $this->assertEquals($price, $this->division->core_package_price);
    }

    #[Test]
    public function it_can_remove_core_price_by_setting_null(): void
    {
        // Arrange
        $this->division->core_package_price = 4000;
        $this->division->save();

        // Act
        $this->action->execute($this->division, null, 'Removing core package pricing');

        // Assert
        $this->division->refresh();
        $this->assertNull($this->division->core_package_price);
    }

    #[Test]
    public function it_logs_activity_when_changing_core_price(): void
    {
        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }

        // Arrange
        $oldPrice = 3000;
        $newPrice = 3500;
        $this->division->core_package_price = $oldPrice;
        $this->division->save();

        // Act
        $this->action->execute($this->division, $newPrice);

        // Assert
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'division_core_pricing',
            'subject_type' => Division::class,
            'subject_id' => $this->division->id,
            'description' => "Division core package price updated from {$oldPrice} to {$newPrice} cents",
        ]);
    }

    #[Test]
    public function it_handles_case_when_no_core_module_exists(): void
    {
        // Arrange
        Module::where('is_core', true)->delete();
        $price = 7000;

        // Act & Assert - Should not throw exception
        $this->action->execute($this->division, $price);

        $this->division->refresh();
        $this->assertEquals($price, $this->division->core_package_price);
    }
}
