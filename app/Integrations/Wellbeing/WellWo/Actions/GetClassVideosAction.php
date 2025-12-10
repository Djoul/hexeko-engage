<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Actions;

use App\Integrations\Wellbeing\WellWo\Services\WellWoClassService;

class GetClassVideosAction
{
    public function __construct(
        private readonly WellWoClassService $classService,
        private readonly FilterContentByLanguageAction $filterAction
    ) {}

    public function execute(string $programId, string $lang = 'es'): ?array
    {
        $videos = $this->classService->getClasseVideoById($programId, $lang);

        // Apply language filtering if we have data
        if ($videos !== null && isset($videos['mediaItems'])) {
            $filteredItems = $this->filterAction->execute(
                collect($videos['mediaItems']),
                $lang,
                'recordedClassesGetVideoList'
            );

            $videos['mediaItems'] = $filteredItems->toArray();
        }

        return $videos;
    }
}
