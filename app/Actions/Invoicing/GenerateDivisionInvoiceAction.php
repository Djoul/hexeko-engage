<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Aggregates\DivisionBalanceAggregate;
use App\Aggregates\InvoiceGenerationAggregate;
use App\DTOs\Invoicing\CreateInvoiceDTO;
use App\DTOs\Invoicing\CreateInvoiceItemDTO;
use App\DTOs\Invoicing\InvoiceDTO;
use App\DTOs\Invoicing\ProrataCalculationDTO;
use App\Enums\InvoiceIssuerType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Division;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Invoicing\CalculateInvoiceService;
use App\Services\Invoicing\CalculateProrataService;
use App\Services\Invoicing\GenerateInvoiceNumberService;
use App\Services\Invoicing\GetActiveBeneficiariesService;
use App\Services\Invoicing\VatCalculatorService;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class GenerateDivisionInvoiceAction
{
    public function __construct(
        private readonly CalculateInvoiceItemsAction $calculateInvoiceItemsAction,
        private readonly CalculateInvoiceService $calculateInvoiceService,
        private readonly GenerateInvoiceNumberService $generateInvoiceNumberService,
        private readonly CalculateProrataService $calculateProrataService,
        private readonly GetActiveBeneficiariesService $getActiveBeneficiariesService,
        private readonly VatCalculatorService $vatCalculatorService,
    ) {}

    public function execute(string $divisionId, string $monthYear, string $batchId): InvoiceDTO
    {
        return DB::transaction(function () use ($divisionId, $monthYear, $batchId): InvoiceDTO {
            $division = Division::with('financers')->findOrFail($divisionId);

            try {
                $periodStart = Carbon::createFromFormat('Y-m', $monthYear);
                if (! $periodStart instanceof Carbon) {
                    throw new LogicException("Invalid month-year format: {$monthYear}");
                }
                $periodStart->startOfMonth();
                $periodEnd = $periodStart->clone()->endOfMonth();
            } catch (Exception $e) {
                throw new LogicException("Invalid month-year format: {$monthYear}", 0, $e);
            }

            $beneficiaries = $this->countBeneficiaries($division, $periodStart, $periodEnd);

            if ($beneficiaries === 0) {
                throw new LogicException('Cannot generate division invoice without active beneficiaries.');
            }

            $contractDate = $division->contract_start_date
                ? Carbon::parse($division->contract_start_date)
                : null;

            $contractProrata = $contractDate instanceof Carbon
                ? $this->calculateProrataService->calculateContractProrata($contractDate, $periodStart, $periodEnd)
                : 1.0;

            $prorataDto = $contractDate instanceof Carbon
                ? new ProrataCalculationDTO(
                    percentage: $contractProrata,
                    days: (int) ($periodStart->diffInDays($periodEnd) + 1),
                    totalDays: (int) ($periodStart->diffInDays($periodEnd) + 1),
                    periodStart: $periodStart->toDateString(),
                    periodEnd: $periodEnd->toDateString(),
                    activationDate: $contractDate->lessThan($periodStart) ? $periodStart->toDateString() : $contractDate->toDateString(),
                    deactivationDate: null,
                )
                : null;

            $items = [
                new CreateInvoiceItemDTO(
                    itemType: 'core_package',
                    moduleId: null,
                    unitPriceHtva: $division->core_package_price ?? 0,
                    quantity: $beneficiaries,
                    prorataPercentage: $contractProrata,
                    prorata: $prorataDto,
                ),
            ];

            $metadata = [[
                'label' => [
                    'en' => 'Core package',
                    'fr' => 'Forfait de base',
                ],
                'description' => null,
                'beneficiaries_count' => $beneficiaries,
            ]];

            $createDto = new CreateInvoiceDTO(
                recipientType: 'division',
                recipientId: $division->id,
                billingPeriodStart: $periodStart->toDateString(),
                billingPeriodEnd: $periodEnd->toDateString(),
                items: $items,
            );

            $country = $division->country ?? 'FR';
            $invoiceItems = $this->calculateInvoiceItemsAction->execute($createDto, $country, $metadata);
            $amounts = $this->calculateInvoiceService->calculateInvoiceAmounts($items, $country);

            $invoiceNumber = $this->generateInvoiceNumberService->generate(
                InvoiceType::HEXEKO_TO_DIVISION,
                $periodEnd->clone()
            );

            $vatRate = $division->vat_rate ?? ($this->vatCalculatorService->getVatRate($country) * 100);

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_type' => InvoiceType::HEXEKO_TO_DIVISION,
                'issuer_type' => InvoiceIssuerType::HEXEKO,
                'issuer_id' => null,
                'recipient_type' => Division::class,
                'recipient_id' => $division->id,
                'billing_period_start' => $periodStart->toDateString(),
                'billing_period_end' => $periodEnd->toDateString(),
                'subtotal_htva' => $amounts->subtotalHtva,
                'vat_rate' => number_format((float) $vatRate, 2, '.', ''),
                'vat_amount' => $amounts->vatAmount,
                'total_ttc' => $amounts->totalTtc,
                'currency' => $division->currency ?? 'EUR',
                'status' => InvoiceStatus::DRAFT,
                'due_date' => $periodEnd->clone()->addDays(30)->toDateString(),
                'metadata' => [
                    'month_year' => $monthYear,
                ],
            ]);

            foreach ($items as $index => $createItem) {
                $dto = $invoiceItems[$index];
                $meta = $metadata[$index];
                $prorata = $createItem->prorata;

                $beneficiariesCount = array_key_exists('beneficiaries_count', $meta) && is_int($meta['beneficiaries_count'])
                    ? $meta['beneficiaries_count']
                    : null;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => $createItem->itemType,
                    'module_id' => $createItem->moduleId,
                    'label' => $dto->label,
                    'description' => $dto->description,
                    'beneficiaries_count' => $beneficiariesCount,
                    'unit_price_htva' => $createItem->unitPriceHtva,
                    'quantity' => $createItem->quantity,
                    'subtotal_htva' => $dto->amounts->subtotalHtva,
                    'vat_rate' => number_format((float) $vatRate, 2, '.', ''),
                    'vat_amount' => $dto->amounts->vatAmount,
                    'total_ttc' => $dto->amounts->totalTtc,
                    'prorata_percentage' => number_format($createItem->prorataPercentage, 2, '.', ''),
                    'prorata_days' => $prorata?->days,
                    'total_days' => $prorata?->totalDays,
                    'metadata' => $dto->metadata,
                ]);
            }

            DivisionBalanceAggregate::retrieve($division->id)
                ->invoiceGenerated($division->id, $invoice->id, $invoice->total_ttc, Carbon::now())
                ->persist();

            InvoiceGenerationAggregate::retrieve($batchId)
                ->invoiceCompleted($batchId, $invoice->id, Carbon::now())
                ->persist();

            return InvoiceDTO::fromModel($invoice->load('items'));
        });
    }

    private function countBeneficiaries(Division $division, Carbon $start, Carbon $end): int
    {
        $total = 0;

        foreach ($division->financers as $financer) {
            $total += $this->getActiveBeneficiariesService->getActiveBeneficiariesCount(
                $financer->id,
                $start,
                $end,
            );
        }

        return $total;
    }
}
