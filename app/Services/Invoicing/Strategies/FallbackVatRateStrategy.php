<?php

declare(strict_types=1);

namespace App\Services\Invoicing\Strategies;

use Illuminate\Support\Facades\Log;

class FallbackVatRateStrategy implements VatRateStrategyInterface
{
    public function supports(string $country): bool
    {
        Log::warning('Using fallback VAT rate for unsupported country', [
            'country' => $country,
            'fallback_rate' => 0.20,
        ]);

        return true;
    }

    public function getRate(): float
    {
        return 0.20;
    }
}
