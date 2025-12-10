<?php

namespace App\Services\Models;

use App\Models\TranslationValue;

class TranslationValueService
{
    /**
     * Create a new translation value or update if exists for key/locale
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TranslationValue
    {
        $record = TranslationValue::firstOrNew([
            'translation_key_id' => $data['translation_key_id'],
            'locale' => $data['locale'],
        ]);

        if (array_key_exists('value', $data)) {
            $record->value = $data['value'];
        }

        $record->save();

        return $record;
    }

    /**
     * Update an existing translation value
     *
     * @param  array<string, mixed>  $data
     */
    public function update(TranslationValue $translationValue, array $data): TranslationValue
    {
        $translationValue->update($data);

        return $translationValue;
    }
}
