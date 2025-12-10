<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Actions;

use App\Integrations\Wellbeing\WellWo\Services\WellWoProgramService;

class GetProgramVideosAction
{
    public function __construct(
        private readonly WellWoProgramService $programService,
        private readonly FilterContentByLanguageAction $filterAction
    ) {}

    public function execute(string $programId, string $lang = 'es'): ?array
    {
        $videos = $this->programService->getProgramVideoById($programId, $lang);

        // Apply language filtering if we have data
        if ($videos !== null && isset($videos['mediaItems'])) {
            $filteredItems = $this->filterAction->execute(
                collect($videos['mediaItems']),
                $lang,
                'recordedProgramsGetVideoList'
            );

            $videos['mediaItems'] = $filteredItems->toArray();
        }

        return $videos;
    }
}
