<?php

namespace Tests\Helpers\Traits;

use Illuminate\Support\Str;

trait GeneratesUniqueModuleIds
{
    protected array $moduleIds = [];

    protected function generateModuleId(int $index = 0): string
    {
        if (! isset($this->moduleIds[$index])) {
            $this->moduleIds[$index] = (string) Str::uuid();
        }

        return $this->moduleIds[$index];
    }

    protected function getCoreModuleId(): string
    {
        return $this->generateModuleId(0);
    }

    protected function getAnalyticsModuleId(): string
    {
        return $this->generateModuleId(1);
    }

    protected function getPremiumModuleId(): string
    {
        return $this->generateModuleId(2);
    }

    protected function resetModuleIds(): void
    {
        $this->moduleIds = [];
    }
}
