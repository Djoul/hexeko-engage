<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Gender;
use App\Models\ContractType;
use App\Models\Department;
use App\Models\FinancerUser;
use App\Models\User;
use App\Services\Apideck\ApideckService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncUserAttributesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private User $user;

    private string $financerId;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $financerId)
    {
        $this->user = $user;
        $this->financerId = $financerId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('sync_user_attributes-'.$this->user->id))->dontRelease()];
    }

    /**
     * Execute the job.
     */
    public function handle(ApideckService $apideckService): void
    {
        $user = $this->user;

        DB::transaction(function () use ($user, $apideckService): void {
            $apideckService->initializeConsumerId($this->financerId);
            $financerUser = FinancerUser::where('financer_id', $this->financerId)->where('user_id', $user->id)->first();
            $sirhId = $financerUser->sirh_id ?? '';

            if (empty($sirhId)) {
                return;
            }

            $employee = $apideckService->getEmployee($sirhId)['data'] ?? null;

            if (empty($employee) || ! is_array($employee)) {
                return;
            }

            $userUpdated = false;

            if (array_key_exists('gender', $employee) && ! empty($employee['gender']) && in_array($employee['gender'], Gender::asArray())) {
                $user->gender = $employee['gender'];
                $userUpdated = true;
            }

            if (array_key_exists('birthday', $employee) && ! empty($employee['birthday'])) {
                $user->birthdate = $employee['birthday'];
                $userUpdated = true;
            }

            if ($userUpdated) {
                $user->save();
            }

            if (array_key_exists('employment_start_date', $employee)) {
                $financerUser->started_at = $employee['employment_start_date'];
                $financerUser->save();
            }

            if (array_key_exists('employment_role', $employee) && array_key_exists('type', $employee['employment_role'])) {
                $contractTypeName = $employee['employment_role']['type'] ?? null;

                if (is_string($contractTypeName) && $contractTypeName !== '') {
                    $contractType = ContractType::withoutGlobalScopes()->firstOrCreate([
                        'financer_id' => $this->financerId,
                        'apideck_id' => $employee['employment_role']['type'],
                    ], [
                        'name' => $contractTypeName,
                    ]);

                    $user->contractTypes()->attach($contractType->id);
                }
            }

            if (array_key_exists('department_id', $employee)) {
                $departmentName = $employee['department'] ?? null;

                if (is_string($departmentName) && $departmentName !== '') {
                    $department = Department::withoutGlobalScopes()->firstOrCreate(
                        [
                            'financer_id' => $this->financerId,
                            'apideck_id' => $employee['department_id'],
                        ],
                        [
                            'name' => $departmentName,
                        ]
                    );

                    $user->departments()->attach($department->id);
                }
            }

            if (array_key_exists('manager', $employee)) {
                $managerFinancerUser = FinancerUser::where('financer_id', $this->financerId)
                    ->where('sirh_id', $employee['manager']['id'])
                    ->first();

                if ($managerFinancerUser) {
                    $user->managers()->attach($managerFinancerUser->user_id, ['financer_id' => $this->financerId]);
                }
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to sync user attributes', [
            'user_id' => $this->user->id,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
