<?php

namespace App\Http\Controllers\V1;

use App\Actions\Translation\ExportTranslationsAction;
use App\Actions\Translation\ImportTranslationsAction;
use App\Attributes\RequiresPermission;
use App\DTOs\Translation\ImportTranslationDTO;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Http\Resources\Translations\TranslationKeyResource;
use App\Http\Resources\Translations\TranslationKeyResourceCollection;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Services\EnvironmentService;
use App\Services\Models\TranslationKeyService;
use App\Services\Models\TranslationValueService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TranslationController extends Controller
{
    public function __construct(
        private readonly TranslationValueService $translationValueService,
        private readonly TranslationKeyService $translationKeyService,
        private readonly ExportTranslationsAction $exportTranslationsAction,
        private readonly ImportTranslationsAction $importTranslationsAction,
        private readonly EnvironmentService $environmentService
    ) {}

    //    #[RequiresPermission(PermissionDefaults::READ_TRADS)]
    public function index(Request $request): TranslationKeyResourceCollection
    {
        // Get interface origin from header
        $interfaceOrigin = $request->header('x-origin-interface', 'web_financer');

        // Get all translation keys with caching
        $keys = $this->translationKeyService->all($interfaceOrigin);

        // Apply filters if needed
        $search = $request->query('search');
        $group = $request->query('group');

        if (is_string($search) || $group) {
            $filteredKeys = $keys->filter(function ($key) use ($search, $group): bool {
                if (! $key instanceof TranslationKey) {
                    return false;
                }
                $matchesSearch = ! is_string($search) || str_contains($key->key, $search);
                $matchesGroup = ! $group || $key->group === $group;

                return $matchesSearch && $matchesGroup;
            });

            return new TranslationKeyResourceCollection($filteredKeys);
        }

        return new TranslationKeyResourceCollection($keys);
    }

    #[RequiresPermission(PermissionDefaults::READ_TRADS)]
    public function export(Request $request): JsonResponse
    {
        $interfaceOrigin = $request->query('interface_origin', 'web');

        $exportData = $this->exportTranslationsAction->execute($interfaceOrigin);

        return response()->json($exportData);
    }

    #[RequiresPermission(PermissionDefaults::CREATE_TRADS)]
    public function import(Request $request): JsonResponse
    {
        $this->assertCanEdit();

        $request->validate([
            'file' => 'required|file|mimes:json',
            'interface_origin' => 'required|string|in:web,mobile,web_financer,web_beneficiary',
            'import_type' => 'required|string|in:multilingual,single',
            'preview_only' => 'nullable|boolean',
            'update_existing_values' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $interfaceOrigin = $request->input('interface_origin');
        $importType = $request->input('import_type');
        $previewOnly = $request->boolean('preview_only', false);
        $updateExistingValues = $request->boolean('update_existing_values', false);

        // Read file content
        $content = file_get_contents($file->getPathname());

        // Create DTO
        $dto = ImportTranslationDTO::fromFileUpload(
            $content,
            $file->getClientOriginalName(),
            $interfaceOrigin,
            $importType,
            $previewOnly,
            $updateExistingValues
        );

        // Execute import
        $result = $this->importTranslationsAction->execute([
            'interface' => $interfaceOrigin,
            'translations' => $dto->translations,
            'update_existing_values' => $updateExistingValues,
        ], $interfaceOrigin, $previewOnly);

        return response()->json($result);
    }

    #[RequiresPermission(PermissionDefaults::READ_TRADS)]
    public function show(int|string $id): TranslationKeyResource
    {
        $key = TranslationKey::with(['values'])->find((string) $id);

        if (! $key instanceof TranslationKey) {
            throw new ModelNotFoundException('Translation key not found');
        }

        return new TranslationKeyResource($key);
    }

    #[RequiresPermission(PermissionDefaults::CREATE_TRADS)]
    public function store(Request $request): JsonResponse
    {
        $this->assertCanEdit();

        $data = $request->validate([
            'key' => 'required|string',
            'group' => 'nullable|string',
            'values' => 'array',
            'values.*' => 'nullable|string',
        ]);

        $tk = $this->translationKeyService->create([
            'key' => $data['key'],
            'group' => $data['group'] ?? null,
        ]);

        foreach ($data['values'] ?? [] as $locale => $value) {
            if ($value !== null && $value !== '') {
                $this->translationValueService->create([
                    'translation_key_id' => $tk->id,
                    'locale' => $locale,
                    'value' => $value,
                ]);
            }
        }

        return response()->json(
            new TranslationKeyResource(TranslationKey::with(['values'])->find((string) $tk->id)),
            201
        );
    }

    #[RequiresPermission(PermissionDefaults::UPDATE_TRADS)]
    public function update(Request $request, int|string $id): TranslationKeyResource
    {
        $this->assertCanEdit();

        $tk = TranslationKey::find((string) $id);

        if (! $tk instanceof TranslationKey) {
            throw new ModelNotFoundException('Translation key not found');
        }

        $data = $request->validate([
            'values' => 'array',
            'values.*' => 'nullable|string',
        ]);

        foreach ($data['values'] ?? [] as $locale => $value) {
            TranslationValue::updateOrCreate([
                'translation_key_id' => $tk->id,
                'locale' => $locale,
            ], [
                'value' => $value,
            ]);
        }

        return new TranslationKeyResource(TranslationKey::with(['values'])->find((string) $tk->id));
    }

    #[RequiresPermission(PermissionDefaults::CREATE_TRADS)]
    public function createTranslationValue(Request $request, int|string $translation_key_id): JsonResponse
    {
        $this->assertCanEdit();

        $data = $request->validate([
            'value' => 'required|string',
            'locale' => 'required|string',
        ]);
        $data['translation_key_id'] = $translation_key_id;
        $translationValue = $this->translationValueService->create($data);

        return response()->json($translationValue, 201);
    }

    #[RequiresPermission(PermissionDefaults::UPDATE_TRADS)]
    public function updateTranslationValue(Request $request, int|string $translation_key_id): JsonResponse
    {
        $this->assertCanEdit();

        $data = $request->validate([
            'value' => 'required|string',
            'locale' => 'required|string',
        ]);

        $translationValue = TranslationValue::where('translation_key_id', $translation_key_id)
            ->where('locale', $data['locale'])
            ->firstOrCreate();
        $this->translationValueService->update($translationValue, $data);

        return response()->json($translationValue);
    }

    #[RequiresPermission(PermissionDefaults::DELETE_TRADS)]
    public function destroy(int|string $id): Response
    {
        $this->assertCanEdit();

        $tk = TranslationKey::find((string) $id);

        if (! $tk instanceof TranslationKey) {
            throw new ModelNotFoundException('Translation key not found');
        }

        $this->translationKeyService->delete($tk);

        return response()->json(['message' => 'Clé supprimée.'])->setStatusCode(204);
    }

    private function assertCanEdit(): void
    {
        if (! $this->environmentService->canEditTranslations()) {
            abort(Response::HTTP_FORBIDDEN, 'Translation editing not allowed in this environment');
        }
    }
}
