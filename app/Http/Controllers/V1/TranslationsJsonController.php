<?php

namespace App\Http\Controllers\V1;

use App\Enums\Languages;
use App\Enums\OrigineInterfaces;
use App\Http\Controllers\Controller;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Services\Models\TranslationKeyService;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TranslationsJsonController extends Controller
{
    public function __construct(
        private readonly TranslationKeyService $translationKeyService
    ) {}

    /**
     * Returns all translations grouped by locale, with dot notation transformed into nested arrays.
     * Format : { locale: { group: { key: ... }, ... }, ... }
     */
    public function allLocales(): JsonResponse
    {
        $originInterface = $this->validateOriginInterface();

        $result = [];

        $keys = $this->translationKeyService->all($originInterface);

        /** @var TranslationKey $key */
        foreach ($keys as $key) {
            foreach ($key->values as $value) {
                /** @var TranslationValue $value */
                $fullKey = $key->group ? $key->group.'.'.$key->key : $key->key;
                $arr = $result[$value->locale] ?? [];
                $arr = $this->arraySetDot($arr, $fullKey, $value->value);
                $result[$value->locale] = $arr;
            }
        }

        ksort($result);
        foreach ($result as &$localeTrads) {
            ksort($localeTrads);
        }

        return response()->json($result);
    }

    /**
     * Returns all translations for a specific locale, as a nested array.
     * Format : { group: { key: ... }, ... }
     */
    #[PathParameter('locale', description: 'the locale code (fr-BE) if secondary language or not existing return fallback (fr-FR) or default (en-GB)', type: 'string', example: 'fr-FR')]
    public function forLocale(string $locale): JsonResponse
    {
        $originInterface = $this->validateOriginInterface();

        $translations = [];
        $fallbackLocale = Languages::getFallbackLanguage($locale);
        $safetyFallbackLocale = config('app.fallback_locale'); // Utiliser en-GB comme dernier fallback

        // Get all keys with their values
        $allKeys = $this->translationKeyService->all($originInterface);

        // Si langue secondaire, charger d'abord les traductions du fallback
        if (Languages::getLanguageType($locale) === 'secondary') {
            /** @var TranslationKey $key */
            foreach ($allKeys as $key) {
                $fallbackValues = $key->values->where('locale', $fallbackLocale);
                $val = $fallbackValues->first();
                if ($val) {
                    $fullKey = $key->group ? $key->group.'.'.$key->key : $key->key;
                    $translations = $this->arraySetDot($translations, $fullKey, $val->value);
                }
            }
        }

        // Load translations for the required language
        /** @var TranslationKey $key */
        foreach ($allKeys as $key) {
            $localeValues = $key->values->where('locale', $locale);
            $val = $localeValues->first();
            if ($val) {
                $fullKey = $key->group ? $key->group.'.'.$key->key : $key->key;
                $translations = $this->arraySetDot($translations, $fullKey, $val->value);
            }
        }

        // Retrieve safety fallback translations
        $safetyTranslations = [];
        /** @var TranslationKey $key */
        foreach ($allKeys as $key) {
            $safetyValues = $key->values->where('locale', $safetyFallbackLocale);
            $val = $safetyValues->first();
            if ($val) {
                $fullKey = $key->group ? $key->group.'.'.$key->key : $key->key;
                $safetyTranslations = $this->arraySetDot($safetyTranslations, $fullKey, $val->value);
            }
        }

        // Complete missing translations with fallback values
        /** @var TranslationKey $key */
        foreach ($allKeys as $key) {
            $fullKey = $key->group ? $key->group.'.'.$key->key : $key->key;

            // Check if the key exists in $translations
            $exists = $this->keyExistsInDotArray($translations, $fullKey);

            if (! $exists) {
                // Search for value in fallback
                $englishValue = $this->getValueFromDotArray($safetyTranslations, $fullKey);
                $translations = $this->arraySetDot($translations, $fullKey, $englishValue);
            }
        }

        ksort($translations);

        // If this is the allLocales endpoint, wrap by locale
        if (request()->route() && request()->route()->uri() === 'translations/json') {
            return response()->json([$locale => $translations]);
        }

        // For forLocale endpoint, return only the nested translations
        return response()->json($translations);
    }

    /**
     * Check if a dot-notated key exists in an array.
     *
     * @param  array<string, mixed>  $array
     */
    private function keyExistsInDotArray(array $array, string $dotKey): bool
    {
        $keys = explode('.', $dotKey);
        $current = $array;

        foreach ($keys as $key) {
            if (! is_array($current) || ! array_key_exists($key, $current)) {
                return false;
            }
            $current = $current[$key];
        }

        return true;
    }

    /**
     * Get a value from an array using dot notation.
     *
     * @param  array<string, mixed>  $array
     * @return mixed|null
     */
    private function getValueFromDotArray(array $array, string $dotKey): mixed
    {
        $keys = explode('.', $dotKey);
        $current = $array;

        foreach ($keys as $key) {
            if (! is_array($current) || ! array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Validate the x-origin-interface header value.
     *
     * @return string The validated origin interface value
     *
     * @throws ValidationException When header is missing or invalid
     */
    private function validateOriginInterface(): string
    {
        $originInterface = request()->header('x-origin-interface');

        // Check if header is present and not empty
        if (empty($originInterface) || ! is_string($originInterface)) {
            throw ValidationException::withMessages([
                'x-origin-interface' => ['The x-origin-interface header is required and cannot be empty.'],
            ]);
        }

        // Check if value matches one of the valid OrigineInterfaces
        $validValues = OrigineInterfaces::getValues();
        if (! in_array($originInterface, $validValues, true)) {
            throw ValidationException::withMessages([
                'x-origin-interface' => [
                    sprintf(
                        'The x-origin-interface header must be one of: %s. Got: %s',
                        implode(', ', $validValues),
                        $originInterface
                    ),
                ],
            ]);
        }

        return $originInterface;
    }

    /**
     * Place une valeur dans un tableau multidimensionnel via une clé pointée.
     *
     * @param  array<string, mixed>  $array
     * @return array<string, mixed>
     */
    private function arraySetDot(array $array, string $key, mixed $value): array
    {
        $keys = explode('.', $key);

        // Create a recursive function to set the value
        $setNestedValue = function (array $arr, array $keyParts, mixed $val) use (&$setNestedValue): array {
            $key = array_shift($keyParts);

            if ($keyParts === []) {
                // We've reached the final key, set the value
                $arr[$key] = $val;
            } else {
                // Initialize the key as an array if it doesn't exist or isn't an array
                if (! array_key_exists($key, $arr) || ! is_array($arr[$key])) {
                    $arr[$key] = [];
                }

                // Recursively set the value in the nested array
                $arr[$key] = $setNestedValue($arr[$key], $keyParts, $val);
            }

            return $arr;
        };

        // Call the recursive function with our array, keys, and value
        return $setNestedValue($array, $keys, $value);
    }
}
