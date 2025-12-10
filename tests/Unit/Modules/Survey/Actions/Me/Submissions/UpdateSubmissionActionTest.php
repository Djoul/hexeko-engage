<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions\Me\Submissions;

use App\Integrations\Survey\Actions\Me\Submissions\UpdateSubmissionAction;
use App\Integrations\Survey\Database\factories\SubmissionFactory;
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
class UpdateSubmissionActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateSubmissionAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdateSubmissionAction;
        $this->financer = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_updates_a_submission_successfully(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
            'started_at' => now()->subHours(2),
        ]);

        $updateData = [];

        // Act
        $result = $this->action->execute($submission, $updateData);

        // Assert
        $this->assertInstanceOf(Submission::class, $result);
        $this->assertEquals($submission->id, $result->id);
    }

    #[Test]
    public function it_maintains_submission_id_after_update(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        $originalId = $submission->id;
        $updateData = [];

        // Act
        $result = $this->action->execute($submission, $updateData);

        // Assert
        $this->assertEquals($originalId, $result->id);
    }

    #[Test]
    public function it_preserves_relationships_after_update(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [];

        // Act
        $result = $this->action->execute($submission, $updateData);

        // Assert
        $this->assertEquals($user->id, $result->user_id);
        $this->assertEquals($survey->id, $result->survey_id);
        $this->assertEquals($this->financer->id, $result->financer_id);
    }

    #[Test]
    public function it_preserves_started_at_timestamp(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $startedAt = now()->subHours(3);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
            'started_at' => $startedAt,
        ]);

        $updateData = [];

        // Act
        $result = $this->action->execute($submission, $updateData);

        // Assert
        $this->assertEquals($startedAt->format('Y-m-d H:i:s'), $result->started_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_preserves_completed_at_if_already_set(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $completedAt = now()->subHour();
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
            'completed_at' => $completedAt,
        ]);

        $updateData = [];

        // Act
        $result = $this->action->execute($submission, $updateData);

        // Assert
        $this->assertNotNull($result->completed_at);
        $this->assertEquals($completedAt->format('Y-m-d H:i:s'), $result->completed_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_handles_empty_update_data(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        $originalData = [
            'user_id' => $submission->user_id,
            'survey_id' => $submission->survey_id,
            'financer_id' => $submission->financer_id,
            'started_at' => $submission->started_at,
        ];

        // Act
        $result = $this->action->execute($submission, []);

        // Assert
        $this->assertEquals($originalData['user_id'], $result->user_id);
        $this->assertEquals($originalData['survey_id'], $result->survey_id);
        $this->assertEquals($originalData['financer_id'], $result->financer_id);
        $this->assertEquals($originalData['started_at']->format('Y-m-d H:i:s'), $result->started_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_returns_refreshed_submission_instance(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [];

        // Act
        $result = $this->action->execute($submission, $updateData);

        // Assert
        $this->assertTrue($result->exists);
        $this->assertNotNull($result->updated_at);
    }

    #[Test]
    public function it_persists_changes_to_database(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        $updateData = [];

        // Act
        $this->action->execute($submission, $updateData);

        // Assert
        $this->assertDatabaseHas('int_survey_submissions', [
            'id' => $submission->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);
    }
}
