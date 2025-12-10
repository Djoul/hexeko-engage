<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\Models\TranslationKey;
use App\Services\Models\TranslationKeyService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class UpdateTranslationAction
{
    public function __construct(
        private TranslationKeyService $translationKeyService
    ) {}

    /**
     * Execute the action to update a translation key
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function execute(TranslationKey $translationKey, array $data): TranslationKey
    {
        // If updating the key, validate it doesn't already exist
        if (array_key_exists('key', $data) && is_string($data['key']) && $data['key'] !== $translationKey->key) {
            $interfaceOrigin = $data['interface_origin'] ?? $translationKey->interface_origin ?? null;
            $this->validateTranslationKey($data['key'], $translationKey->id, $interfaceOrigin);
        }

        // Update the translation key via the service
        $updatedTranslationKey = $this->translationKeyService->update($translationKey, $data);

        // Log activity
        if (! app()->environment('testing')) {
            activity()
                ->performedOn($updatedTranslationKey)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old' => [
                        'key' => $translationKey->key,
                        'group' => $translationKey->group,
                    ],
                    'new' => $data,
                ])
                ->log('Translation key updated');
        }

        if (! app()->environment('testing')) {
            Log::info('Translation key updated', [
                'key' => $updatedTranslationKey->key,
                'group' => $updatedTranslationKey->group,
                'user_id' => auth()->id(),
            ]);
        }

        return $updatedTranslationKey;
    }

    /**
     * Validate that the translation key doesn't already exist
     *
     * @throws InvalidArgumentException
     */
    private function validateTranslationKey(string $key, string|int $currentId, string $interfaceOrigin): void
    {
        $existingKey = $this->translationKeyService->findByKey($key, $interfaceOrigin);

        if ($existingKey instanceof TranslationKey && $existingKey->id != $currentId) {
            throw new InvalidArgumentException("Translation key already exists: {$key}");
        }
    }
}
