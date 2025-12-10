<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions\Me\Submissions;

use App\Integrations\Survey\Actions\Me\Submissions\CreateSubmissionAction;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Models\Submission;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
#[Group('submission')]
#[Group('action')]
class CreateSubmissionActionTest extends TestCase
{
    use DatabaseTransactions;

    private CreateSubmissionAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CreateSubmissionAction;
        $this->financer = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_creates_a_submission_successfully(): void
    {
        // Arrange
        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $this->actingAs($user);

        $data = [
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ];

        $submission = new Submission;

        // Act
        $result = $this->action->execute($submission, $data);

        // Assert
        $this->assertInstanceOf(Submission::class, $result);
        $this->assertNotNull($result->id);
        $this->assertEquals($survey->id, $result->survey_id);
        $this->assertEquals($this->financer->id, $result->financer_id);
        $this->assertEquals($user->id, $result->user_id);
        $this->assertNotNull($result->started_at);
        $this->assertNull($result->completed_at);

        // Check database persistence
        $this->assertDatabaseHas('int_survey_submissions', [
            'id' => $result->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);
    }

    #[Test]
    public function it_sets_started_at_automatically(): void
    {
        // Arrange
        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $this->actingAs($user);

        $data = [
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ];

        $submission = new Submission;
        $beforeCreate = now()->subSecond();

        // Act
        $result = $this->action->execute($submission, $data);

        // Assert
        $this->assertNotNull($result->started_at);
        $this->assertTrue($result->started_at->greaterThanOrEqualTo($beforeCreate));
        $this->assertTrue($result->started_at->lessThanOrEqualTo(now()));
    }

    #[Test]
    public function it_sets_user_id_from_authenticated_user(): void
    {
        // Arrange
        $authenticatedUser = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $this->actingAs($authenticatedUser);

        $data = [
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ];

        $submission = new Submission;

        // Act
        $result = $this->action->execute($submission, $data);

        // Assert
        $this->assertEquals($authenticatedUser->id, $result->user_id);
    }

    #[Test]
    public function it_creates_submission_with_null_completed_at(): void
    {
        // Arrange
        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $this->actingAs($user);

        $data = [
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ];

        $submission = new Submission;

        // Act
        $result = $this->action->execute($submission, $data);

        // Assert
        $this->assertNull($result->completed_at);
    }

    #[Test]
    public function it_creates_multiple_submissions_for_same_user(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey1 = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $survey2 = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $this->actingAs($user);

        $data1 = [
            'survey_id' => $survey1->id,
            'financer_id' => $this->financer->id,
        ];

        $data2 = [
            'survey_id' => $survey2->id,
            'financer_id' => $this->financer->id,
        ];

        $submission1 = new Submission;
        $submission2 = new Submission;

        // Act
        $result1 = $this->action->execute($submission1, $data1);
        $result2 = $this->action->execute($submission2, $data2);

        // Assert
        $this->assertNotEquals($result1->id, $result2->id);
        $this->assertEquals($user->id, $result1->user_id);
        $this->assertEquals($user->id, $result2->user_id);
        $this->assertEquals($survey1->id, $result1->survey_id);
        $this->assertEquals($survey2->id, $result2->survey_id);
    }

    #[Test]
    public function it_returns_refreshed_submission_instance(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $this->actingAs($user);

        $data = [
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ];

        $submission = new Submission;

        // Act
        $result = $this->action->execute($submission, $data);

        // Assert
        $this->assertTrue($result->exists);
        $this->assertNotNull($result->created_at);
        $this->assertNotNull($result->updated_at);
    }

    #[Test]
    public function it_creates_submission_with_proper_relationships(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $this->actingAs($user);

        $data = [
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ];

        $submission = new Submission;

        // Act
        $result = $this->action->execute($submission, $data);

        // Load relationships
        $result->load(['survey', 'user', 'financer']);

        // Assert
        $this->assertInstanceOf(User::class, $result->user);
        $this->assertEquals($user->id, $result->user->id);

        $this->assertNotNull($result->survey);
        $this->assertEquals($survey->id, $result->survey->id);

        $this->assertNotNull($result->financer);
        $this->assertEquals($this->financer->id, $result->financer->id);
    }
}
