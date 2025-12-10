<?php

namespace App\Jobs\Apideck;

use App\Events\Apideck\SIRHSyncCompletedEvent;
use App\Models\User;
use App\Services\Apideck\ApideckService;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CompleteSIRHSyncJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $syncId,
        private readonly string $financerId,
        private readonly string $userId,
        private readonly int $totalEmployees,
        private readonly int $processedCount,
        private readonly int $failedCount
    ) {}

    public function handle(): void
    {
        $status = $this->failedCount === 0 ? 'completed' : 'completed_with_errors';

        broadcast(new SIRHSyncCompletedEvent(
            $this->syncId,
            $this->financerId,
            $this->userId,
            $this->totalEmployees,
            $this->processedCount,
            $this->failedCount,
            $status
        ));

        try {
            /** @var ApideckService $service */
            $service = app(ApideckService::class);
            $consumerId = $service->resolveConsumerId($this->financerId);
            $service->initializeConsumerId($this->financerId);

            $cacheKey = $service->totalEmployeesCacheKey($this->financerId, $consumerId);
            Cache::forget($cacheKey);
            Cache::forget($service->totalEmployeesJobLockKey($this->financerId, $consumerId));

            Log::info('Total employees cache invalidated after sync', [
                'sync_id' => $this->syncId,
                'financer_id' => $this->financerId,
                'cache_key' => $cacheKey,
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to invalidate total employees cache', [
                'sync_id' => $this->syncId,
                'error' => $e->getMessage(),
            ]);
        }

        // Clear user cache after SIRH sync completes
        // Since employees have been synced, user data may have changed
        try {
            // The cache includes: user ID, financer ID, auth user ID, and request params hash
            // We need to invalidate all user-related caches

            // Strategy 1: Touch all user records to trigger cache invalidation via Cachable trait
            User::query()
                ->whereHas('financers', function ($query): void {
                    $query->where('financer_id', $this->financerId);
                })
                ->touch();

            // Strategy 2: Clear Redis cache with proper patterns for Redis cluster
            $redis = Cache::store('redis')->getRedis();

            // The cache prefix is 'upengage_cache_' and keys use hash tags like {entity_user_}
            // We need to find all keys related to users
            $patterns = [
                '*{entity_user}*',
                '*{entity_user_*',
                '*user_*',
            ];

            foreach ($patterns as $pattern) {
                try {
                    // In Redis cluster, KEYS command might not work across all nodes
                    // Use SCAN instead for better compatibility
                    $iterator = null;
                    do {
                        $result = $redis->scan($iterator, ['match' => $pattern, 'count' => 100]);
                        if ($result === false) {
                            break;
                        }

                        [$iterator, $keys] = $result;
                        if (! empty($keys)) {
                            foreach ($keys as $key) {
                                $redis->del($key);
                            }
                        }
                    } while ($iterator > 0);
                } catch (Exception $e) {
                    Log::debug('Failed to scan/delete cache pattern', [
                        'pattern' => $pattern,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('User cache cleared after SIRH sync', [
                'sync_id' => $this->syncId,
                'financer_id' => $this->financerId,
            ]);
        } catch (Exception $e) {
            // Log but don't fail if cache clear fails
            Log::warning('Failed to clear user cache after SIRH sync', [
                'sync_id' => $this->syncId,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Employee sync completed', [
            'sync_id' => $this->syncId,
            'total' => $this->totalEmployees,
            'processed' => $this->processedCount,
            'failed' => $this->failedCount,
            'status' => $status,
        ]);
    }
}
