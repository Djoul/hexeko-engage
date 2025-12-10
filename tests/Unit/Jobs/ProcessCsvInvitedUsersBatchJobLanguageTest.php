<?php

namespace Tests\Unit\Jobs;

use App\Enums\Languages;
use App\Events\CsvInvitedUsersBatchProcessed;
use App\Jobs\ProcessCsvInvitedUsersBatchJob;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\User;
use App\Services\CsvImportTrackerService;
use App\Services\Models\InvitedUserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
#[Group('jobs')]
#[Group('language')]
class ProcessCsvInvitedUsersBatchJobLanguageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Event::fake();

        // Mock CsvImportTrackerService to avoid Redis connection
        $trackerMock = Mockery::mock(CsvImportTrackerService::class);
        $trackerMock->shouldReceive('updateBatchProgress')->andReturn(null);
        $this->app->instance(CsvImportTrackerService::class, $trackerMock);
    }

    #[Test]
    public function it_processes_user_with_valid_language_column(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $importId = 'import-lang-001';
        $batchNumber = 1;
        $email = 'user.french.'.uniqid().'@example.com';

        $rows = [
            [
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'email' => $email,
                'phone' => '+33123456789',
                'external_id' => 'EXT001',
                'language' => Languages::FRENCH,
            ],
        ];

        $job = new ProcessCsvInvitedUsersBatchJob(
            $rows,
            $financer->id,
            'user-123',
            $importId,
            $batchNumber
        );

        // Act
        $job->handle(app(InvitedUserService::class));

        // Assert - User was created with correct locale
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertEquals(Languages::FRENCH, $user->locale);

        // Assert - Financer pivot has correct language
        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $financer->id,
            'language' => Languages::FRENCH,
        ]);

        // Assert - Welcome email job was queued
        Queue::assertPushed(SendWelcomeEmailJob::class, 1);

        // Assert - Batch processed successfully
        Event::assertDispatched(CsvInvitedUsersBatchProcessed::class, function ($event): bool {
            return $event->processedCount === 1 &&
                   $event->failedCount === 0;
        });
    }

    #[Test]
    public function it_processes_multiple_users_with_different_languages(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $importId = 'import-lang-002';
        $batchNumber = 2;

        $rows = [
            [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'email' => 'john.'.uniqid().'@example.com',
                'phone' => '',
                'external_id' => '',
                'language' => Languages::ENGLISH,
            ],
            [
                'first_name' => 'Maria',
                'last_name' => 'Garcia',
                'email' => 'maria.'.uniqid().'@example.com',
                'phone' => '',
                'external_id' => '',
                'language' => Languages::SPANISH,
            ],
            [
                'first_name' => 'Hans',
                'last_name' => 'Mueller',
                'email' => 'hans.'.uniqid().'@example.com',
                'phone' => '',
                'external_id' => '',
                'language' => Languages::GERMAN,
            ],
        ];

        $job = new ProcessCsvInvitedUsersBatchJob(
            $rows,
            $financer->id,
            'user-123',
            $importId,
            $batchNumber
        );

        // Act
        $job->handle(app(InvitedUserService::class));

        // Assert - Each user has correct language in pivot
        $users = User::whereIn('email', [
            $rows[0]['email'],
            $rows[1]['email'],
            $rows[2]['email'],
        ])->get();

        $this->assertCount(3, $users);

        // Check that all expected languages are present (order-independent)
        $expectedLanguages = [Languages::ENGLISH, Languages::SPANISH, Languages::GERMAN];
        foreach ($expectedLanguages as $expectedLanguage) {
            $found = $users->first(function ($user) use ($financer, $expectedLanguage) {
                return $user->financers()
                    ->where('financer_id', $financer->id)
                    ->wherePivot('language', $expectedLanguage)
                    ->exists();
            });
            $this->assertNotNull($found, "Expected to find user with language {$expectedLanguage}");
        }

        // Assert - All email jobs queued
        Queue::assertPushed(SendWelcomeEmailJob::class, 3);

        // Assert - All processed successfully
        Event::assertDispatched(CsvInvitedUsersBatchProcessed::class, function ($event): bool {
            return $event->processedCount === 3 &&
                   $event->failedCount === 0;
        });
    }

    #[Test]
    public function it_processes_user_with_empty_language_column(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $importId = 'import-lang-003';
        $batchNumber = 3;
        $email = 'user.nolang.'.uniqid().'@example.com';

        $rows = [
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => $email,
                'phone' => '',
                'external_id' => '',
                'language' => '', // Empty language
            ],
        ];

        $job = new ProcessCsvInvitedUsersBatchJob(
            $rows,
            $financer->id,
            'user-123',
            $importId,
            $batchNumber
        );

        // Act
        $job->handle(app(InvitedUserService::class));

        // Assert - User was created
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);

        // Assert - Financer pivot has null language
        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $financer->id,
            'language' => null,
        ]);

        // Assert - Processed successfully
        Event::assertDispatched(CsvInvitedUsersBatchProcessed::class, function ($event): bool {
            return $event->processedCount === 1 &&
                   $event->failedCount === 0;
        });
    }

    #[Test]
    public function it_processes_user_without_language_column(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $importId = 'import-lang-004';
        $batchNumber = 4;
        $email = 'user.legacy.'.uniqid().'@example.com';

        $rows = [
            [
                'first_name' => 'Legacy',
                'last_name' => 'User',
                'email' => $email,
                'phone' => '',
                'external_id' => '',
                // No language column
            ],
        ];

        $job = new ProcessCsvInvitedUsersBatchJob(
            $rows,
            $financer->id,
            'user-123',
            $importId,
            $batchNumber
        );

        // Act
        $job->handle(app(InvitedUserService::class));

        // Assert - User was created
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);

        // Assert - Financer pivot has null language (backward compatibility)
        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $financer->id,
            'language' => null,
        ]);

        // Assert - Processed successfully
        Event::assertDispatched(CsvInvitedUsersBatchProcessed::class, function ($event): bool {
            return $event->processedCount === 1 &&
                   $event->failedCount === 0;
        });
    }

    #[Test]
    public function it_rejects_user_with_invalid_language_code(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $importId = 'import-lang-005';
        $batchNumber = 5;
        $validEmail = 'valid.'.uniqid().'@example.com';
        $invalidEmail = 'invalid.'.uniqid().'@example.com';

        $rows = [
            [
                'first_name' => 'Valid',
                'last_name' => 'User',
                'email' => $validEmail,
                'phone' => '',
                'external_id' => '',
                'language' => Languages::ENGLISH,
            ],
            [
                'first_name' => 'Invalid',
                'last_name' => 'User',
                'email' => $invalidEmail,
                'phone' => '',
                'external_id' => '',
                'language' => 'invalid-code', // Invalid language code
            ],
        ];

        $job = new ProcessCsvInvitedUsersBatchJob(
            $rows,
            $financer->id,
            'user-123',
            $importId,
            $batchNumber
        );

        // Act
        $job->handle(app(InvitedUserService::class));

        // Assert - Valid user was created
        $this->assertDatabaseHas('users', [
            'email' => $validEmail,
            'invitation_status' => 'pending',
        ]);

        // Assert - Invalid user was NOT created
        $this->assertDatabaseMissing('users', [
            'email' => $invalidEmail,
        ]);

        // Assert - Only one email job queued
        Queue::assertPushed(SendWelcomeEmailJob::class, 1);

        // Assert - Event shows one success, one failure
        Event::assertDispatched(CsvInvitedUsersBatchProcessed::class, function ($event): bool {
            return $event->processedCount === 1 &&
                   $event->failedCount === 1 &&
                   count($event->failedRows) === 1;
        });
    }

    #[Test]
    public function it_handles_all_supported_language_codes(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $importId = 'import-lang-006';
        $batchNumber = 6;

        // Test a subset of all 20 supported languages
        $testLanguages = [
            Languages::ENGLISH,      // en-GB
            Languages::FRENCH,       // fr-FR
            Languages::GERMAN,       // de-DE
            Languages::SPANISH,      // es-ES
            Languages::ITALIAN,      // it-IT
            Languages::PORTUGUESE,   // pt-PT
            Languages::DUTCH,        // nl-NL
            Languages::POLISH,       // pl-PL
        ];

        $rows = [];
        foreach ($testLanguages as $index => $language) {
            $rows[] = [
                'first_name' => "User{$index}",
                'last_name' => "Test{$index}",
                'email' => "user{$index}.".uniqid().'@example.com',
                'phone' => '',
                'external_id' => '',
                'language' => $language,
            ];
        }

        $job = new ProcessCsvInvitedUsersBatchJob(
            $rows,
            $financer->id,
            'user-123',
            $importId,
            $batchNumber
        );

        // Act
        $job->handle(app(InvitedUserService::class));

        // Assert - All users were created with correct languages
        $users = User::whereIn('email', array_column($rows, 'email'))->get();
        $this->assertCount(count($testLanguages), $users);

        // Map rows by email to get expected language for each user
        $rowsByEmail = [];
        foreach ($rows as $row) {
            $rowsByEmail[$row['email']] = $row;
        }

        foreach ($users as $user) {
            $expectedLanguage = $rowsByEmail[$user->email]['language'];
            $this->assertDatabaseHas('financer_user', [
                'user_id' => $user->id,
                'financer_id' => $financer->id,
                'language' => $expectedLanguage,
            ]);
        }

        // Assert - All processed successfully
        Event::assertDispatched(CsvInvitedUsersBatchProcessed::class, function ($event) use ($testLanguages): bool {
            return $event->processedCount === count($testLanguages) &&
                   $event->failedCount === 0;
        });
    }
}
