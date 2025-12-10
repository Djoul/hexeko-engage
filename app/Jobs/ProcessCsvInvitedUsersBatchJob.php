<?php

namespace App\Jobs;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Languages;
use App\Events\CsvInvitedUsersBatchFailed;
use App\Events\CsvInvitedUsersBatchProcessed;
use App\Models\User;
use App\Services\CsvImportTrackerService;
use App\Services\Models\InvitedUserService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Log;
use Throwable;

class ProcessCsvInvitedUsersBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    public function __construct(
        public readonly array $rows,
        public readonly string $financerId,
        public readonly string $userId,
        public readonly string $importId,
        public readonly int $batchNumber
    ) {}

    public function handle(InvitedUserService $invitedUserService): void
    {
        // Clear Eloquent model cache to prevent memory accumulation
        Model::clearBootedModels();

        // Clear query log to prevent memory leaks
        \DB::disableQueryLog();

        // Force garbage collection
        gc_collect_cycles();

        Log::info('Processing batch of invited users', [
            'import_id' => $this->importId,
            'batch_number' => $this->batchNumber,
            'row_count' => count($this->rows),
        ]);

        $processedCount = 0;
        $failedCount = 0;
        $failedRows = [];
        $processedEmails = [];

        foreach ($this->rows as $row) {
            try {
                // Validate the row data
                $validator = Validator::make($row, [
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'required|string|max:255',
                    'email' => 'required|email|max:255',
                    'phone' => 'nullable|string|max:20',
                    'external_id' => 'nullable|string',
                    'language' => 'nullable|string|in:'.implode(',', Languages::getValues()),
                ]);

                if ($validator->fails()) {
                    $failedCount++;
                    $failedRows[] = [
                        'row' => $row,
                        'error' => $validator->errors()->first(),
                    ];

                    continue;
                }

                // Check for duplicate emails within this batch
                if (in_array($row['email'], $processedEmails)) {
                    $failedCount++;
                    $failedRows[] = [
                        'row' => $row,
                        'error' => 'Duplicate email in batch',
                    ];

                    continue;
                }

                // Create the invited user within a transaction
                $invitedUser = DB::transaction(function () use ($invitedUserService, $row): User {
                    // Check if email already exists for this financer with active status
                    $existing = User::where('email', $row['email'])
                        ->whereHas('financers', function ($query): void {
                            $query->where('financer_user.financer_id', $this->financerId)
                                ->where('financer_user.active', true);
                        })
                        ->first();

                    if ($existing) {
                        throw new Exception('Email already exists for this financer');
                    }

                    // Extract language from row (nullable)
                    $language = empty($row['language']) ? null : $row['language'];

                    // Prepare user data (minimal - service handles invitation fields)
                    $userData = [
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'],
                        'email' => $row['email'],
                        'phone' => empty($row['phone']) ? null : $row['phone'],
                        'invitation_metadata' => [
                            'financer_id' => $this->financerId,
                            'external_id' => empty($row['external_id']) ? null : $row['external_id'],
                            'language' => $language,
                        ],
                    ];

                    // Only set locale if language is provided (locale has default in DB via migration)
                    if ($language !== null) {
                        $userData['locale'] = $language;
                    }

                    // Create the invited user (now User with invitation_status='pending')
                    $invitedUser = $invitedUserService->create($userData);

                    // Attach financer if provided
                    if ($this->financerId !== '' && $this->financerId !== '0') {
                        $invitedUser->financers()->attach($this->financerId, [
                            'active' => true, // CSV imported users are active by default
                            'sirh_id' => '',
                            'from' => now()->toDateString(),
                            'role' => RoleDefaults::BENEFICIARY, // REQUIRED: CSV imported users are beneficiaries by default
                            'language' => $language, // Set language in financer_user pivot
                        ]);
                    }

                    // Queue welcome email instead of sending synchronously
                    SendWelcomeEmailJob::dispatch(
                        $invitedUser->id,
                        $invitedUser->email,
                        $invitedUser->first_name,
                        $invitedUser->last_name
                    )->delay(now()->addSeconds(2)); // Small delay to avoid overwhelming mail server

                    return $invitedUser;
                });

                $processedEmails[] = $row['email'];
                $processedCount++;

                // Free memory after processing each user
                unset($invitedUser);

            } catch (QueryException $e) {
                // Handle database constraint violations (e.g., unique constraints)
                // This catches race conditions where parallel batches try to create the same user
                if ($e->getCode() === '23000' ||
                    str_contains($e->getMessage(), 'Duplicate entry') ||
                    str_contains($e->getMessage(), 'duplicate key') ||
                    str_contains($e->getMessage(), 'unique constraint')) {

                    Log::warning('Duplicate detected in batch (database constraint)', [
                        'import_id' => $this->importId,
                        'batch_number' => $this->batchNumber,
                        'email' => $row['email'] ?? 'unknown',
                        'error_code' => $e->getCode(),
                    ]);

                    $failedCount++;
                    $failedRows[] = [
                        'row' => $row,
                        'error' => 'Email already exists in database',
                    ];
                } else {
                    // Other database errors
                    Log::error('Database error in batch', [
                        'import_id' => $this->importId,
                        'batch_number' => $this->batchNumber,
                        'email' => $row['email'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'code' => $e->getCode(),
                    ]);

                    $failedCount++;
                    $failedRows[] = [
                        'row' => $row,
                        'error' => 'Database error: '.$e->getMessage(),
                    ];
                }
            } catch (Throwable $e) {
                Log::warning('Failed to import user in batch', [
                    'import_id' => $this->importId,
                    'batch_number' => $this->batchNumber,
                    'email' => $row['email'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);

                $failedCount++;
                $failedRows[] = [
                    'row' => $row,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Update tracking in Redis
        $tracker = app(CsvImportTrackerService::class);
        $tracker->updateBatchProgress(
            $this->importId,
            $processedCount,
            $failedCount,
            $failedRows
        );

        // Dispatch batch processed event (will also broadcast automatically since it implements ShouldBroadcastNow)
        event(new CsvInvitedUsersBatchProcessed(
            $this->importId,
            $this->financerId,
            $this->userId,
            $this->batchNumber,
            $processedCount,
            $failedCount,
            $failedRows
        ));

        Log::info('Batch processing completed', [
            'import_id' => $this->importId,
            'batch_number' => $this->batchNumber,
            'processed' => $processedCount,
            'failed' => $failedCount,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Batch job failed completely', [
            'import_id' => $this->importId,
            'batch_number' => $this->batchNumber,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Dispatch dedicated failure event (will also broadcast automatically since it implements ShouldBroadcastNow)
        event(new CsvInvitedUsersBatchFailed(
            $this->importId,
            $this->financerId,
            $this->userId,
            $this->batchNumber,
            count($this->rows),
            $exception->getMessage(),
            get_class($exception)
        ));
    }
}
