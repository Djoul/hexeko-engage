<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Models;

use App\Enums\Security\AuthorizationMode;
use App\Integrations\Survey\Models\Answer;
use App\Integrations\Survey\Models\Submission;
use App\Integrations\Survey\Models\Survey;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
#[Group('submission')]
class SubmissionTest extends TestCase
{
    use DatabaseTransactions;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financer = ModelFactory::createFinancer();

        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$this->financer->id],
            [$this->financer->division_id],
            [],
            $this->financer->id  // Set current financer for global scopes
        );
    }

    #[Test]
    public function it_uses_uuid_as_primary_key(): void
    {
        $submission = new Submission;

        $this->assertTrue($submission->getIncrementing() === false);
        $this->assertEquals('string', $submission->getKeyType());
    }

    #[Test]
    public function it_can_create_a_submission(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'started_at' => now()->subHours(2),
            'completed_at' => null,
        ]);

        // Assert
        $this->assertInstanceOf(Submission::class, $submission);
        $this->assertDatabaseHas('int_survey_submissions', [
            'id' => $submission->id,
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
        ]);
    }

    #[Test]
    public function it_can_create_a_completed_submission(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $submission = Submission::factory()->completed()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
        ]);

        // Assert
        $this->assertInstanceOf(Submission::class, $submission);
        $this->assertNotNull($submission->completed_at);
        $this->assertNotNull($submission->started_at);
        $this->assertGreaterThanOrEqual($submission->started_at, $submission->completed_at);
    }

    #[Test]
    public function it_can_create_an_in_progress_submission(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $submission = Submission::factory()->inProgress()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
        ]);

        // Assert
        $this->assertInstanceOf(Submission::class, $submission);
        $this->assertNull($submission->completed_at);
        $this->assertNotNull($submission->started_at);
    }

    #[Test]
    public function it_belongs_to_a_survey(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(Survey::class, $submission->survey);
        $this->assertEquals($survey->id, $submission->survey->id);
    }

    #[Test]
    public function it_belongs_to_a_user(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $submission->user);
        $this->assertEquals($user->id, $submission->user->id);
    }

    #[Test]
    public function it_can_have_many_answers(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
        ]);

        // Act
        $answer1 = Answer::factory()->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
        ]);
        $answer2 = Answer::factory()->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
        ]);

        // Assert
        /** @var Collection<int, Answer> $answers */
        $answers = $submission->answers;
        $this->assertCount(2, $answers);
        $this->assertTrue($answers->contains('id', $answer1->id));
        $this->assertTrue($answers->contains('id', $answer2->id));
    }

    #[Test]
    public function it_casts_dates_correctly(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $startedAt = now()->subHours(3);
        $completedAt = now()->subHours(1);

        // Act
        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);

        // Assert
        $this->assertInstanceOf(Carbon::class, $submission->started_at);
        $this->assertInstanceOf(Carbon::class, $submission->completed_at);
        $this->assertEquals($startedAt->format('Y-m-d H:i:s'), $submission->started_at->format('Y-m-d H:i:s'));
        $this->assertEquals($completedAt->format('Y-m-d H:i:s'), $submission->completed_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_can_have_nullable_completed_at(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'started_at' => now(),
            'completed_at' => null,
        ]);

        // Assert
        $this->assertNull($submission->completed_at);
    }

    #[Test]
    public function it_uses_soft_deletes(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
        ]);

        // Act
        $submission->delete();

        // Assert
        $this->assertSoftDeleted('int_survey_submissions', ['id' => $submission->id]);
        $this->assertNull(Submission::find($submission->id));
        $this->assertNotNull(Submission::withTrashed()->find($submission->id));
    }

    #[Test]
    public function it_has_auditable_trait(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
        ]);

        // Assert
        $this->assertTrue(method_exists($submission, 'audits'));
        $this->assertTrue(method_exists($submission, 'getAuditEvents'));
    }

    #[Test]
    public function it_can_scope_submissions_by_survey(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();

        $survey1 = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey 1'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $survey2 = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey 2'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey1->id,
        ]);

        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user2->id,
            'survey_id' => $survey2->id,
        ]);

        // Act
        $survey1Submissions = Submission::query()->where('survey_id', $survey1->id)->get();

        // Assert
        $this->assertCount(1, $survey1Submissions);
        $this->assertTrue($survey1Submissions->contains('survey_id', $survey1->id));
    }

    #[Test]
    public function it_can_scope_submissions_by_user(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();

        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey->id,
        ]);

        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user2->id,
            'survey_id' => $survey->id,
        ]);

        // Act
        $user1Submissions = Submission::query()->where('user_id', $user1->id)->get();

        // Assert
        $this->assertCount(1, $user1Submissions);
        $this->assertTrue($user1Submissions->contains('user_id', $user1->id));
    }

    #[Test]
    public function it_can_scope_completed_submissions(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        Submission::factory()->completed()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
        ]);

        Submission::factory()->inProgress()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
        ]);

        // Act
        $completedSubmissions = Submission::query()->whereNotNull('completed_at')->get();

        // Assert
        $this->assertCount(1, $completedSubmissions);
        $this->assertNotNull($completedSubmissions->first()->completed_at);
    }

    #[Test]
    public function it_can_scope_in_progress_submissions(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        Submission::factory()->completed()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
        ]);

        Submission::factory()->inProgress()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
        ]);

        // Act
        $inProgressSubmissions = Submission::query()->whereNull('completed_at')->get();

        // Assert
        $this->assertCount(1, $inProgressSubmissions);
        $this->assertNull($inProgressSubmissions->first()->completed_at);
    }

    // ==================== HasCreator Trait Tests ====================

    #[Test]
    public function it_automatically_sets_created_by_when_authenticated(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($user);
        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
        ]);

        // Assert
        $this->assertEquals($user->id, $submission->created_by);
        $this->assertDatabaseHas('int_survey_submissions', [
            'id' => $submission->id,
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_set_created_by_when_not_authenticated(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::logout();
        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'survey_id' => $survey->id,
        ]);

        // Assert
        $this->assertNull($submission->created_by);
        $this->assertDatabaseHas('int_survey_submissions', [
            'id' => $submission->id,
            'created_by' => null,
        ]);
    }

    #[Test]
    public function it_sets_updated_by_when_updating(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        Auth::login($creator);
        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $creator->id,
            'survey_id' => $survey->id,
        ]);

        // Act
        Auth::login($updater);
        $submission->update([
            'completed_at' => now(),
        ]);

        // Assert
        $this->assertEquals($creator->id, $submission->created_by);
        $this->assertEquals($updater->id, $submission->updated_by);
        $this->assertDatabaseHas('int_survey_submissions', [
            'id' => $submission->id,
            'created_by' => $creator->id,
            'updated_by' => $updater->id,
        ]);
    }

    #[Test]
    public function it_has_creator_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        Auth::login($creator);
        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $creator->id,
            'survey_id' => $survey->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $submission->creator);
        $this->assertEquals($creator->id, $submission->creator->id);
        $this->assertEquals($creator->name, $submission->creator->name);
    }

    #[Test]
    public function it_has_updater_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        Auth::login($creator);
        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $creator->id,
            'survey_id' => $survey->id,
        ]);

        // Act
        Auth::login($updater);
        $submission->update([
            'completed_at' => now(),
        ]);

        // Assert
        $this->assertInstanceOf(User::class, $submission->updater);
        $this->assertEquals($updater->id, $submission->updater->id);
        $this->assertEquals($updater->name, $submission->updater->name);
    }

    #[Test]
    public function it_can_check_if_was_created_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        Auth::login($creator);
        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $creator->id,
            'survey_id' => $survey->id,
        ]);

        // Act & Assert
        $this->assertTrue($submission->wasCreatedBy($creator));
        $this->assertFalse($submission->wasCreatedBy($otherUser));
        $this->assertFalse($submission->wasCreatedBy(null));
    }

    #[Test]
    public function it_can_check_if_was_updated_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        Auth::login($creator);
        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $creator->id,
            'survey_id' => $survey->id,
        ]);

        // Act
        Auth::login($updater);
        $submission->update([
            'completed_at' => now(),
        ]);

        // Assert
        $this->assertTrue($submission->wasUpdatedBy($updater));
        $this->assertFalse($submission->wasUpdatedBy($creator));
        $this->assertFalse($submission->wasUpdatedBy($otherUser));
        $this->assertFalse($submission->wasUpdatedBy(null));
    }
}
