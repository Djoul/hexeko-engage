<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Invoicing;

use App\Services\Invoicing\VatCalculatorService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class VatCalculatorServiceTest extends TestCase
{
    private VatCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(VatCalculatorService::class);
    }

    #[Test]
    public function it_returns_expected_vat_amounts_for_supported_countries(): void
    {
        $cases = [
            ['FR', 10000, 2000, 12000],
            ['BE', 10000, 2100, 12100],
            ['PT', 10000, 2300, 12300],
            ['LU', 10000, 1700, 11700],
            ['GB', 10000, 2000, 12000],
            ['RO', 10000, 1900, 11900],
        ];

        foreach ($cases as [$country, $amountHtva, $expectedVat, $expectedTotal]) {
            $vatAmount = $this->service->calculateVat($amountHtva, $country);
            $this->assertSame($expectedVat, $vatAmount, "Unexpected VAT for country {$country}");

            $total = $this->service->calculateTotalTtc($amountHtva, $vatAmount);
            $this->assertSame($expectedTotal, $total, "Unexpected TTC for country {$country}");

            $rate = $this->service->getVatRate($country);
            $this->assertGreaterThan(0, $rate);
        }
    }

    #[Test]
    public function it_uses_fallback_vat_rate_for_unsupported_countries(): void
    {
        $vatAmount = $this->service->calculateVat(10000, 'DE');
        $this->assertSame(2000, $vatAmount, 'Fallback VAT should be 20%');

        $rate = $this->service->getVatRate('DE');
        $this->assertSame(0.20, $rate, 'Fallback rate should be 0.20');

        $total = $this->service->calculateTotalTtc(10000, $vatAmount);
        $this->assertSame(12000, $total);
    }
}
