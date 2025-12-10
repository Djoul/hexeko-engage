<?php

namespace Tests\Unit\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use OwenIt\Auditing\Models\Audit;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('audit')]
#[Group('commands')]
class AuditCleanCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure auditing is enabled for tests
        config(['audit.enabled' => true]);

        // Clean up any existing audit records to ensure test isolation
        Audit::query()->delete();
    }

    /**
     * Test that the audit:clean command removes old records.
     */
    public function test_audit_clean_command_removes_old_records(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Create some old audit records manually
        $oldAudit1 = new Audit;
        $oldAudit1->auditable_type = User::class;
        $oldAudit1->auditable_id = $user->id;
        $oldAudit1->event = 'created';
        $oldAudit1->old_values = [];
        $oldAudit1->new_values = ['first_name' => $user->first_name];
        $oldAudit1->created_at = Carbon::now()->subDays(100);
        $oldAudit1->save();

        $oldAudit2 = new Audit;
        $oldAudit2->auditable_type = User::class;
        $oldAudit2->auditable_id = $user->id;
        $oldAudit2->event = 'updated';
        $oldAudit2->old_values = ['first_name' => $user->first_name];
        $oldAudit2->new_values = ['first_name' => 'Updated'];
        $oldAudit2->created_at = Carbon::now()->subDays(95);
        $oldAudit2->save();

        // Create a recent audit record (auditing happens automatically)
        $user->update(['first_name' => 'Recent Update']);

        // Since automatic auditing might not work in tests, manually create the recent audit
        $recentAudit = new Audit;
        $recentAudit->auditable_type = User::class;
        $recentAudit->auditable_id = $user->id;
        $recentAudit->event = 'updated';
        $recentAudit->old_values = ['first_name' => $user->first_name];
        $recentAudit->new_values = ['first_name' => 'Recent Update'];
        $recentAudit->created_at = Carbon::now();
        $recentAudit->save();

        // Verify we have 3 audit records (2 old, 1 recent)
        $this->assertEquals(3, Audit::count(), 'Expected 3 audit records (2 old, 1 recent)');

        // Run the clean command with 90 days threshold
        $this->artisan('audit:clean', ['--days' => 90])
            ->expectsOutput('Cleaning all audit records older than 90 days...')
            ->expectsOutput('Successfully deleted 2 audit records.')
            ->assertSuccessful();

        // Verify only the recent audit record remains
        $this->assertEquals(1, Audit::count());
        $this->assertEquals('updated', Audit::first()->event);

        // Handle new_values as either JSON string or array
        $newValues = Audit::first()->new_values;
        if (is_string($newValues)) {
            $newValues = json_decode($newValues, true);
        }
        $this->assertEquals('Recent Update', $newValues['first_name']);
    }

    /**
     * Test that the audit:clean command can target a specific model.
     */
    public function test_audit_clean_command_can_target_specific_model(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Create an old audit record
        $oldAudit = new Audit;
        $oldAudit->auditable_type = User::class;
        $oldAudit->auditable_id = $user->id;
        $oldAudit->event = 'created';
        $oldAudit->old_values = [];
        $oldAudit->new_values = ['first_name' => $user->first_name];
        $oldAudit->created_at = Carbon::now()->subDays(100);
        $oldAudit->save();

        // Verify the audit was created with old date
        $this->assertEquals(1, Audit::count());

        // Run the clean command targeting the User model
        $this->artisan('audit:clean', ['--days' => 90, '--model' => 'User'])
            ->expectsOutput('Cleaning audit records older than 90 days for model User...')
            ->expectsOutput('.')  // The command outputs dots during deletion
            ->expectsOutput('Successfully deleted 1 audit records.')
            ->assertSuccessful();

        // Verify the audit record was deleted
        $this->assertEquals(0, Audit::count());
    }

    /**
     * Test that the audit:clean command validates input.
     */
    public function test_audit_clean_command_validates_input(): void
    {
        // Test with invalid days parameter
        $this->artisan('audit:clean', ['--days' => 0])
            ->expectsOutput('The number of days must be greater than 0.')
            ->assertFailed();

        // Test with non-existent model
        $this->artisan('audit:clean', ['--days' => 90, '--model' => 'NonExistentModel'])
            ->expectsOutput('Model NonExistentModel not found!')
            ->assertFailed();
    }
}
