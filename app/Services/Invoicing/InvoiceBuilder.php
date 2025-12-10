<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\DTOs\Invoicing\CreateInvoiceDTO;
use App\DTOs\Invoicing\CreateInvoiceItemDTO;
use Illuminate\Support\Carbon;
use LogicException;

class InvoiceBuilder
{
    private string $recipientType = 'division';

    private string $recipientId = '';

    private ?Carbon $periodStart = null;

    private ?Carbon $periodEnd = null;

    /**
     * @var array<int, CreateInvoiceItemDTO>
     */
    private array $items = [];

    public function forDivision(string $divisionId): self
    {
        $this->recipientType = 'division';
        $this->recipientId = $divisionId;

        return $this;
    }

    public function forFinancer(string $financerId): self
    {
        $this->recipientType = 'financer';
        $this->recipientId = $financerId;

        return $this;
    }

    public function forPeriod(Carbon $start, Carbon $end): self
    {
        $this->periodStart = $start;
        $this->periodEnd = $end;

        return $this;
    }

    public function addCorePackageItem(int $unitPriceHtva, int $quantity, float $prorata = 1.0): self
    {
        $this->items[] = new CreateInvoiceItemDTO(
            itemType: 'core_package',
            moduleId: null,
            unitPriceHtva: $unitPriceHtva,
            quantity: $quantity,
            prorataPercentage: $prorata,
        );

        return $this;
    }

    public function addModuleItem(string $moduleId, int $unitPriceHtva, int $quantity, float $prorata = 1.0): self
    {
        $this->items[] = new CreateInvoiceItemDTO(
            itemType: 'module',
            moduleId: $moduleId,
            unitPriceHtva: $unitPriceHtva,
            quantity: $quantity,
            prorataPercentage: $prorata,
        );

        return $this;
    }

    public function build(): CreateInvoiceDTO
    {
        if (! $this->periodStart instanceof Carbon || ! $this->periodEnd instanceof Carbon) {
            throw new LogicException('Billing period must be defined before building the invoice DTO.');
        }

        if ($this->recipientId === '') {
            throw new LogicException('Recipient must be defined before building the invoice DTO.');
        }

        return new CreateInvoiceDTO(
            recipientType: $this->recipientType,
            recipientId: $this->recipientId,
            billingPeriodStart: $this->periodStart->toDateString(),
            billingPeriodEnd: $this->periodEnd->toDateString(),
            items: $this->items,
        );
    }
}
