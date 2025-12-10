<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\Models\TranslationValue;
use App\Services\Models\TranslationValueService;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class UpdateTranslationValueAction
{
    public function __construct(
        private TranslationValueService $translationValueService
    ) {}

    /**
     * Execute the action to update a translation value
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function execute(TranslationValue $translationValue, array $data): TranslationValue
    {
        // Validate that value is provided
        $this->validateData($data);

        // Update the translation value via the service
        $updatedValue = $this->translationValueService->update($translationValue, $data);

        // Log activity
        if (! app()->environment('testing')) {
            activity()
                ->performedOn($updatedValue)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_value' => $translationValue->value,
                    'new_value' => $data['value'],
                ])
                ->log('Translation value updated');
        }

        if (! app()->environment('testing')) {
            Log::info('Translation value updated', [
                'translation_value_id' => $updatedValue->id,
                'locale' => $updatedValue->locale,
                'old_value' => $translationValue->value,
                'new_value' => $data['value'],
                'user_id' => auth()->id(),
            ]);
        }

        return $updatedValue;
    }

    /**
     * Validate the input data
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    private function validateData(array $data): void
    {
        if (! array_key_exists('value', $data)) {
            throw new InvalidArgumentException('Value is required');
        }
    }
}
