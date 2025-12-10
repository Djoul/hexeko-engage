<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\TranslationMigrations\RollbackTranslationMigrationAction;
use App\DTOs\TranslationMigrations\RollbackMigrationDTO;
use App\Http\Requests\ApplyTranslationMigrationRequest;
use App\Http\Requests\RollbackTranslationMigrationRequest;
use App\Http\Requests\SyncTranslationMigrationsRequest;
use App\Http\Resources\TranslationMigrationResource;
use App\Jobs\TranslationMigrations\ProcessTranslationMigrationJob;
use App\Jobs\TranslationMigrations\SyncTranslationMigrationsJob;
use App\Models\TranslationMigration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TranslationMigrationController extends Controller
{
    /**
     * List translation migrations with optional filters
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = TranslationMigration::query();

        // Filter by interface
        if ($request->has('interface')) {
            $interface = $request->get('interface');
            if (is_string($interface)) {
                $query->forInterface($interface);
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Sort by created_at desc by default
        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $migrations = $query->paginate(is_numeric($perPage) ? (int) $perPage : 15);

        return TranslationMigrationResource::collection($migrations);
    }

    /**
     * Show a single migration
     */
    public function show(TranslationMigration $translationMigration): TranslationMigrationResource
    {
        return new TranslationMigrationResource($translationMigration);
    }

    /**
     * Apply a migration
     */
    public function apply(
        ApplyTranslationMigrationRequest $request,
        TranslationMigration $translationMigration
    ): JsonResponse {
        // Dispatch job to process migration
        ProcessTranslationMigrationJob::dispatch(
            migrationId: $translationMigration->id,
            createBackup: $request->boolean('create_backup', true),
            validateChecksum: $request->boolean('validate_checksum', true)
        );

        return response()->json([
            'data' => [
                'message' => 'Migration is being processed',
                'migration_id' => $translationMigration->id,
            ],
        ], 202); // 202 Accepted
    }

    /**
     * Rollback a migration
     */
    public function rollback(
        RollbackTranslationMigrationRequest $request,
        TranslationMigration $translationMigration,
        RollbackTranslationMigrationAction $action
    ): JsonResponse {
        // Execute rollback action directly (synchronous for immediate feedback)
        $reason = $request->get('reason');
        $dto = new RollbackMigrationDTO(
            migrationId: $translationMigration->id,
            reason: is_string($reason) ? $reason : ''
        );

        $result = $action->execute($dto);

        if (! $result->success) {
            return response()->json([
                'error' => $result->error,
            ], 422);
        }

        return response()->json([
            'data' => [
                'message' => 'Rollback is being processed',
                'migration_id' => $translationMigration->id,
            ],
        ], 202);
    }

    /**
     * Sync migrations from S3
     */
    public function sync(SyncTranslationMigrationsRequest $request): JsonResponse
    {
        // Dispatch sync job
        $interface = $request->get('interface');
        SyncTranslationMigrationsJob::dispatch(
            is_string($interface) ? $interface : '',
            $request->boolean('auto_process', false)
        );

        return response()->json([
            'data' => [
                'message' => 'Sync job has been queued',
                'interface' => $request->get('interface'),
            ],
        ], 202);
    }
}
