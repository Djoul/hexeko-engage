<?php

namespace App\Services\Apideck;

use App\Actions\User\InvitedUser\CreateInvitedUserAction;
use App\Documentation\ThirdPartyApis\Concerns\LogsApiCalls;
use App\Documentation\ThirdPartyApis\Contracts\ThirdPartyServiceInterface;
use App\DTOs\ApideckEmployeeDTO;
use App\DTOs\User\CreateInvitedUserDTO;
use App\Events\ApideckSyncCompleted;
use App\Jobs\Apideck\GetTotalEmployeesJob;
use App\Models\Financer;
use App\Models\User;
use Arr;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Log;

class ApideckService implements ThirdPartyServiceInterface
{
    use LogsApiCalls;

    protected string $baseUrl;

    protected string $apiKey;

    protected string $appId;

    protected string $consumerId = '';

    public function __construct()
    {
        $this->baseUrl = is_string(config('services.apideck.base_url'))
            ? config('services.apideck.base_url')
            : throw new InvalidArgumentException('Invalid base_url');

        $this->apiKey = is_string(config('services.apideck.key'))
            ? config('services.apideck.key')
            : throw new InvalidArgumentException('Invalid apiKey');

        $this->appId = is_string(config('services.apideck.app_id'))
            ? config('services.apideck.app_id')
            : throw new InvalidArgumentException('Invalid appId');
    }

    /**
     * Initialize consumerId based on financerId
     */
    public function initializeConsumerId(?string $financerId = null): void
    {
        // Use provided financerId or try to get it from context
        $activeFinancerId = $financerId ?? activeFinancerID();
        $financerIdStr = is_string($activeFinancerId) ? $activeFinancerId : null;

        if ($financerIdStr === null) {
            $defaultConsumerId = config('services.apideck.consumer_id');
            if (! is_string($defaultConsumerId) || empty($defaultConsumerId)) {
                throw new InvalidArgumentException('No financerId provided and no default consumer_id configured');
            }
            $this->consumerId = $defaultConsumerId;
        } else {
            $this->consumerId = $this->resolveConsumerId($financerIdStr);
        }
    }

    /**
     * Get headers for API requests
     *
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->apiKey}",
            'x-apideck-app-id' => $this->appId,
            'x-apideck-consumer-id' => $this->consumerId,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     *
     * @throws Exception
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        // Ensure consumerId is initialized
        if (empty($this->consumerId)) {
            $this->initializeConsumerId();
        }

        /** @var Response $response */
        $response = Http::withHeaders($this->getHeaders())->{$method}(
            "{$this->baseUrl}{$endpoint}",
            $data
        );

        $status = $response->status();
        $responseData = $response->json();

        // Log the API call using the trait

        $logData = is_array($responseData) ? $responseData : [];
        $this->logApiCall(
            strtoupper($method),
            $endpoint,
            $status,
            $logData
        );

        if (! $response->successful()) {
            // Only reset consumer_id for authentication/authorization errors
            // 401: Unauthorized, 403: Forbidden - these indicate invalid consumer_id
            if (in_array($status, [401, 403], true)) {
                // Reset consumer_id to force re-initialization on next request
                $this->consumerId = '';

                // Reset consumer_id in database if we have a financer context
                $this->resetFinancerConsumerId();
            }

            // Parse error response to extract type_name if available
            $errorArray = [
                'error' => true,
                'message' => 'Apideck API Error: '.$response->body(),
                'status' => $status,
                'data' => [],
            ];

            // If we have parsed JSON data, include type_name for error detection
            if (is_array($responseData) && isset($responseData['type_name'])) {
                $errorArray['type_name'] = $responseData['type_name'];
            }

            return $errorArray;
        }

        return is_array($responseData) ? $responseData : [];
    }

    /**
     * @param  array<string,array<int,mixed>>  $params
     * @return array<string,mixed>
     *
     * @throws Exception
     */
    public function index(array $params = [], int $perPage = 20, int $page = 1): array
    {
        $financerId = $params['financer_id'] ?? activeFinancerID();
        $financerIdStr = is_string($financerId) ? $financerId : null;

        $this->initializeConsumerId($financerIdStr);

        $totalEmployeeCount = null;
        if ($financerIdStr !== null) {
            $cacheKey = $this->totalEmployeesCacheKey($financerIdStr);
            $totalEmployeeCount = Cache::get($cacheKey);

            if ($totalEmployeeCount === null) {
                $this->dispatchTotalEmployeesJobIfNeeded($financerIdStr);
            }
        }

        $fetchResult = $this->fetchEmployees($params);

        $responseData = $fetchResult['response'] ?? [];
        if (is_array($responseData) && ! empty($responseData['error'])) {
            $logContext = is_array($responseData) ? $responseData : [];
            Log::warning('Erreur lors du fetch employees', $logContext);

            return [
                'employees' => [],
                'meta' => [
                    'error' => $responseData['error'] ?? 'Unknown error',
                    'message' => $responseData['message'] ?? 'Erreur inconnue',
                ],
                'links' => [],
            ];
        }

        /** @var Collection<int, mixed> $allEmployees */
        $allEmployees = $fetchResult['allEmployees'];
        $response = $fetchResult['response'];

        // Filter unsynchronized employees if requested
        if (array_key_exists('unsynchronized', $params) && $params['unsynchronized']) {
            $allEmployees = $this->filterUnsynchronizedEmployees($allEmployees);
        }
        $employees = $allEmployees;
        $total = $employees->count();

        // Apply pagination like ArticleService
        if (paginated()) {
            $employees = $allEmployees->forPage($page, $perPage);
        }

        // Calculate pagination metadata
        $itemsOnThisPage = $employees->count(); // Number of items returned on this page
        $pageCount = $perPage > 0 ? (int) ceil($total / $perPage) : 0;

        return [
            'employees' => $employees->map(
                fn (mixed $employee): User => new User(
                    (new ApideckEmployeeDTO(is_array($employee) ? $employee : []))->toUserModelArray()
                )),
            'meta' => [
                'total_items' => $totalEmployeeCount,
                'total_page' => $itemsOnThisPage,
                'page_count' => $pageCount,
                // Merge external API meta but exclude fields we've already set correctly
                ...(is_array($response) && is_array($response['meta'] ?? null)
                    ? Arr::except($response['meta'], ['items_on_page', 'total_page', 'total_items', 'page_count'])
                    : []),
            ],
            // @phpstan-ignore-next-line
            'links' => $response['links'] ?? [],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     *
     * @throws Exception
     */
    public function syncAll(array $params = ['per_page' => 20]): array
    {
        $startTime = Carbon::now();
        $financerId = $params['financer_id'] ?? activeFinancerID();

        if (! $financerId || ! is_string($financerId)) {
            throw new InvalidArgumentException('Financer ID is required for synchronization');
        }

        // Initialize consumerId with the provided financer ID
        $this->initializeConsumerId($financerId);

        $created = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];
        $totalEmployeeCount = 0;

        try {
            $cacheKey = $this->totalEmployeesCacheKey($financerId);
            $totalEmployeeCount = Cache::get($cacheKey) ?? $this->calculateTotalEmployees($financerId);
            $fetchResult = $this->fetchEmployees($params);
            $responseData = $fetchResult['response'] ?? [];
            $metaData = is_array($responseData) ? $responseData['meta'] ?? [] : [];
            $cursorsData = is_array($metaData) ? $metaData['cursors'] ?? [] : [];
            $cursor = is_array($cursorsData) ? $cursorsData['next'] ?? null : null;
            $employees = $fetchResult['allEmployees'];

            do {
                // @phpstan-ignore-next-line
                foreach ($employees as $employee) {
                    try {
                        // @phpstan-ignore-next-line
                        $result = $this->syncEmployee($employee, $params['financer_id']);
                        if ($result === true) {
                            $created++;
                        } elseif ($result === 'updated') {
                            $updated++;
                        }
                    } catch (Exception $e) {
                        $failed++;
                        $errors[] = $e->getMessage();
                    }
                }

                if ($cursor) {
                    $params['cursor'] = $cursor;
                    $fetchResult = $this->fetchEmployees($params);
                    $responseData2 = $fetchResult['response'] ?? [];
                    $metaData2 = is_array($responseData2) ? $responseData2['meta'] ?? [] : [];
                    $cursorsData2 = is_array($metaData2) ? $metaData2['cursors'] ?? [] : [];
                    $cursor = is_array($cursorsData2) ? $cursorsData2['next'] ?? null : null;
                    $employees = $fetchResult['allEmployees'];
                }
            } while ($cursor !== null);

            $endTime = Carbon::now();
            $durationSeconds = $startTime->diffInSeconds($endTime);

            // Build sync data
            $syncData = [
                'created' => $created,
                'updated' => $updated,
                'failed' => $failed,
                'total' => $totalEmployeeCount,
                'duration_seconds' => $durationSeconds,
                'started_at' => $startTime->toIso8601String(),
                'completed_at' => $endTime->toIso8601String(),
            ];

            // Add division context if present
            if (array_key_exists('division_id', $params) && $params['division_id'] !== null) {
                $syncData['division_id'] = $params['division_id'];
            }

            // Dispatch the sync completed event
            event(new ApideckSyncCompleted($financerId, $syncData));

            return [
                'created' => $created,
                'updated' => $updated,
                'failed' => $failed,
                'meta' => [
                    'total_items' => $totalEmployeeCount,
                    'duration_seconds' => $durationSeconds,
                ],
            ];
        } catch (Exception $e) {
            $endTime = Carbon::now();
            $durationSeconds = $startTime->diffInSeconds($endTime);

            // Dispatch error event
            $syncData = [
                'created' => $created,
                'updated' => $updated,
                'failed' => $failed,
                'total' => $totalEmployeeCount,
                'duration_seconds' => $durationSeconds,
                'started_at' => $startTime->toIso8601String(),
                'completed_at' => $endTime->toIso8601String(),
                'error' => $e->getMessage(),
                'error_code' => 'SYNC_FAILED',
            ];

            event(new ApideckSyncCompleted($financerId, $syncData));

            return [
                'created' => $created,
                'updated' => $updated,
                'failed' => $failed,
                'error' => $e->getMessage(),
                'meta' => [
                    'total_items' => $totalEmployeeCount,
                    'duration_seconds' => $durationSeconds,
                ],
            ];
        }
    }

    /**
     * Fetch all employees from SIRH for batch processing
     *
     * @return array{employees: array<int, array<string, mixed>>, meta: array<string, mixed>}
     *
     * @throws Exception
     */
    public function fetchAllEmployees(): array
    {
        $allEmployees = [];
        $cursor = null;

        do {
            $params = [
                'limit' => 200,
                'filter' => [
                    'employment_status' => 'active',
                ],
            ];
            if ($cursor) {
                $params['cursor'] = $cursor;
            }

            $response = $this->request('get', '/hris/employees', $params);

            // If filter not supported, retry without filter and apply filter manually
            if (array_key_exists('error', $response) &&
                isset($response['type_name']) &&
                $response['type_name'] === 'UnsupportedFiltersError') {
                unset($params['filter']);
                $response = $this->request('get', '/hris/employees', $params);
            }

            // Check for error response
            if (array_key_exists('error', $response) && $response['error'] === true) {
                // Return the error response with empty employees array
                return [
                    'employees' => [],
                    'meta' => [
                        'total_items' => 0,
                        'error' => $response['message'] ?? 'Unknown error',
                    ],
                ];
            }

            $data = $response['data'] ?? [];
            if (is_array($data)) {
                // Filter only active employees (in case API filtering wasn't applied)
                // AND exclude employees whose email already exists in User tables
                $activeEmployees = array_filter($data, function ($employee): bool {
                    // Check if employee is active
                    $isActive = ! is_array($employee) ||
                               ! isset($employee['employment_status']) ||
                               $employee['employment_status'] === 'active';

                    // Check if email doesn't exist in User tables
                    $emailNotExists = true;
                    if (is_array($employee) && isset($employee['email'])) {
                        $emailNotExists = ! User::where('email', $employee['email'])->exists();
                    }

                    return $isActive && $emailNotExists;
                });
                $allEmployees = array_merge($allEmployees, $activeEmployees);
            }

            $meta = $response['meta'] ?? [];

            // Get next cursor for pagination
            $cursors = is_array($meta) && array_key_exists('cursors', $meta) ? $meta['cursors'] : [];
            $cursor = is_array($cursors) ? ($cursors['next'] ?? null) : null;

        } while ($cursor !== null);

        /** @var array<int, array<string, mixed>> $typedEmployees */
        $typedEmployees = $allEmployees;

        return [
            'employees' => $typedEmployees,
            'meta' => [
                'total_items' => count($allEmployees),
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     *
     * @throws Exception
     */
    protected function fetchEmployees(array $params): array
    {
        $allEmployees = collect();

        // Set default limit if not provided
        $params['limit'] = $params['per_page'] ?? 20;
        $params['filter'] = [
            'employment_status' => 'active',
        ];

        unset($params['per_page']);
        $limit = $params['limit'];

        if (Arr::has($params, 'page') && $params['page'] != 1) {
            $params['cursor'] = $params['page'];
        }
        unset($params['page']);

        //        do {

        $params = Arr::except($params, 'financer_id');

        $response = $this->request(
            'get',
            '/hris/employees',
            $limit === '0'
                ? [...$params, 'limit' => 200, 'filter' => ['employment_status' => 'active']]
                : $params
        );

        // If filter not supported, retry without filter
        if (array_key_exists('error', $response) &&
            isset($response['type_name']) &&
            $response['type_name'] === 'UnsupportedFiltersError') {
            unset($params['filter']);
            $response = $this->request(
                'get',
                '/hris/employees',
                $limit === '0' ? [...$params, 'limit' => 200] : $params
            );
        }

        // Check for error response
        if (array_key_exists('error', $response) && $response['error'] === true) {
            // Return empty collection with error info
            return [
                'allEmployees' => collect(),
                'limit' => $limit,
                'response' => $response,
            ];
        }

        $responseData = $response['data'] ?? [];

        // Filter only active employees (in case API filtering wasn't applied)
        // AND exclude employees whose email already exists in User tables
        if (is_array($responseData)) {
            $activeEmployees = array_filter($responseData, function ($employee): bool {
                // Check if employee is active
                $isActive = ! is_array($employee) ||
                           ! isset($employee['employment_status']) ||
                           $employee['employment_status'] === 'active';

                // Check if email doesn't exist in User  tables
                $emailNotExists = true;
                if (is_array($employee) && isset($employee['email'])) {
                    $emailNotExists = ! User::where('email', $employee['email'])->exists();
                }

                return $isActive && $emailNotExists;
            });
            $allEmployees = $allEmployees->merge(collect($activeEmployees));
        }

        return [
            'allEmployees' => $allEmployees,
            'limit' => $limit,
            'response' => $response,
        ];
    }

    /**
     * @return array<string,mixed>
     *
     * @throws Exception
     */
    public function getEmployee(string $employeeId): array
    {
        // Return the response as-is (including error structure if present)
        return $this->request('get', "/hris/employees/{$employeeId}");
    }

    /**
     * @param  array<string,mixed>  $employee
     * @return bool|string Returns true if created, 'updated' if updated, false if no action taken
     */
    public function syncEmployee(mixed $employee, string $financerId): bool|string
    {
        $employeeId = is_array($employee) && array_key_exists('id', $employee) ? $employee['id'] : '';

        $existingUser = User::join('financer_user', 'users.id', '=', 'financer_user.user_id')
            ->where('financer_user.sirh_id', $employeeId)
            ->where('financer_user.financer_id', $financerId)
            ->first();

        if (! $existingUser) {
            $apideckEmployeeDTO = new ApideckEmployeeDTO($employee);

            $toUserModelArray = $apideckEmployeeDTO->toInvitedUserModelArray();

            if (! array_key_exists('financers', $toUserModelArray) || ! is_array($toUserModelArray['financers'])) {
                $toUserModelArray['financers'] = [];
            }

            $toUserModelArray['financers'][0] = [
                'id' => $financerId,
                'pivot' => [
                    'sirh_id' => is_scalar($employee['id'] ?? null) ? (string) $employee['id'] : '',
                    'active' => true,
                ],
            ];

            // Check if email already exists for this financer with active status
            $emailExists = User::where('email', $toUserModelArray['email'])
                ->whereHas('financers', function ($query) use ($financerId): void {
                    $query->where('financer_user.financer_id', $financerId)
                        ->where('financer_user.active', true);
                })
                ->exists();

            if ($emailExists) {
                return false;
            }

            // Create DTO from employee data
            $dto = CreateInvitedUserDTO::from([
                'first_name' => $toUserModelArray['first_name'] ?? '',
                'last_name' => $toUserModelArray['last_name'] ?? '',
                'email' => $toUserModelArray['email'] ?? '',
                'phone' => $toUserModelArray['phone'] ?? null,
                'financer_id' => $financerId,
                'sirh_id' => is_scalar($employee['id'] ?? null) ? (string) $employee['id'] : null,
                'external_id' => $toUserModelArray['external_id'] ?? null,
                'intended_role' => 'beneficiary', // Always set beneficiary for Apideck sync (fixes UE-664)
            ]);

            // Dispatch job WITHOUT email/event (bulk import)
            $action = new CreateInvitedUserAction($dto);
            dispatch($action);

            return true;
        }

        // Here you could add logic to update existing user if needed
        // For now, we return false to indicate no action was taken
        return false;
    }

    /**
     * @return Repository|Application|mixed|object|string|null
     */
    protected function getConsumerId(string $financerId): string
    {
        $financer = Financer::where('id', $financerId)->first();
        if (! $financer) {
            throw new InvalidArgumentException('Invalid financerId: '.$financerId);
        }
        $externalId = $financer->external_id;
        // Handle case where external_id might be a JSON string
        if (is_string($externalId)) {
            $externalId = json_decode($externalId, true) ?? [];
        } elseif (! is_array($externalId)) {
            $externalId = [];
        }
        if (is_array($externalId) &&
            array_key_exists('sirh', $externalId) &&
            is_array($externalId['sirh']) &&
            array_key_exists('consumer_id', $externalId['sirh']) &&
            is_string($externalId['sirh']['consumer_id']) &&
            $externalId['sirh']['consumer_id'] !== '') {
            return $externalId['sirh']['consumer_id'];
        }

        abort(401, 'Invalid consumerId');
    }

    public function resolveConsumerId(string $financerId): string
    {
        return $this->getConsumerId($financerId);
    }

    /**
     * Filter employees that are not yet synchronized (not in users or invited_users)
     *
     * @param  Collection<int, mixed>  $employees
     * @return Collection<int, mixed>
     */
    protected function filterUnsynchronizedEmployees($employees): Collection
    {
        // Get sirh_ids from users table (JSON field: {"platform":"aws","id":"..."})
        // Extract the "id" value from the JSON structure
        $userSirhIds = User::whereNotNull('sirh_id')
            ->get()
            ->map(function ($user) {
                $sirhData = is_string($user->sirh_id) ? json_decode($user->sirh_id, true) : $user->sirh_id;

                return is_array($sirhData) ? ($sirhData['id'] ?? null) : $user->sirh_id;
            })
            ->filter()
            ->toArray();

        // Get sirh_ids from financer_user pivot table (for users with multiple financers)
        $pivotSirhIds = DB::table('financer_user')
            ->whereNotNull('sirh_id')
            ->pluck('sirh_id')
            ->toArray();

        // Combine both arrays
        $allSynchronizedIds = array_merge($userSirhIds, $pivotSirhIds);

        // Filter employees that are not in the synchronized list
        return collect($employees)->filter(function ($employee) use ($allSynchronizedIds): bool {
            $employeeId = is_array($employee) ? ($employee['id'] ?? null) : null;

            return $employeeId && ! in_array($employeeId, $allSynchronizedIds, true);
        });
    }

    /**
     * Calculate the total number of employees and persist it in cache.
     *
     * @throws Exception
     */
    public function calculateTotalEmployees(string $financerId): int
    {
        $this->initializeConsumerId($financerId);

        $cacheKey = $this->totalEmployeesCacheKey($financerId);

        $totalCount = 0;
        $cursor = null;

        do {
            $params = [
                'limit' => 200,
                'filter' => [
                    'employment_status' => 'active',
                ],
            ];
            if ($cursor !== null) {
                $params['cursor'] = $cursor;
            }

            $response = $this->request('get', '/hris/employees', $params);

            if (array_key_exists('error', $response) &&
                isset($response['type_name']) &&
                $response['type_name'] === 'UnsupportedFiltersError') {
                unset($params['filter']);
                $response = $this->request('get', '/hris/employees', $params);
            }

            if (array_key_exists('error', $response) && $response['error'] === true) {
                throw new Exception($response['message'] ?? 'Unable to calculate total employees');
            }

            $dataArray = $response['data'] ?? [];

            if (is_array($dataArray)) {
                $activeEmployees = array_filter($dataArray, function ($employee): bool {
                    $isActive = ! is_array($employee) ||
                               ! isset($employee['employment_status']) ||
                               $employee['employment_status'] === 'active';

                    $emailNotExists = true;
                    if (is_array($employee) && isset($employee['email'])) {
                        $emailNotExists = ! User::where('email', $employee['email'])->exists();
                    }

                    return $isActive && $emailNotExists;
                });
                $totalCount += count($activeEmployees);
            }

            $metaArray = $response['meta'] ?? [];
            $cursorsArray = is_array($metaArray) ? $metaArray['cursors'] ?? [] : [];
            $cursor = is_array($cursorsArray) ? $cursorsArray['next'] ?? null : null;
        } while ($cursor !== null);

        Cache::put($cacheKey, $totalCount, 3600);

        return $totalCount;
    }

    /**
     * Dispatch the async calculation job when the cache is empty.
     */
    private function dispatchTotalEmployeesJobIfNeeded(string $financerId): void
    {
        if ($this->consumerId === '') {
            return;
        }

        $lockKey = $this->totalEmployeesJobLockKey($financerId);

        if (! Cache::add($lockKey, true, 60)) {
            return;
        }

        $userId = Auth::id();
        $userIdentifier = $userId !== null ? (string) $userId : 'system';

        GetTotalEmployeesJob::dispatch($financerId, $this->consumerId, $userIdentifier);
    }

    public function totalEmployeesCacheKey(string $financerId, ?string $consumerId = null): string
    {
        $consumer = $consumerId ?? $this->consumerId;
        if ($consumer === '') {
            $consumer = 'default';
        }

        return 'apideck_total_employees:{'.$financerId.'_'.$consumer.'}';
    }

    public function totalEmployeesJobLockKey(string $financerId, ?string $consumerId = null): string
    {
        $consumer = $consumerId ?? $this->consumerId;
        if ($consumer === '') {
            $consumer = 'default';
        }

        return 'apideck_total_employees_job_lock:{'.$financerId.'_'.$consumer.'}';
    }

    /**
     * Reset consumer_id in financer database when API error occurs
     */
    protected function resetFinancerConsumerId(): void
    {
        // Get current financer ID from context
        $financerId = activeFinancerID();

        if (! in_array($financerId, [null, '', '0'], true)) {
            $financer = Financer::find($financerId);

            if ($financer) {
                // Get current external_id
                $externalId = $financer->external_id;

                // Decode if it's a JSON string
                if (is_string($externalId)) {
                    $externalId = json_decode($externalId, true) ?? [];
                } elseif (! is_array($externalId)) {
                    $externalId = [];
                }

                // Reset the consumer_id in the sirh section
                if (is_array($externalId) && array_key_exists('sirh', $externalId) && is_array($externalId['sirh'])) {
                    // Set consumer_id to null to force re-initialization
                    $externalId['sirh']['consumer_id'] = null;

                    // Save back to database
                    $financer->external_id = $externalId;
                    $financer->save();

                    Log::info('Reset consumer_id for financer', [
                        'financer_id' => $financerId,
                        'sirh' => $externalId['sirh'],
                    ]);
                }
            }
        }
    }

    /**
     * Get the provider name for logging
     * Required by ThirdPartyServiceInterface
     */
    public function getProviderName(): string
    {
        return 'apideck';
    }

    /**
     * Get the API version
     * Required by ThirdPartyServiceInterface
     */
    public function getApiVersion(): string
    {
        return 'v1';
    }

    /**
     * Check if the service is healthy
     * Required by ThirdPartyServiceInterface
     */
    public function isHealthy(): bool
    {
        try {
            // Try to get connections to check if API is responding
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(5)
                ->get($this->baseUrl.'/vault/connections', [
                    'api' => 'hris',
                    'limit' => 1,
                ]);

            return $response->successful();
        } catch (Exception $e) {
            return false;
        }
    }
}
