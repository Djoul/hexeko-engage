<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Jobs;

use App\Enums\IDP\RoleDefaults;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Integrations\Survey\Jobs\SyncSurveyUsersJob;
use App\Integrations\Survey\Models\Survey;
use App\Models\Segment;
use App\Models\User;
use Database\Factories\FinancerFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('survey')]
class SyncSurveyUsersJobTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_attaches_financer_users_when_no_segment_is_defined(): void
    {
        $financer = resolve(FinancerFactory::class)->create();

        Context::add('financer_id', $financer->id);
        Context::add('accessible_financers', [$financer->id]);

        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            $financer->users()->attach($user->id, [
                'active' => true,
                'role' => RoleDefaults::BENEFICIARY,
                'from' => now(),
            ]);
        }

        $survey = Survey::factory()
            ->withFinancer($financer)
            ->create([
                'segment_id' => null,
                'status' => SurveyStatusEnum::DRAFT,
            ]);

        $job = new SyncSurveyUsersJob($survey);
        $job->handle();

        $survey->refresh()->load('users');

        $this->assertEqualsCanonicalizing(
            $users->pluck('id')->all(),
            $survey->users->pluck('id')->all()
        );

        $this->assertSame($users->count(), $survey->users_count);

        foreach ($users as $user) {
            $this->assertDatabaseHas('int_survey_survey_user', [
                'survey_id' => $survey->id,
                'user_id' => $user->id,
            ]);
        }
    }

    #[Test]
    public function it_syncs_segment_users_and_removes_stale_links(): void
    {
        $financer = resolve(FinancerFactory::class)->create();

        Context::add('financer_id', $financer->id);
        Context::add('accessible_financers', [$financer->id]);

        $segmentUsers = User::factory()->count(2)->create();
        $staleUser = User::factory()->create();

        foreach ($segmentUsers->merge([$staleUser]) as $user) {
            $financer->users()->attach($user->id, [
                'active' => true,
                'role' => RoleDefaults::BENEFICIARY,
                'from' => now(),
            ]);
        }

        $segment = Segment::factory()->create([
            'financer_id' => $financer->id,
            'name' => 'Test segment',
            'filters' => [],
        ]);

        foreach ($segmentUsers as $user) {
            $segment->users()->attach($user->id);
        }

        $survey = Survey::factory()
            ->withFinancer($financer)
            ->create([
                'segment_id' => $segment->id,
                'status' => SurveyStatusEnum::DRAFT,
            ]);

        $survey->users()->attach($staleUser->id);

        $job = new SyncSurveyUsersJob($survey);
        $job->handle();

        $survey->refresh()->load('users');

        $this->assertEqualsCanonicalizing(
            $segmentUsers->pluck('id')->all(),
            $survey->users->pluck('id')->all()
        );

        $this->assertSame($segmentUsers->count(), $survey->users_count);

        $this->assertDatabaseMissing('int_survey_survey_user', [
            'survey_id' => $survey->id,
            'user_id' => $staleUser->id,
        ]);
    }
}
