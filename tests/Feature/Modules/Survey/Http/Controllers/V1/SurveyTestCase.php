<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Survey\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Integrations\Survey\Models\Survey;
use App\Models\Financer;
use App\Models\Team;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use Tests\ProtectedRouteTestCase;

#[Group('survey')]
abstract class SurveyTestCase extends ProtectedRouteTestCase
{
    protected Team $team;

    protected Financer $financer;

    /**
     * Set up the test environment.
     */
    final protected function setUp(): void
    {
        parent::setUp();

        // Use local disk for testing instead of S3
        config(['media-library.disk_name' => 'public']);

        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $this->financer = $this->auth->financers()->first();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('accessible_divisions', [$this->financer->division_id]);
        Context::add('financer_id', $this->financer->id);
    }

    /**
     * Helper method to attach users to a survey
     *
     * @param  Survey  $survey
     * @param  array<int, string>  $userIds
     */
    protected function attachUsersToSurvey($survey, array $userIds): void
    {
        $survey->users()->attach($userIds);
    }
}
