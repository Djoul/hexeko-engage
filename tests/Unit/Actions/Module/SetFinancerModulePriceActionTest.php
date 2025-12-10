<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Module;

use App\Actions\Module\RecordPricingHistoryAction;
use App\Actions\Module\SetFinancerModulePriceAction;
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
class SetFinancerModulePriceActionTest extends TestCase
{
    use DatabaseTransactions;

    private SetFinancerModulePriceAction $action;

    private Financer $financer;

    private Module $module;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new SetFinancerModulePriceAction(
            new RecordPricingHistoryAction
        );

        $this->financer = Financer::factory()->create();
        $this->module = Module::factory()->create();
        $this->user = User::factory()->create();
        Auth::login($this->user);
    }

    #[Test]
    public function it_sets_module_price_for_financer(): void
    {
        // Arrange
        $price = 2000; // €20.00

        // Act
        $this->action->execute($this->financer, $this->module, $price);

        // Assert
        $pivot = $this->financer->modules()
            ->where('module_id', $this->module->id)
            ->first();

        $this->assertNotNull($pivot);
        $this->assertEquals($price, $pivot->pivot->price_per_beneficiary);
    }

    #[Test]
    public function it_updates_existing_module_price_for_financer(): void
    {
        // Arrange
        $this->financer->modules()->attach($this->module->id, [
            'active' => true,
            'promoted' => true,
            'price_per_beneficiary' => 1500,
        ]);

        $newPrice = 2500;

        // Act
        $this->action->execute($this->financer, $this->module, $newPrice);

        // Assert
        $pivot = $this->financer->modules()
            ->where('module_id', $this->module->id)
            ->first();

        $this->assertEquals($newPrice, $pivot->pivot->price_per_beneficiary);
        $this->assertTrue($pivot->pivot->active); // Should preserve active status
        $this->assertTrue($pivot->pivot->promoted); // Should preserve promoted status
    }

    #[Test]
    public function it_records_pricing_history_for_financer(): void
    {
        // Arrange
        $this->financer->modules()->attach($this->module->id, [
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => 1800,
        ]);

        $newPrice = 2200;

        // Act
        $this->action->execute($this->financer, $this->module, $newPrice, 'Contract negotiation');

        // Assert
        $this->assertDatabaseHas('module_pricing_history', [
            'module_id' => $this->module->id,
            'entity_id' => $this->financer->id,
            'entity_type' => 'financer',
            'old_price' => 1800,
            'new_price' => $newPrice,
            'price_type' => 'module_price',
            'changed_by' => $this->user->id,
            'reason' => 'Contract negotiation',
        ]);
    }

    #[Test]
    public function it_can_remove_financer_price_override(): void
    {
        // Arrange
        $this->financer->modules()->attach($this->module->id, [
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => 3000,
        ]);

        // Act
        $this->action->execute($this->financer, $this->module, null, 'Reverting to division price');

        // Assert
        $pivot = $this->financer->modules()
            ->where('module_id', $this->module->id)
            ->first();

        $this->assertNull($pivot->pivot->price_per_beneficiary);
        $this->assertTrue($pivot->pivot->active); // Should preserve active status
    }

    #[Test]
    public function it_creates_pivot_with_price_if_not_exists(): void
    {
        // Arrange
        $price = 4500;

        // Act
        $this->action->execute($this->financer, $this->module, $price);

        // Assert
        $pivot = $this->financer->modules()
            ->where('module_id', $this->module->id)
            ->first();

        $this->assertNotNull($pivot);
        $this->assertEquals($price, $pivot->pivot->price_per_beneficiary);
        $this->assertFalse($pivot->pivot->active); // Should be inactive by default
        $this->assertFalse($pivot->pivot->promoted); // Should not be promoted by default
    }

    #[Test]
    public function it_preserves_promoted_status_when_updating_price(): void
    {
        // Arrange
        $this->financer->modules()->attach($this->module->id, [
            'active' => true,
            'promoted' => true,
            'price_per_beneficiary' => 2000,
        ]);

        // Act
        $this->action->execute($this->financer, $this->module, 2500);

        // Assert
        $pivot = $this->financer->modules()
            ->where('module_id', $this->module->id)
            ->first();

        $this->assertTrue($pivot->pivot->promoted); // Promoted status should be preserved
    }
}
