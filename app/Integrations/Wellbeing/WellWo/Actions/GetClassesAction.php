<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Actions;

use App\Integrations\Wellbeing\WellWo\Services\WellWoClassService;
use Illuminate\Support\Collection;

class GetClassesAction
{
    public function __construct(
        private readonly WellWoClassService $classService,
        private readonly FilterContentByLanguageAction $filterAction
    ) {}

    public function execute(string $lang = 'es'): Collection
    {
        $classes = $this->classService->getClasses($lang);

        return $this->filterAction->execute(
            $classes,
            $lang,
            'recordedClassesGetDisciplines'
        );
    }
}
