<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Services\Models\TranslationValueService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CreateTranslationValueAction
{
    public function __construct(
        private TranslationValueService $translationValueService
    ) {}

    /**
     * Execute the action to create a new translation value
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function execute(TranslationKey $translationKey, array $data): TranslationValue
    {
        // Validate required fields
        $this->validateData($data);

        // Check if translation value already exists for this locale
        if (! is_string($data['locale'])) {
            throw new InvalidArgumentException('Locale must be a string');
        }
        $this->validateUniqueLocale($translationKey, $data['locale']);

        // Prepare data with translation key ID
        $valueData = array_merge($data, [
            'translation_key_id' => $translationKey->id,
        ]);

        // Create the translation value via the service
        $translationValue = $this->translationValueService->create($valueData);

        // Log activity
        if (! app()->environment('testing')) {
            activity()
                ->performedOn($translationValue)
                ->causedBy(auth()->user())
                ->withProperties($data)
                ->log('Translation value created');
        }

        if (! app()->environment('testing')) {
            Log::info('Translation value created', [
                'translation_key_id' => $translationKey->id,
                'locale' => $translationValue->locale,
                'user_id' => auth()->id(),
            ]);
        }

        return $translationValue;
    }

    /**
     * Validate required data
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    private function validateData(array $data): void
    {
        if (! array_key_exists('locale', $data) || empty($data['locale'])) {
            throw new InvalidArgumentException('Locale is required');
        }

        if (! array_key_exists('value', $data)) {
            throw new InvalidArgumentException('Value is required');
        }
    }

    /**
     * Validate that the locale doesn't already exist for this translation key
     *
     * @throws InvalidArgumentException
     */
    private function validateUniqueLocale(TranslationKey $translationKey, string $locale): void
    {
        if ($translationKey->values()->where('locale', $locale)->exists()) {
            throw new InvalidArgumentException("Translation value for locale \"{$locale}\" already exists");
        }
    }
}
