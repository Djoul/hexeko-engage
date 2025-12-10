<?php

declare(strict_types=1);

namespace App\Http\Controllers\AdminPanel;

use App\Actions\Translation\CheckTranslationHealthAction;
use App\Actions\Translation\DetectTranslationDriftAction;
use App\Actions\Translation\ReconcileTranslationsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReconciliationResultResource;
use App\Http\Resources\TranslationDriftResource;
use App\Http\Resources\TranslationHealthResource;
use App\Services\EnvironmentService;
use App\Services\TranslationCacheService;
use App\Services\TranslationManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationHealthController extends Controller
{
    public function __construct(
        private readonly EnvironmentService $environmentService,
        private readonly TranslationManifestService $manifestService,
        private readonly TranslationCacheService $cacheService
    ) {}

    /**
     * Check translation system health.
     */
    public function health(CheckTranslationHealthAction $action): JsonResponse
    {
        $result = $action->execute();

        return response()->json(
            new TranslationHealthResource($result),
            $result->healthy ? 200 : 503
        );
    }

    /**
     * Detect translation drift between local and S3.
     */
    public function detectDrift(
        Request $request,
        DetectTranslationDriftAction $action
    ): JsonResponse {
        $request->validate([
            'interface' => ['nullable', 'string', 'in:mobile,web_financer,web_beneficiary'],
        ]);

        $options = [
            'interface' => $request->input('interface'),
        ];

        $result = $action->execute($options);

        return response()->json(new TranslationDriftResource($result));
    }

    /**
     * Trigger manual translation reconciliation.
     */
    public function reconcile(
        Request $request,
        ReconcileTranslationsAction $action
    ): JsonResponse {
        // Check environment permissions
        if (! $this->environmentService->shouldReconcile()) {
            return response()->json([
                'message' => 'Reconciliation is not enabled in '.app()->environment().' environment.',
                'allowed_environments' => ['staging', 'production'],
            ], 409);
        }

        $request->validate([
            'interfaces' => ['nullable', 'array'],
            'interfaces.*' => ['string', 'in:mobile,web_financer,web_beneficiary'],
            'force' => ['nullable', 'boolean'],
        ]);

        $options = [
            'interfaces' => $request->input('interfaces', ['mobile', 'web_financer', 'web_beneficiary']),
            'force' => $request->boolean('force', false),
        ];

        $result = $action->execute($options);

        return response()->json(
            new ReconciliationResultResource($result),
            $result->success ? 200 : 500
        );
    }

    /**
     * Get translation manifest for an interface.
     */
    public function getManifest(string $interface): JsonResponse
    {
        $validInterfaces = ['mobile', 'web_financer', 'web_beneficiary'];
        if (! in_array($interface, $validInterfaces, true)) {
            return response()->json(['message' => 'Invalid interface'], 400);
        }

        $manifest = $this->manifestService->getManifest($interface);

        if ($manifest === null || $manifest === []) {
            return response()->json(['message' => 'Manifest not found'], 404);
        }

        return response()->json($manifest);
    }

    /**
     * Clear translation cache.
     */
    public function clearCache(Request $request): JsonResponse
    {
        $request->validate([
            'interface' => ['nullable', 'string', 'in:mobile,web_financer,web_beneficiary'],
        ]);

        $interface = $request->input('interface');
        $interfaces = $interface ? [$interface] : ['mobile', 'web_financer', 'web_beneficiary'];

        foreach ($interfaces as $int) {
            $this->cacheService->clearInterface($int);
        }

        return response()->json([
            'message' => 'Translation cache cleared successfully',
            'interfaces_cleared' => $interfaces,
        ]);
    }
}
