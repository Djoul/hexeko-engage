<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Module;

use App\Actions\Module\RecordPricingHistoryAction;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('module')]
#[Group('pricing')]
#[Group('division')]
#[Group('financer')]
class RecordPricingHistoryActionTest extends TestCase
{
    use DatabaseTransactions;

    private RecordPricingHistoryAction $action;

    private Module $module;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new RecordPricingHistoryAction;
        $this->module = Module::factory()->create();
        $this->user = User::factory()->create();
        Auth::login($this->user);
    }

    #[Test]
    public function it_records_pricing_history_when_price_changes(): void
    {
        // Arrange
        $entityId = fake()->uuid();
        $oldPrice = 1000;
        $newPrice = 1500;

        // Act
        $this->action->execute(
            $this->module,
            $entityId,
            'division',
            $oldPrice,
            $newPrice,
            'module_price',
            'Price adjustment'
        );

        // Assert
        $this->assertDatabaseHas('module_pricing_history', [
            'module_id' => $this->module->id,
            'entity_id' => $entityId,
            'entity_type' => 'division',
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'price_type' => 'module_price',
            'changed_by' => $this->user->id,
            'reason' => 'Price adjustment',
        ]);
    }

    #[Test]
    public function it_does_not_record_history_when_price_unchanged(): void
    {
        // Arrange
        $entityId = fake()->uuid();
        $price = 1000;
        $initialCount = DB::table('module_pricing_history')->count();

        // Act
        $this->action->execute(
            $this->module,
            $entityId,
            'financer',
            $price,
            $price, // Same price
            'module_price'
        );

        // Assert
        $this->assertEquals($initialCount, DB::table('module_pricing_history')->count());
    }

    #[Test]
    public function it_records_history_when_setting_price_from_null(): void
    {
        // Arrange
        $entityId = fake()->uuid();
        $newPrice = 2000;

        // Act
        $this->action->execute(
            $this->module,
            $entityId,
            'division',
            null,
            $newPrice,
            'core_package'
        );

        // Assert
        $this->assertDatabaseHas('module_pricing_history', [
            'module_id' => $this->module->id,
            'entity_id' => $entityId,
            'old_price' => null,
            'new_price' => $newPrice,
            'price_type' => 'core_package',
        ]);
    }

    #[Test]
    public function it_records_history_when_removing_price(): void
    {
        // Arrange
        $entityId = fake()->uuid();
        $oldPrice = 1500;

        // Act
        $this->action->execute(
            $this->module,
            $entityId,
            'financer',
            $oldPrice,
            null,
            'module_price',
            'Removing custom pricing'
        );

        // Assert
        $this->assertDatabaseHas('module_pricing_history', [
            'module_id' => $this->module->id,
            'entity_id' => $entityId,
            'old_price' => $oldPrice,
            'new_price' => null,
            'reason' => 'Removing custom pricing',
        ]);
    }

    #[Test]
    public function it_logs_activity_when_recording_history(): void
    {
        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }

        // Arrange
        $entityId = fake()->uuid();
        $oldPrice = 1000;
        $newPrice = 2000;
        $effectiveDate = now()->startOfMonth()->addMonth()->toDateString();

        // Act
        $this->action->execute(
            $this->module,
            $entityId,
            'division',
            $oldPrice,
            $newPrice,
            'module_price'
        );

        // Assert
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'module_pricing',
            'subject_type' => Module::class,
            'subject_id' => $this->module->id,
            'description' => "Module price updated from {$oldPrice} to {$newPrice} cents - effective from {$effectiveDate}",
        ]);
    }
}
