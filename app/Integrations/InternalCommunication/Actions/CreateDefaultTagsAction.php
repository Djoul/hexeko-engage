<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Actions;

use App\Enums\Languages;
use App\Integrations\InternalCommunication\Enums\TagsDefault;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Models\Financer;

/**
 * Creates default tags for a financer based on TagsDefault enum definitions.
 */
class CreateDefaultTagsAction
{
    /**
     * Create all default tags for the specified financer.
     *
     * This action is idempotent - running it multiple times will not create duplicates.
     * It reuses existing tags by matching English labels (including legacy labels).
     */
    public function handle(Financer $financer): void
    {
        $definitions = TagsDefault::getDefinitions();

        foreach ($definitions as $definition) {
            $translations = $definition['translations'];
            $legacyEnglishLabels = $definition['legacy_english'] ?? [];

            // Find existing tag by current or legacy English label
            $existingTag = $this->findExistingTag(
                $financer,
                $translations[Languages::ENGLISH],
                $legacyEnglishLabels
            );

            if ($existingTag instanceof Tag) {
                // Always update to ensure all translations are present
                $existingTag->update([
                    'label' => $translations,
                ]);
            } else {
                // Create new tag only if it doesn't exist
                Tag::create([
                    'financer_id' => $financer->id,
                    'label' => $translations,
                ]);
            }
        }
    }

    /**
     * Find an existing tag by financer and English label (including legacy labels).
     *
     * @param  array<int, string>  $legacyEnglishLabels
     */
    private function findExistingTag(Financer $financer, string $englishLabel, array $legacyEnglishLabels): ?Tag
    {
        $column = 'label->'.Languages::ENGLISH;

        return Tag::query()
            ->where('financer_id', $financer->id)
            ->where(function ($query) use ($column, $englishLabel, $legacyEnglishLabels): void {
                $query->where($column, $englishLabel);

                if ($legacyEnglishLabels !== []) {
                    $query->orWhereIn($column, $legacyEnglishLabels);
                }
            })
            ->first();
    }
}
