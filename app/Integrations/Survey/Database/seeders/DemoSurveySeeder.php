<?php

namespace App\Integrations\Survey\Database\seeders;

use App\Integrations\Survey\Enums\QuestionTypeEnum;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Integrations\Survey\Models\Answer;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\QuestionOption;
use App\Integrations\Survey\Models\Submission;
use App\Integrations\Survey\Models\Survey;
use App\Integrations\Survey\Models\Theme;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class DemoSurveySeeder extends Seeder
{
    use WithoutModelEvents;

    protected ?string $financerId = null;

    public function __construct(?string $financerId = null)
    {
        $this->financerId = $financerId;
    }

    public function run(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $financerId = $this->financerId
            ?? (string) $this->command?->ask('Financer ID (leave blank to seed for all financers)');

        $financers = $financerId !== '' && $financerId !== '0'
            ? Financer::query()->where('id', $financerId)->get()
            : Financer::all();

        if ($financers->isEmpty()) {
            $this->command?->error('No financers found!');

            return;
        }

        foreach ($financers as $financer) {
            $this->command?->info("Seeding surveys for financer: {$financer->name}");
            $this->seedSurveysForFinancer($financer);
        }

        $this->command?->info('Survey seeding completed!');
    }

    protected function seedSurveysForFinancer(Financer $financer): void
    {
        // Get users for this financer
        $users = User::query()
            ->whereHas('financers', function ($q) use ($financer): void {
                $q->where('financers.id', $financer->id)
                    ->where('financer_user.active', true);
            })
            ->get();

        if ($users->isEmpty()) {
            $this->command?->warn("No users found for financer {$financer->name}. Skipping.");

            return;
        }

        $this->command?->info("Found {$users->count()} users for financer {$financer->name}");

        // Create 3 surveys with different statuses
        $surveysData = [
            [
                'title' => [
                    'fr-BE' => 'Enquête de Satisfaction Employés',
                    'nl-BE' => 'Medewerkerstevredenheidsonderzoek',
                    'en-GB' => 'Employee Satisfaction Survey',
                ],
                'description' => [
                    'fr-BE' => 'Aidez-nous à améliorer votre expérience au travail',
                    'nl-BE' => 'Help ons uw werkervaring te verbeteren',
                    'en-GB' => 'Help us improve your work experience',
                ],
                'status' => SurveyStatusEnum::PUBLISHED,
                'starts_at' => now()->subDays(7),
                'ends_at' => now()->addDays(23),
            ],
            [
                'title' => [
                    'fr-BE' => 'Évaluation du Leadership',
                    'nl-BE' => 'Leiderschapsevaluatie',
                    'en-GB' => 'Leadership Assessment',
                ],
                'description' => [
                    'fr-BE' => 'Partagez votre feedback sur le management',
                    'nl-BE' => 'Deel uw feedback over het management',
                    'en-GB' => 'Share your feedback on management',
                ],
                'status' => SurveyStatusEnum::PUBLISHED,
                'starts_at' => now()->subDays(60),
                'ends_at' => now()->subDays(30),
            ],
            [
                'title' => [
                    'fr-BE' => 'Enquête Bien-être au Travail',
                    'nl-BE' => 'Welzijn op het werk enquête',
                    'en-GB' => 'Workplace Wellness Survey',
                ],
                'description' => [
                    'fr-BE' => 'Comment vous sentez-vous au travail ?',
                    'nl-BE' => 'Hoe voelt u zich op het werk?',
                    'en-GB' => 'How do you feel at work?',
                ],
                'status' => SurveyStatusEnum::PUBLISHED,
                'starts_at' => now()->addDays(7),
                'ends_at' => now()->addDays(37),
            ],
        ];

        foreach ($surveysData as $surveyData) {
            $survey = $this->createSurvey($financer, $surveyData);
            $this->createQuestionsForSurvey($survey);
            $this->attachUsersToSurvey($survey, $users);

            // Only create submissions for surveys that have started
            $this->createSubmissionsForSurvey($survey, $users);

            $survey->update([
                'users_count' => $survey->users()->count(),
                'submissions_count' => $survey->submissions()->withoutGlobalScopes()->count(),
            ]);
        }
    }

    protected function createSurvey(Financer $financer, array $data): Survey
    {
        $survey = Survey::create([
            'id' => Uuid::uuid7()->toString(),
            'financer_id' => $financer->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'welcome_message' => [
                'fr-BE' => 'Bienvenue à ce sondage',
                'nl-BE' => 'Welkom bij deze enquête',
                'en-GB' => 'Welcome to this survey',
            ],
            'thank_you_message' => [
                'fr-BE' => 'Merci pour votre participation',
                'nl-BE' => 'Bedankt voor uw deelname',
                'en-GB' => 'Thank you for your participation',
            ],
            'status' => $data['status'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'settings' => [],
        ]);

        $this->command?->info("Created survey: {$data['title']['en-GB']}");

        return $survey;
    }

    protected function createQuestionsForSurvey(Survey $survey): void
    {
        $questionsData = [
            [
                'text' => [
                    'fr-BE' => 'Comment évaluez-vous votre satisfaction générale ?',
                    'nl-BE' => 'Hoe beoordeelt u uw algemene tevredenheid?',
                    'en-GB' => 'How would you rate your overall satisfaction?',
                ],
                'type' => QuestionTypeEnum::SCALE,
                'metadata' => ['min' => 1, 'max' => 5],
            ],
            [
                'text' => [
                    'fr-BE' => 'Que pourrions-nous améliorer ?',
                    'nl-BE' => 'Wat kunnen we verbeteren?',
                    'en-GB' => 'What could we improve?',
                ],
                'type' => QuestionTypeEnum::TEXT,
                'metadata' => [],
            ],
            [
                'text' => [
                    'fr-BE' => 'Recommanderiez-vous notre entreprise ?',
                    'nl-BE' => 'Zou u ons bedrijf aanbevelen?',
                    'en-GB' => 'Would you recommend our company?',
                ],
                'type' => QuestionTypeEnum::SINGLE_CHOICE,
                'metadata' => [
                    'options' => [
                        ['fr-BE' => 'Oui', 'nl-BE' => 'Ja', 'en-GB' => 'Yes'],
                        ['fr-BE' => 'Non', 'nl-BE' => 'Nee', 'en-GB' => 'No'],
                        ['fr-BE' => 'Peut-être', 'nl-BE' => 'Misschien', 'en-GB' => 'Maybe'],
                    ],
                ],
            ],
            [
                'text' => [
                    'fr-BE' => 'Quels avantages appréciez-vous le plus ?',
                    'nl-BE' => 'Welke voordelen waardeert u het meest?',
                    'en-GB' => 'Which benefits do you appreciate most?',
                ],
                'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
                'metadata' => [
                    'options' => [
                        ['fr-BE' => 'Flexibilité horaire', 'nl-BE' => 'Werkflexibiliteit', 'en-GB' => 'Flexible hours'],
                        ['fr-BE' => 'Télétravail', 'nl-BE' => 'Thuiswerken', 'en-GB' => 'Remote work'],
                        ['fr-BE' => 'Formation continue', 'nl-BE' => 'Continue opleiding', 'en-GB' => 'Continuous training'],
                        ['fr-BE' => 'Avantages sociaux', 'nl-BE' => 'Sociale voordelen', 'en-GB' => 'Social benefits'],
                    ],
                ],
            ],
            [
                'text' => [
                    'fr-BE' => 'Comment évaluez-vous la communication interne ?',
                    'nl-BE' => 'Hoe beoordeelt u de interne communicatie?',
                    'en-GB' => 'How would you rate internal communication?',
                ],
                'type' => QuestionTypeEnum::SCALE,
                'metadata' => ['min' => 1, 'max' => 5],
            ],
        ];

        $createdCount = 0;

        foreach ($questionsData as $index => $questionData) {
            $question = Question::create([
                'id' => Uuid::uuid7()->toString(),
                'financer_id' => $survey->financer_id,
                'text' => $questionData['text'],
                'help_text' => [
                    'fr-BE' => '',
                    'nl-BE' => '',
                    'en-GB' => '',
                ],
                'type' => $questionData['type'],
                'metadata' => $questionData['metadata'],
                'is_default' => false,
                'theme_id' => Theme::query()->withoutGlobalScopes()->where('financer_id', $survey->financer_id)->inRandomOrder()->first()->id,
            ]);

            // Attach question to survey via polymorphic relation
            $survey->questions()->withoutGlobalScopes()->attach($question->id, [
                'position' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create QuestionOption entities for SINGLE_CHOICE and MULTIPLE_CHOICE
            if (in_array($questionData['type'], [QuestionTypeEnum::SINGLE_CHOICE, QuestionTypeEnum::MULTIPLE_CHOICE])) {
                $options = $questionData['metadata']['options'] ?? [];
                foreach ($options as $optionIndex => $optionText) {
                    QuestionOption::create([
                        'id' => Uuid::uuid7()->toString(),
                        'question_id' => $question->id,
                        'text' => $optionText,
                        'position' => $optionIndex + 1,
                    ]);
                }
            }

            $createdCount++;
        }

        $this->command?->info("  Created {$createdCount} questions");
    }

    protected function attachUsersToSurvey(Survey $survey, $users): void
    {
        $now = now();

        $users->chunk(1000)->each(function ($chunk) use ($survey, $now): void {
            $data = $chunk->map(fn (User $user): array => [
                'survey_id' => $survey->id,
                'user_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();

            DB::table('int_survey_survey_user')->insert($data);

            $this->command?->info("  Attached {$chunk->count()} users to survey");
        });

        $survey->update([
            'users_count' => $survey->users()->count(),
        ]);

        $this->command?->info("  Total attached {$users->count()} users to survey");
    }

    protected function createSubmissionsForSurvey(Survey $survey, $users): void
    {
        // Load questions with their options, without global scopes (HasFinancerScope would filter them out during seeding)
        $questions = $survey->questions()->withoutGlobalScopes()->with('options')->get();
        $totalQuestions = $questions->count();

        if ($totalQuestions === 0) {
            $this->command?->warn('  No questions found for survey. Skipping submissions.');

            return;
        }

        // Randomly select 75-95% of users to respond
        $responseRate = rand(75, 95) / 100;
        $respondingUserCount = max(1, (int) round($users->count() * $responseRate));
        $respondingUsers = $respondingUserCount >= $users->count()
            ? $users
            : $users->random($respondingUserCount);

        $completedCount = 0;
        $partialCount = 0;
        $totalAnswersCreated = 0;

        foreach ($respondingUsers as $user) {
            // 80% complete the survey, 20% only partial
            $willComplete = rand(1, 100) <= 80;

            // Determine how many questions they'll answer
            $numberOfQuestionsToAnswer = $willComplete
                ? $totalQuestions
                : rand(
                    max(1, (int) ceil($totalQuestions / 2)),
                    max(1, $totalQuestions - 1)
                );

            $startedAt = ($survey->starts_at ?? now())->copy()->addHours(rand(1, 100));

            // Create submission (initially without completed_at)
            $submission = Submission::create([
                'id' => Uuid::uuid7()->toString(),
                'financer_id' => $survey->financer_id,
                'user_id' => $user->id,
                'survey_id' => $survey->id,
                'started_at' => $startedAt,
                'completed_at' => null,
                'created_at' => $startedAt,
                'updated_at' => $startedAt,
            ]);

            // Create answers
            $answeredQuestions = $questions->take($numberOfQuestionsToAnswer);
            $answersCreated = 0;

            foreach ($answeredQuestions as $question) {
                $answerData = $this->generateAnswerData($question);

                Answer::create([
                    'id' => Uuid::uuid7()->toString(),
                    'user_id' => $user->id,
                    'submission_id' => $submission->id,
                    'question_id' => $question->id,
                    'answer' => $answerData,
                ]);

                $answersCreated++;
                $totalAnswersCreated++;
            }

            // Mark as completed ONLY if all questions were answered
            if ($answersCreated === $totalQuestions) {
                $submission->update([
                    'completed_at' => $startedAt->copy()->addMinutes(rand(5, 30)),
                ]);
                $completedCount++;
            } else {
                $partialCount++;
            }
        }

        $this->command?->info("  Created {$totalAnswersCreated} answers in {$completedCount} complete and {$partialCount} partial submissions");
    }

    protected function generateAnswerData(Question $question): array
    {
        // Get the enum value as string for comparison
        $questionType = $question->type instanceof QuestionTypeEnum
            ? $question->type->value
            : $question->type;

        return match ($questionType) {
            QuestionTypeEnum::SCALE => [
                'value' => rand($question->metadata['min'] ?? 1, $question->metadata['max'] ?? 5),
            ],
            QuestionTypeEnum::TEXT => [
                'value' => fake()->sentence(rand(5, 20)),
            ],
            QuestionTypeEnum::SINGLE_CHOICE => [
                'value' => $this->getRandomOptionId($question),
            ],
            QuestionTypeEnum::MULTIPLE_CHOICE => [
                'value' => $this->getRandomOptionIds($question),
            ],
            default => ['value' => null],
        };
    }

    /**
     * Get a random option ID (UUID) for single choice questions
     */
    protected function getRandomOptionId(Question $question): ?string
    {
        $options = $question->options;

        if ($options->isEmpty()) {
            return null;
        }

        return $options->random()->id;
    }

    /**
     * Get multiple random option IDs (UUIDs) for multiple choice questions
     */
    protected function getRandomOptionIds(Question $question): array
    {
        $options = $question->options;

        if ($options->isEmpty()) {
            return [];
        }

        $numberOfSelections = rand(1, min(3, $options->count()));

        return $options->random($numberOfSelections)->pluck('id')->toArray();
    }
}
