<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Actions;

use App\Integrations\Wellbeing\WellWo\Services\WellWoProgramService;
use Illuminate\Support\Collection;

class GetProgramsAction
{
    public function __construct(
        private readonly WellWoProgramService $programService,
        private readonly FilterContentByLanguageAction $filterAction
    ) {}

    public function execute(string $lang = 'es'): Collection
    {
        $programs = $this->programService->getPrograms($lang);

        // Apply language filtering
        return $this->filterAction->execute(
            $programs,
            $lang,
            'recordedProgramsGetPrograms'
        );
    }
}
