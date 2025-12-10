<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\Models\TranslationKey;
use App\Services\Models\TranslationKeyService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CreateTranslationAction
{
    public function __construct(
        private TranslationKeyService $translationKeyService
    ) {}

    /**
     * Execute the action to create a new translation key
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function execute(array $data): TranslationKey
    {
        if (! array_key_exists('key', $data) || ! is_string($data['key'])) {
            throw new InvalidArgumentException('Key is required and must be a string');
        }

        // Validate that the translation key doesn't already exist
        $this->validateTranslationKey($data['key'], $data['interface_origin'] ?? null);

        // Create the translation key via the service
        $translationKey = $this->translationKeyService->create($data);

        // Log activity
        if (! app()->environment('testing')) {
            activity()
                ->performedOn($translationKey)
                ->causedBy(auth()->user())
                ->withProperties($data)
                ->log('Translation key created');
        }

        if (! app()->environment('testing')) {
            Log::info('Translation key created', [
                'key' => $translationKey->key,
                'group' => $translationKey->group,
                'user_id' => auth()->id(),
            ]);
        }

        return $translationKey;
    }

    /**
     * Validate that the translation key doesn't already exist
     *
     * @throws InvalidArgumentException
     */
    private function validateTranslationKey(string $key, ?string $interfaceOrigin): void
    {
        $existingKey = $this->translationKeyService->findByKey($key, $interfaceOrigin);

        if ($existingKey instanceof TranslationKey) {
            throw new InvalidArgumentException("Translation key already exists: {$key}");
        }
    }
}
