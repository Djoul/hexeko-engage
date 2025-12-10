<?php

declare(strict_types=1);

namespace App\Actions\Module;

use App\Models\Financer;
use App\Models\Module;
use App\Services\Models\ModuleService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BulkToggleFinancerModulesAction
{
    public function __construct(
        private ModuleService $moduleService
    ) {}

    /**
     * Execute the action to bulk toggle modules for a financer
     *
     * @param  array<string>  $moduleIds
     * @return array<string, bool>
     *
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function execute(array $moduleIds, Financer $financer): array
    {
        // Validate input
        $this->validateModuleIds($moduleIds);

        // Check if any of the modules are core modules
        $this->validateNoCoreModules($moduleIds);

        DB::beginTransaction();
        try {
            // Perform bulk toggle via service
            $results = $this->moduleService->bulkToggleForFinancer($moduleIds, $financer);

            // Log activity
            if (! app()->environment('testing')) {
                $activated = array_keys(array_filter($results));
                $deactivated = array_keys(array_filter($results, fn (bool $v): bool => ! $v));

                activity()
                    ->performedOn($financer)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'module_ids' => $moduleIds,
                        'activated' => $activated,
                        'deactivated' => $deactivated,
                        'results' => $results,
                    ])
                    ->log('Bulk module toggle for financer');
            }

            if (! app()->environment('testing')) {
                Log::info('Bulk module toggle for financer', [
                    'financer_id' => $financer->id,
                    'financer_name' => $financer->name,
                    'module_ids' => $moduleIds,
                    'results' => $results,
                    'user_id' => auth()->id(),
                ]);
            }

            DB::commit();

            return $results;
        } catch (Exception $e) {
            DB::rollBack();

            if (! app()->environment('testing')) {
                Log::error('Bulk module toggle failed for financer', [
                    'financer_id' => $financer->id,
                    'module_ids' => $moduleIds,
                    'error' => $e->getMessage(),
                    'user_id' => auth()->id(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Validate module IDs
     *
     * @param  array<mixed>  $moduleIds
     *
     * @throws InvalidArgumentException
     */
    private function validateModuleIds(array $moduleIds): void
    {
        if ($moduleIds === []) {
            throw new InvalidArgumentException('Module IDs cannot be empty');
        }

        foreach ($moduleIds as $moduleId) {
            if (! is_string($moduleId)) {
                throw new InvalidArgumentException('All module IDs must be strings');
            }
        }
    }

    /**
     * Validate that no core modules are included
     *
     * @param  array<string>  $moduleIds
     *
     * @throws UnprocessableEntityHttpException
     */
    private function validateNoCoreModules(array $moduleIds): void
    {
        $coreModules = Module::whereIn('id', $moduleIds)
            ->where('is_core', true)
            ->pluck('name', 'id');

        if ($coreModules->isNotEmpty()) {
            $coreModuleNames = $coreModules->implode(', ');
            throw new UnprocessableEntityHttpException(
                "Cannot toggle core modules: {$coreModuleNames}. Core modules must always remain active."
            );
        }
    }
}
