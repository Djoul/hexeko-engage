<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Services\Models\TranslationKeyService;
use App\Services\Models\TranslationValueService;
use App\Services\TranslationMigrations\S3StorageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ImportTranslationsAction
{
    public function __construct(
        private readonly TranslationKeyService $translationKeyService,
        private readonly TranslationValueService $translationValueService,
        private readonly ExportTranslationsAction $exportTranslationsAction,
        private readonly S3StorageService $s3StorageService
    ) {}

    /**
     * Import translations from JSON with optional preview mode
     *
     * @param  array{interface?: string, translations: array<string, array<string, string>>, update_existing_values?: bool}  $data
     * @return array{preview?: bool, success?: bool, summary: array<string, int>, changes?: array<string, array<int, mixed>>}
     */
    public function execute(array $data, string $interfaceOrigin, bool $previewOnly = false): array
    {
        // Validate JSON structure
        $this->validateJsonStructure($data);

        // If interface is present in the JSON, ensure it matches the selected one
        if (array_key_exists('interface', $data) && $data['interface'] !== $interfaceOrigin) {
            throw ValidationException::withMessages([
                'interface' => ['The interface in the JSON does not match the target interface.'],
            ]);
        }

        $updateExistingValues = $data['update_existing_values'] ?? false;
        $changes = $this->detectChanges($data['translations'], $interfaceOrigin);

        if ($previewOnly) {
            return $this->formatPreviewResponse($changes);
        }

        // If updateExistingValues is false, move updated_values to unchanged before applying
        if (! $updateExistingValues && ! empty($changes['updated_values'])) {
            /** @var array{key_id: int, key: string, group: string|null, locale: string, old_value: string, new_value: string} $updatedValue */
            foreach ($changes['updated_values'] as $updatedValue) {
                $changes['unchanged'][] = [
                    'key' => $updatedValue['key'],
                    'group' => $updatedValue['group'],
                    'locale' => $updatedValue['locale'],
                    'value' => $updatedValue['old_value'], // Keep old value
                ];
            }
            $changes['updated_values'] = []; // Clear updated values
        }

        // Create backup before import
        $this->createBackup($interfaceOrigin);

        // Apply changes in a transaction
        DB::transaction(function () use ($changes, $interfaceOrigin, $updateExistingValues): void {
            $this->applyChanges($changes, $interfaceOrigin, $updateExistingValues);
        });

        return $this->formatImportResponse($changes);
    }

    /**
     * Validate the JSON structure
     *
     * @param  array<string, mixed>  $data
     */
    private function validateJsonStructure(array $data): void
    {
        $validator = Validator::make($data, [
            // Interface is not required in the JSON file, it's passed as a parameter
            'translations' => 'required|array',
            'translations.*' => 'required|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Detect changes between import data and existing translations
     *
     * @param  array<string, array<string, string>>  $importTranslations
     * @return array{new_keys: array<int, mixed>, updated_values: array<int, mixed>, new_values: array<int, mixed>, unchanged: array<int, mixed>}
     */
    private function detectChanges(array $importTranslations, string $interfaceOrigin): array
    {
        $changes = [
            'new_keys' => [],
            'updated_values' => [],
            'new_values' => [],
            'unchanged' => [],
        ];

        $existingKeys = $this->translationKeyService->allForInterface($interfaceOrigin);
        $existingKeysMap = [];

        // Build map of existing keys
        /** @var TranslationKey $key */
        foreach ($existingKeys as $key) {
            $fullKey = $key->group !== null && $key->group !== '' ? $key->group.'.'.$key->key : $key->key;
            $existingKeysMap[$fullKey] = $key;
        }

        // Process import data - new flat format
        foreach ($importTranslations as $fullKey => $localeValues) {
            $changes = $this->processFlatTranslations(
                $fullKey,
                $localeValues,
                $existingKeysMap,
                $changes
            );
        }

        return $changes;
    }

    /**
     * Process flat translations format
     *
     * @param  array<string, string>  $localeValues
     * @param  array<string, TranslationKey>  $existingKeysMap
     * @param  array{new_keys: array<int, mixed>, updated_values: array<int, mixed>, new_values: array<int, mixed>, unchanged: array<int, mixed>}  $changes
     * @return array{new_keys: array<int, mixed>, updated_values: array<int, mixed>, new_values: array<int, mixed>, unchanged: array<int, mixed>}
     */
    private function processFlatTranslations(
        string $fullKey,
        array $localeValues,
        array $existingKeysMap,
        array $changes
    ): array {
        // Split into group and key
        $parts = explode('.', $fullKey);
        $actualKey = array_pop($parts);
        $group = in_array(implode('.', $parts), ['', '0'], true) ? null : implode('.', $parts);
        $keyAdded = false;

        foreach ($localeValues as $locale => $value) {
            if (array_key_exists($fullKey, $existingKeysMap)) {
                // Key exists, check if value is different
                $existingKey = $existingKeysMap[$fullKey];
                $existingValue = $existingKey->values->where('locale', $locale)->first();
                if ($existingValue) {
                    if ($existingValue->value !== $value) {
                        $changes['updated_values'][] = [
                            'key_id' => $existingKey->id,
                            'key' => $actualKey,
                            'group' => $group,
                            'locale' => $locale,
                            'old_value' => $existingValue->value,
                            'new_value' => $value,
                        ];
                    } else {
                        $changes['unchanged'][] = [
                            'key' => $actualKey,
                            'group' => $group,
                            'locale' => $locale,
                            'value' => $value,
                        ];
                    }
                } else {
                    // New value for existing key
                    $changes['new_values'][] = [
                        'key_id' => $existingKey->id,
                        'key' => $actualKey,
                        'group' => $group,
                        'locale' => $locale,
                        'value' => $value,
                    ];
                }
            } elseif (! $keyAdded) {
                // New key - only add once per key, not per locale
                $changes['new_keys'][] = [
                    'key' => $actualKey,
                    'group' => $group,
                    'full_key' => $fullKey,
                    'locales' => $localeValues,
                ];
                $keyAdded = true;
            }
        }

        return $changes;
    }

    /**
     * Apply detected changes to the database
     *
     * @param  array{new_keys: array<int, mixed>, updated_values: array<int, mixed>, new_values: array<int, mixed>, unchanged: array<int, mixed>}  $changes
     */
    private function applyChanges(array $changes, string $interfaceOrigin, bool $updateExistingValues = false): void
    {
        // CRITICAL: Reset PostgreSQL sequences BEFORE any inserts to prevent duplicate key violations
        // This ensures sequences are synchronized with the current MAX(id) in tables
        $this->resetPostgresSequence('translation_keys');
        $this->resetPostgresSequence('translation_values');

        // Create new keys
        /** @var array<string, int> $newKeyIds */
        $newKeyIds = [];
        /** @var array{key: string, group: string|null, full_key: string, locales: array<string, string>} $newKey */
        foreach ($changes['new_keys'] as $newKey) {
            $fullKey = $newKey['full_key'];

            if (! array_key_exists($fullKey, $newKeyIds)) {
                // Check if key already exists (in case of race condition)
                $existingKey = TranslationKey::where('key', $newKey['key'])
                    ->where('group', $newKey['group'])
                    ->where('interface_origin', $interfaceOrigin)
                    ->first();

                if ($existingKey) {
                    $newKeyIds[$fullKey] = $existingKey->id;
                } else {
                    // Create without triggering cache events
                    $translationKey = new TranslationKey([
                        'key' => $newKey['key'],
                        'group' => $newKey['group'],
                        'interface_origin' => $interfaceOrigin,
                    ]);
                    $translationKey->saveQuietly();
                    $newKeyIds[$fullKey] = $translationKey->id;
                }
            }
        }

        // Synchronize PostgreSQL sequence for translation_keys after bulk inserts
        if (count($changes['new_keys']) > 0) {
            $this->resetPostgresSequence('translation_keys');
        }

        // BULK INSERT: Collect all translation values from new keys and new values
        $valuesToInsert = [];

        // Collect values from new keys
        foreach ($changes['new_keys'] as $newKey) {
            $fullKey = $newKey['full_key'];
            $keyId = $newKeyIds[$fullKey];
            foreach ($newKey['locales'] as $locale => $value) {
                $valuesToInsert[] = [
                    'translation_key_id' => $keyId,
                    'locale' => $locale,
                    'value' => $value,
                ];
            }
        }

        // Collect new values for existing keys
        foreach ($changes['new_values'] as $newValue) {
            $valuesToInsert[] = [
                'translation_key_id' => $newValue['key_id'],
                'locale' => $newValue['locale'],
                'value' => $newValue['value'],
            ];
        }

        // Execute bulk insert for all values at once (fixes N+1 query ENGAGE-MAIN-API-A2)
        if (count($valuesToInsert) > 0) {
            $this->bulkUpsertTranslationValues($valuesToInsert);
            $this->resetPostgresSequence('translation_values');
        }

        // BULK UPDATE: Update existing values if flag is enabled (fixes N+1 query ENGAGE-MAIN-API-97)
        if ($updateExistingValues && count($changes['updated_values']) > 0) {
            $this->bulkUpdateTranslationValues($changes['updated_values']);
        }
    }

    /**
     * Format response for preview mode
     *
     * @param  array{new_keys: array<int, mixed>, updated_values: array<int, mixed>, new_values: array<int, mixed>, unchanged: array<int, mixed>}  $changes
     * @return array{preview: bool, summary: array<string, int>, changes: array<string, array<int, mixed>>}
     */
    private function formatPreviewResponse(array $changes): array
    {
        return [
            'preview' => true,
            'summary' => [
                'new_keys' => count($changes['new_keys']),
                'updated_values' => count($changes['updated_values']),
                'new_values' => count($changes['new_values']),
                'unchanged' => count($changes['unchanged']),
            ],
            'changes' => [
                'new_keys' => $changes['new_keys'],
                'updated_values' => $changes['updated_values'],
                'new_values' => $changes['new_values'],
            ],
        ];
    }

    /**
     * Format response for actual import
     *
     * @param  array{new_keys: array<int, mixed>, updated_values: array<int, mixed>, new_values: array<int, mixed>, unchanged: array<int, mixed>}  $changes
     * @return array{success: bool, summary: array<string, int>}
     */
    private function formatImportResponse(array $changes): array
    {
        return [
            'success' => true,
            'summary' => [
                'new_keys_created' => count($changes['new_keys']),
                'values_updated' => count($changes['updated_values']),
                'new_values_created' => count($changes['new_values']),
                'unchanged' => count($changes['unchanged']),
            ],
        ];
    }

    /**
     * Create a JSON backup of current translations before import
     * Uses the same format as migration backups for consistency
     */
    private function createBackup(string $interfaceOrigin): void
    {
        // Export current translations - same format as migrations
        $exportData = $this->exportTranslationsAction->execute($interfaceOrigin);

        // Convert to JSON string (same format as migrations)
        $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($jsonContent === false) {
            throw new RuntimeException('Failed to encode backup data to JSON');
        }

        // Use S3StorageService createUnifiedBackup with consistent naming
        // Same format as ApplyTranslationMigrationAction uses
        $this->s3StorageService->createUnifiedBackup(
            $interfaceOrigin,
            'before-import',  // Operation type for clear identification
            $jsonContent,
            'json'
        );
    }

    /**
     * Reset PostgreSQL sequence to synchronize with the max ID in the table
     * This prevents duplicate key violations after bulk inserts using saveQuietly()
     */
    private function resetPostgresSequence(string $tableName): void
    {
        // Only execute for PostgreSQL connections
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $sequenceName = $tableName.'_id_seq';

        // Reset the sequence to match the current maximum ID
        DB::statement("SELECT setval('{$sequenceName}', (SELECT COALESCE(MAX(id), 1) FROM {$tableName}), true)");
    }

    /**
     * Bulk upsert translation values using PostgreSQL-specific optimizations
     * Fixes N+1 query problem ENGAGE-MAIN-API-A2 (126 individual INSERT queries)
     *
     * @param  array<int, array{translation_key_id: int, locale: string, value: string}>  $values
     */
    private function bulkUpsertTranslationValues(array $values): void
    {
        if (count($values) === 0) {
            return;
        }

        // For PostgreSQL, use multi-row INSERT with ON CONFLICT
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Build VALUES clause with placeholders
            $valuesPlaceholders = [];
            $bindings = [];

            foreach ($values as $value) {
                $valuesPlaceholders[] = '(?, ?, ?, NOW(), NOW())';
                $bindings[] = $value['translation_key_id'];
                $bindings[] = $value['locale'];
                $bindings[] = $value['value'];
            }

            $valuesClause = implode(', ', $valuesPlaceholders);

            // Execute single bulk INSERT with ON CONFLICT (uses unique constraint from migration)
            DB::statement(
                "INSERT INTO translation_values (translation_key_id, locale, value, created_at, updated_at)
                VALUES {$valuesClause}
                ON CONFLICT ON CONSTRAINT translation_values_unique DO NOTHING",
                $bindings
            );

            return;
        }

        // Fallback for non-PostgreSQL databases (MySQL, SQLite)
        $now = now();
        $insertData = array_map(function (array $value) use ($now): array {
            return [
                'translation_key_id' => $value['translation_key_id'],
                'locale' => $value['locale'],
                'value' => $value['value'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $values);

        DB::table('translation_values')->insertOrIgnore($insertData);
    }

    /**
     * Bulk update translation values using PostgreSQL-specific optimizations
     * Fixes N+1 query problem ENGAGE-MAIN-API-97 (44 individual SELECT+UPDATE queries)
     *
     * @param  array<int, array{key_id: int, locale: string, new_value: string}>  $updates
     */
    private function bulkUpdateTranslationValues(array $updates): void
    {
        if (count($updates) === 0) {
            return;
        }

        // For PostgreSQL, use UPDATE with CASE statement for bulk updates
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Build CASE statement for value updates with separate binding arrays
            $caseStatements = [];
            $conditions = [];
            $caseBindings = [];
            $whereBindings = [];

            foreach ($updates as $update) {
                // CASE bindings: key_id, locale, new_value
                $caseStatements[] = 'WHEN translation_key_id = ? AND locale = ? THEN ?';
                $caseBindings[] = $update['key_id'];
                $caseBindings[] = $update['locale'];
                $caseBindings[] = $update['new_value'];

                // WHERE bindings: key_id, locale
                $conditions[] = '(translation_key_id = ? AND locale = ?)';
                $whereBindings[] = $update['key_id'];
                $whereBindings[] = $update['locale'];
            }

            $caseClause = implode(' ', $caseStatements);
            $whereClause = implode(' OR ', $conditions);

            // Combine bindings in correct order: CASE first, then WHERE
            $allBindings = array_merge($caseBindings, $whereBindings);

            // Execute single bulk UPDATE with CASE statement
            DB::statement(
                "UPDATE translation_values
                SET value = CASE {$caseClause} END,
                    updated_at = NOW()
                WHERE {$whereClause}",
                $allBindings
            );

            return;
        }

        // Fallback for non-PostgreSQL databases - use service for cache invalidation
        foreach ($updates as $update) {
            $value = TranslationValue::where('translation_key_id', $update['key_id'])
                ->where('locale', $update['locale'])
                ->first();

            if ($value) {
                $this->translationValueService->update($value, [
                    'value' => $update['new_value'],
                ]);
            }
        }
    }
}
