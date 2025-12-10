<?php

namespace App\Console\Commands\Translations;

use App\Models\TranslationKey;
use App\Models\TranslationValue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TranslationsImport extends Command
{
    protected $signature = 'translations:import';

    protected $description = 'Importe les fichiers lang/{locale} dans la base de données (TranslationKey/TranslationValue)';

    public function handle(): ?int
    {
        $langPath = resource_path('lang');
        if (! is_dir($langPath)) {
            $this->error("Le dossier 'resources/lang' est introuvable. Aucune importation possible.");

            return 1;
        }
        $locales = array_filter(scandir($langPath), function (string $f) use ($langPath): bool {
            return is_dir($langPath.DIRECTORY_SEPARATOR.$f) && ! in_array($f, ['.', '..']);
        });
        foreach ($locales as $locale) {
            $files = File::allFiles($langPath.DIRECTORY_SEPARATOR.$locale);
            foreach ($files as $file) {
                $array = include $file->getPathname();
                /** @var array<string, mixed> $array */
                $array = (array) $array;
                $group = $file->getFilenameWithoutExtension();
                $this->importArray($array, $group, $locale);
            }
        }
        $this->info('Importation terminée.');

        return null;
    }

    /**
     * @param  array<string, mixed>  $array
     */
    private function importArray(array $array, string $group, string $locale, string $prefix = ''): void
    {
        foreach ($array as $key => $value) {
            $fullKey = $prefix !== '' && $prefix !== '0' ? "$prefix.$key" : $key;
            if (is_array($value)) {
                // @phpstan-ignore-next-line
                $this->importArray($value, $group, $locale, $fullKey);
            } else {
                $translationKey = TranslationKey::firstOrCreate([
                    'key' => $fullKey,
                    'group' => $group,
                ]);
                TranslationValue::updateOrCreate([
                    'translation_key_id' => $translationKey->id,
                    'locale' => $locale,
                ], [
                    'value' => $value,
                ]);
            }
        }
    }
}
