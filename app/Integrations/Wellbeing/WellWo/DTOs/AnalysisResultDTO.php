<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\DTOs;

class AnalysisResultDTO
{
    public bool $success = false;

    public string $language = '';

    public int $itemsAnalyzed = 0;

    public int $itemsAvailable = 0;

    public ?string $error = null;

    public float $duration = 0.0;
}
