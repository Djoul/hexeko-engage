<?php

namespace App\Integrations\HRTools\Rules;

use App\Integrations\HRTools\Models\Link;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class LinksExistRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Vérifier que $value est un tableau
        if (! is_array($value)) {
            $fail('La liste des liens doit être un tableau.');

            return;
        }

        // Extraire tous les IDs des liens
        $linkIds = [];
        foreach ($value as $index => $linkData) {
            if (! is_array($linkData) || ! array_key_exists('id', $linkData)) {
                $fail("L'ID du lien à l'index $index est manquant.");

                return;
            }

            $linkIds[] = $linkData['id'];
        }

        // Vérifier que tous les IDs sont uniques
        $uniqueIds = array_unique($linkIds);
        if (count($uniqueIds) !== count($linkIds)) {
            $fail('Les IDs des liens doivent être uniques.');

            return;
        }

        // Vérifier en une seule requête que tous les liens existent
        $existingLinksCount = Link::whereIn('id', $linkIds)->count();

        if ($existingLinksCount !== count($linkIds)) {
            $missingCount = count($linkIds) - $existingLinksCount;
            $fail("$missingCount lien(s) n'existe(nt) pas dans la base de données.");
        }
    }
}
