<?php

namespace App\Console\Commands\Translations;

use App\Models\TranslationValue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TranslationsExport extends Command
{
    protected $signature = 'translations:export {locale}';

    protected $description = 'Exporte les traductions de la base en un fichier JSON plat translations/exports/{locale}.json';

    public function handle(): void
    {
        $locale = $this->argument('locale');
        $translations = [];
        $values = TranslationValue::where('locale', $locale)->with('key')->get();
        foreach ($values as $value) {
            $group = $value->key->getAttribute('group');
            $key = $value->key->getAttribute('key');
            $fullKey = (is_string($group) && $group !== '')
                ? ((is_string($key) ? $group.'.'.$key : $group))
                : (is_string($key) ? $key : '');
            if ($fullKey !== '') {
                $translations[$fullKey] = $value->value;
            }
        }
        $json = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $exportPath = base_path('translations/exports');
        File::ensureDirectoryExists($exportPath);
        $file = "$exportPath/{$locale}.json";
        if (is_string($json)) {
            File::put($file, $json);
            $this->info("Exporté dans $file");
        } else {
            $this->error('Erreur lors de l\'encodage JSON des traductions.');
        }
    }
}
