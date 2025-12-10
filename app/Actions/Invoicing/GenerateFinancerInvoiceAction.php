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
use App\Models\Financer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Invoicing\CalculateInvoiceService;
use App\Services\Invoicing\CalculateProrataService;
use App\Services\Invoicing\GenerateInvoiceNumberService;
use App\Services\Invoicing\GetActiveBeneficiariesService;
use App\Services\Invoicing\GetModuleActivationHistoryService;
use App\Services\Invoicing\VatCalculatorService;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class GenerateFinancerInvoiceAction
{
    public function __construct(
        private readonly CalculateInvoiceItemsAction $calculateInvoiceItemsAction,
        private readonly CalculateInvoiceService $calculateInvoiceService,
        private readonly GenerateInvoiceNumberService $generateInvoiceNumberService,
        private readonly CalculateProrataService $calculateProrataService,
        private readonly GetActiveBeneficiariesService $getActiveBeneficiariesService,
        private readonly GetModuleActivationHistoryService $moduleActivationHistoryService,
        private readonly VatCalculatorService $vatCalculatorService,
    ) {}

    public function execute(string $financerId, string $monthYear, string $batchId): ?InvoiceDTO
    {
        return DB::transaction(function () use ($financerId, $monthYear, $batchId): ?InvoiceDTO {
            $financer = Financer::with(['division', 'modules'])->findOrFail($financerId);
            if ($financer->division !== null) {
                $financer->division->loadMissing('modules');
            }

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

            $beneficiaries = $this->getActiveBeneficiariesService->getActiveBeneficiariesCount(
                $financer->id,
                $periodStart,
                $periodEnd,
            );

            // Skip invoice generation for financers without active beneficiaries
            if ($beneficiaries === 0) {
                return null;
            }

            $contractDate = $financer->contract_start_date
                ? Carbon::parse($financer->contract_start_date)
                : null;

            $contractProrata = $contractDate instanceof Carbon
                ? $this->calculateProrataService->calculateContractProrata($contractDate, $periodStart, $periodEnd)
                : 1.0;

            $contractProrataDto = $contractDate instanceof Carbon
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

            $divisionCorePrice = $financer->division !== null ? $financer->division->core_package_price : null;
            $corePackagePrice = $financer->core_package_price ?? $divisionCorePrice ?? 0;

            $items = [
                new CreateInvoiceItemDTO(
                    itemType: 'core_package',
                    moduleId: null,
                    unitPriceHtva: $corePackagePrice,
                    quantity: $beneficiaries,
                    prorataPercentage: $contractProrata,
                    prorata: $contractProrataDto,
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

            foreach ($financer->modules as $module) {
                if (! $this->moduleActivationHistoryService->isModuleActiveInPeriod($financer->id, $module->id, $periodEnd)) {
                    continue;
                }

                $prorata = $this->calculateProrataService->calculateModuleProrata(
                    $financer->id,
                    $module->id,
                    $periodStart,
                    $periodEnd,
                );

                $divisionModulePrice = null;
                if ($financer->division !== null) {
                    $divisionModule = $financer->division->modules->firstWhere('id', $module->id);
                    if ($divisionModule !== null && $divisionModule->pivot !== null) {
                        $divisionModulePrice = $divisionModule->pivot->price_per_beneficiary;
                    }
                }

                $unitPrice = $module->pivot->price_per_beneficiary
                    ?? $divisionModulePrice
                    ?? 0;

                $items[] = new CreateInvoiceItemDTO(
                    itemType: 'module',
                    moduleId: $module->id,
                    unitPriceHtva: $unitPrice,
                    quantity: $beneficiaries,
                    prorataPercentage: $prorata->percentage,
                    prorata: $prorata,
                );

                $metadata[] = [
                    'label' => [
                        'en' => $module->getTranslation('name', 'en-US'),
                        'fr' => $module->getTranslation('name', 'fr-FR'),
                    ],
                    'description' => $module->getTranslation('description', 'en-US'),
                    'beneficiaries_count' => $beneficiaries,
                    'metadata' => [
                        'module_id' => $module->id,
                    ],
                ];
            }

            $createDto = new CreateInvoiceDTO(
                recipientType: 'financer',
                recipientId: $financer->id,
                billingPeriodStart: $periodStart->toDateString(),
                billingPeriodEnd: $periodEnd->toDateString(),
                items: $items,
            );

            $division = $financer->division;
            $country = $division !== null ? $division->country : 'FR';

            $invoiceItems = $this->calculateInvoiceItemsAction->execute($createDto, $country, $metadata);
            $amounts = $this->calculateInvoiceService->calculateInvoiceAmounts($items, $country);

            $invoiceNumber = $this->generateInvoiceNumberService->generate(
                InvoiceType::DIVISION_TO_FINANCER,
                $periodEnd->clone()
            );

            $divisionVatRate = $division !== null ? $division->vat_rate : null;
            $vatRate = $divisionVatRate ?? ($this->vatCalculatorService->getVatRate($country) * 100);

            $divisionCurrency = $division !== null ? $division->currency : null;

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_type' => InvoiceType::DIVISION_TO_FINANCER,
                'issuer_type' => InvoiceIssuerType::DIVISION,
                'issuer_id' => $division !== null ? $division->id : null,
                'recipient_type' => Financer::class,
                'recipient_id' => $financer->id,
                'billing_period_start' => $periodStart->toDateString(),
                'billing_period_end' => $periodEnd->toDateString(),
                'subtotal_htva' => $amounts->subtotalHtva,
                'vat_rate' => number_format((float) $vatRate, 2, '.', ''),
                'vat_amount' => $amounts->vatAmount,
                'total_ttc' => $amounts->totalTtc,
                'currency' => $divisionCurrency ?? 'EUR',
                'status' => InvoiceStatus::CONFIRMED,
                'confirmed_at' => Carbon::now(),
                'due_date' => $periodEnd->clone()->addDays(30)->toDateString(),
                'metadata' => [
                    'month_year' => $monthYear,
                    'division_id' => $division !== null ? $division->id : null,
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

            if ($division !== null) {
                DivisionBalanceAggregate::retrieve($division->id)
                    ->invoiceGenerated($division->id, $invoice->id, $invoice->total_ttc, Carbon::now())
                    ->persist();
            }

            InvoiceGenerationAggregate::retrieve($batchId)
                ->invoiceCompleted($batchId, $invoice->id, Carbon::now())
                ->persist();

            return InvoiceDTO::fromModel($invoice->load('items'));
        });
    }
}
