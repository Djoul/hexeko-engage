<?php

namespace Tests\Unit\Services\ActivityLogs;

use App\Models\Division;
use App\Models\Financer;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('financer')]

#[Group('audit')]
class FinancerActivityLogTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }
    }

    #[Test]
    public function it_logs_when_a_financer_is_created(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::create([
            'name' => 'ABC Finance',
            'division_id' => $division->id,
            'timezone' => $division->timezone,
            'company_number' => 'TEST123456',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'financer',
            'description' => 'created',
            'subject_id' => $financer->id,
            'subject_type' => Financer::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_financer_is_updated(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::create([
            'name' => 'ABC Finance',
            'division_id' => $division->id,
            'timezone' => $division->timezone,
            'company_number' => 'TEST123456',
        ]);

        $financer->update(['name' => 'Updated Financer']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'financer',
            'description' => 'updated',
            'subject_id' => $financer->id,
            'subject_type' => Financer::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_financer_is_deleted(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::create([
            'name' => 'ABC Finance',
            'division_id' => $division->id,
            'timezone' => $division->timezone,
            'company_number' => 'TEST123456',
        ]);

        $financer->delete();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'financer',
            'description' => 'deleted',
            'subject_id' => $financer->id,
            'subject_type' => Financer::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_user_is_attached_to_financer(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::create([
            'name' => 'ABC Finance',
            'division_id' => $division->id,
            'timezone' => $division->timezone,
            'company_number' => 'TEST123456',
        ]);
        $user = User::factory()->create();

        $financer->attachUser($user->id);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'financer',
            'description' => "Utilisateur ID {$user->id} attaché au financier {$financer->name}",
            'subject_id' => $financer->id,
            'subject_type' => Financer::class,
        ]);
    }
}
