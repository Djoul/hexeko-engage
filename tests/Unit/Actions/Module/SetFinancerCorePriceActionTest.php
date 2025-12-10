<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Module;

use App\Actions\Module\RecordPricingHistoryAction;
use App\Actions\Module\SetFinancerCorePriceAction;
use App\Models\Financer;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('module')]
#[Group('pricing')]
#[Group('financer')]
class SetFinancerCorePriceActionTest extends TestCase
{
    use DatabaseTransactions;

    private SetFinancerCorePriceAction $action;

    private Financer $financer;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new SetFinancerCorePriceAction(
            new RecordPricingHistoryAction
        );

        $this->financer = Financer::factory()->create();
        $this->user = User::factory()->create();
        Auth::login($this->user);

        // Ensure at least one core module exists
        Module::factory()->create(['is_core' => true]);
    }

    #[Test]
    public function it_sets_core_package_price_for_financer(): void
    {
        // Arrange
        $price = 7500; // €75.00

        // Act
        $this->action->execute($this->financer, $price);

        // Assert
        $this->financer->refresh();
        $this->assertEquals($price, $this->financer->core_package_price);
    }

    #[Test]
    public function it_updates_existing_core_package_price(): void
    {
        // Arrange
        $this->financer->core_package_price = 5000;
        $this->financer->save();

        $newPrice = 6500;

        // Act
        $this->action->execute($this->financer, $newPrice);

        // Assert
        $this->financer->refresh();
        $this->assertEquals($newPrice, $this->financer->core_package_price);
    }

    #[Test]
    public function it_records_pricing_history_for_core_package(): void
    {
        // Arrange
        $this->financer->core_package_price = 4500;
        $this->financer->save();

        $newPrice = 5500;
        $coreModule = Module::where('is_core', true)->first();

        // Act
        $this->action->execute($this->financer, $newPrice, 'Contract renegotiation');

        // Assert
        $this->assertDatabaseHas('module_pricing_history', [
            'module_id' => $coreModule->id,
            'entity_id' => $this->financer->id,
            'entity_type' => 'financer',
            'old_price' => 4500,
            'new_price' => $newPrice,
            'price_type' => 'core_package',
            'changed_by' => $this->user->id,
            'reason' => 'Contract renegotiation',
        ]);
    }

    #[Test]
    public function it_can_set_core_price_from_null(): void
    {
        // Arrange
        $this->financer->core_package_price = null;
        $this->financer->save();

        $price = 8000;

        // Act
        $this->action->execute($this->financer, $price);

        // Assert
        $this->financer->refresh();
        $this->assertEquals($price, $this->financer->core_package_price);
    }

    #[Test]
    public function it_can_remove_core_price_by_setting_null(): void
    {
        // Arrange
        $this->financer->core_package_price = 5500;
        $this->financer->save();

        // Act
        $this->action->execute($this->financer, null, 'Reverting to division pricing');

        // Assert
        $this->financer->refresh();
        $this->assertNull($this->financer->core_package_price);
    }

    #[Test]
    public function it_logs_activity_when_changing_core_price(): void
    {
        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }

        // Arrange
        $oldPrice = 4000;
        $newPrice = 4800;
        $this->financer->core_package_price = $oldPrice;
        $this->financer->save();

        // Act
        $this->action->execute($this->financer, $newPrice);

        // Assert
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'financer_core_pricing',
            'subject_type' => Financer::class,
            'subject_id' => $this->financer->id,
            'description' => "Financer core package price updated from {$oldPrice} to {$newPrice} cents",
        ]);
    }

    #[Test]
    public function it_handles_case_when_no_core_module_exists(): void
    {
        // Arrange
        Module::where('is_core', true)->delete();
        $price = 9000;

        // Act & Assert - Should not throw exception
        $this->action->execute($this->financer, $price);

        $this->financer->refresh();
        $this->assertEquals($price, $this->financer->core_package_price);
    }

    #[Test]
    public function it_preserves_financer_specific_pricing_over_division(): void
    {
        // Arrange
        // Division has its own core price
        $this->financer->division->core_package_price = 3000;
        $this->financer->division->save();

        // Financer sets its own price
        $financerPrice = 4500;

        // Act
        $this->action->execute($this->financer, $financerPrice);

        // Assert
        $this->financer->refresh();
        $this->assertEquals($financerPrice, $this->financer->core_package_price);
        // Financer price should be independent of division price
        $this->assertNotEquals($this->financer->division->core_package_price, $this->financer->core_package_price);
    }
}
