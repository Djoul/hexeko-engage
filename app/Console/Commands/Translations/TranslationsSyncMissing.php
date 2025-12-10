<?php

namespace App\Console\Commands\Translations;

use App\Models\TranslationKey;
use App\Models\TranslationValue;
use Illuminate\Console\Command;

class TranslationsSyncMissing extends Command
{
    protected $signature = 'translations:sync-missing';

    protected $description = 'Identifie les clés de traduction manquantes par langue et propose un rapport';

    public function handle(): void
    {
        $locales = TranslationValue::distinct()->pluck('locale');
        $keys = TranslationKey::pluck('id');
        $missing = [];
        foreach ($locales as $locale) {
            $existing = TranslationValue::where('locale', $locale)->pluck('translation_key_id')->toArray();
            $missingKeys = $keys->diff($existing);
            if ($missingKeys->count()) {
                $missing[$locale] = $missingKeys->values()->all();
            }
        }
        if ($missing === []) {
            $this->info('Aucune clé manquante.');
        } else {
            foreach ($missing as $locale => $keyIds) {
                $this->warn("Locale $locale : clés manquantes : ".implode(',', $keyIds));
            }
        }
    }
}
