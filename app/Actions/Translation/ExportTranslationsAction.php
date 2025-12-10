<?php

declare(strict_types=1);

namespace App\Actions\Translation;

use App\Enums\Languages;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Services\Models\TranslationKeyService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ExportTranslationsAction
{
    public function __construct(
        private readonly TranslationKeyService $translationKeyService
    ) {}

    /**
     * Export translations for a specific interface to JSON format
     *
     * @return array{interface: string, exported_at: string, total_keys: int, locales: array<int, string>, translations: array<string, array<string, string>>}
     */
    public function execute(string $interfaceOrigin, ?string $locale = null): array
    {
        $translations = [];
        /** @var Collection<int, TranslationKey> $keys */
        $keys = $this->translationKeyService->allForInterface($interfaceOrigin);

        // Get all available locales from the data
        /** @var array<int, string> $availableLocales */
        $availableLocales = [];
        /** @var TranslationKey $key */
        foreach ($keys as $key) {
            /** @var TranslationValue $value */
            foreach ($key->values as $value) {
                if (! in_array($value->locale, $availableLocales)) {
                    $availableLocales[] = $value->locale;
                }
            }
        }

        // Sort locales
        sort($availableLocales);

        // If specific locale requested, filter
        if (! in_array($locale, [null, '', '0'], true)) {
            $availableLocales = in_array($locale, $availableLocales) ? [$locale] : [];
        }

        // Build flat translations structure
        foreach ($keys as $key) {
            $fullKey = $key->group !== null && $key->group !== '' ? $key->group.'.'.$key->key : $key->key;
            $keyTranslations = [];

            foreach ($availableLocales as $currentLocale) {
                $value = $key->values->where('locale', $currentLocale)->first();

                // If no value for this locale, try fallback
                if (! $value) {
                    $fallbackLocale = Languages::getFallbackLanguage($currentLocale);
                    if ($fallbackLocale !== $currentLocale) {
                        $value = $key->values->where('locale', $fallbackLocale)->first();
                    }
                }

                if ($value) {
                    $keyTranslations[$currentLocale] = $value->value;
                }
            }

            if ($keyTranslations !== []) {
                $translations[$fullKey] = $keyTranslations;
            }
        }

        return [
            'interface' => $interfaceOrigin,
            'exported_at' => Carbon::now()->toIso8601String(),
            'total_keys' => $keys->count(),
            'locales' => $availableLocales,
            'translations' => $translations,
        ];
    }
}
