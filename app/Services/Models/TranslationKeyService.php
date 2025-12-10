<?php

declare(strict_types=1);

namespace App\Services\Models;

use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class TranslationKeyService
{
    /**
     * Create a new translation key
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TranslationKey
    {
        return TranslationKey::create([
            'key' => $data['key'],
            'group' => $data['group'] ?? null,
            'interface_origin' => $data['interface_origin'] ?? 'web_financer',
        ]);
    }

    /**
     * Update a translation key
     *
     * @param  array<string, mixed>  $data
     */
    public function update(TranslationKey $translationKey, array $data): TranslationKey
    {
        $translationKey->update([
            'key' => $data['key'] ?? $translationKey->key,
            'group' => $data['group'] ?? $translationKey->group,
        ]);

        $fresh = $translationKey->fresh();
        if (! $fresh instanceof TranslationKey) {
            throw new RuntimeException('Failed to refresh translation key');
        }

        return $fresh;
    }

    /**
     * Delete a translation key and all its values
     */
    public function delete(TranslationKey $translationKey): bool
    {
        // Delete all associated translation values first
        $translationKey->values()->delete();

        return (bool) $translationKey->delete();
    }

    /**
     * Get all translation keys
     */
    public function all($interfaceOrigin): Collection
    {
        return TranslationKey::with(['values'])
            ->where('interface_origin', request()->header('x-origin-interface'))
            ->forInterface($interfaceOrigin)
            ->get();
    }

    /**
     * Find translation key by key
     */
    public function findByKey(string $key, $interfaceOrigin): ?TranslationKey
    {
        return TranslationKey::where('key', $key)
            ->forInterface($interfaceOrigin)
            ->first();
    }

    /**
     * Get all translation keys for a specific interface
     *
     * @return Collection<int, TranslationKey>
     */
    public function allForInterface(string $interfaceOrigin): Collection
    {
        return TranslationKey::with('values')
            ->forInterface($interfaceOrigin)
            ->orderBy('group')
            ->orderBy('key')
            ->get();
    }
}
