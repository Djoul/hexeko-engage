<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Actions\Invoicing\BulkUpdateInvoiceStatusAction;
use App\Actions\Invoicing\ConfirmInvoiceAction;
use App\Actions\Invoicing\MarkInvoiceAsPaidAction;
use App\Actions\Invoicing\MarkInvoiceAsSentAction;
use App\Enums\InvoiceIssuerType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\BulkUpdateInvoiceStatusRequest;
use App\Http\Requests\Invoicing\ConfirmInvoiceRequest;
use App\Http\Requests\Invoicing\CreateInvoiceRequest;
use App\Http\Requests\Invoicing\ListInvoicesRequest;
use App\Http\Requests\Invoicing\MarkInvoiceAsPaidRequest;
use App\Http\Requests\Invoicing\UpdateInvoiceRequest;
use App\Http\Resources\Invoicing\InvoiceResource;
use App\Models\Financer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Invoicing\GenerateInvoiceNumberService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

#[Group('Invoicing')]
class InvoiceController extends Controller
{
    public function __construct(
        private readonly GenerateInvoiceNumberService $invoiceNumberService,
        private readonly ConfirmInvoiceAction $confirmInvoiceAction,
        private readonly MarkInvoiceAsSentAction $markInvoiceAsSentAction,
        private readonly MarkInvoiceAsPaidAction $markInvoiceAsPaidAction,
        private readonly BulkUpdateInvoiceStatusAction $bulkUpdateInvoiceStatusAction,
    ) {}

    /**
     * List invoices.
     *
     * Retrieves a paginated list of invoices accessible to the authenticated user.
     * Invoices can be filtered by status, recipient, and billing period.
     * Each invoice includes its associated items.
     *
     * @response AnonymousResourceCollection<InvoiceResource>
     *
     * @throws AuthorizationException
     */
    #[QueryParameter('status', description: 'Filter by invoice status', type: 'string', example: 'draft')]
    #[QueryParameter('recipient_id', description: 'Filter by recipient UUID', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('billing_period_start', description: 'Filter by billing period start date (>=)', type: 'date', example: '2024-01-01')]
    #[QueryParameter('billing_period_end', description: 'Filter by billing period end date (<=)', type: 'date', example: '2024-01-31')]
    #[QueryParameter('per_page', description: 'Number of items per page', type: 'integer', example: 25)]
    public function index(ListInvoicesRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);

        $validated = $request->validated();

        $invoices = Invoice::query()
            ->accessibleByUser($request->user())
            ->with('items')
            ->when(Arr::get($validated, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(Arr::get($validated, 'recipient_id'), fn ($query, $recipientId) => $query->where('recipient_id', $recipientId))
            ->when(Arr::get($validated, 'billing_period_start'), fn ($query, $start) => $query->where('billing_period_start', '>=', $start))
            ->when(Arr::get($validated, 'billing_period_end'), fn ($query, $end) => $query->where('billing_period_end', '<=', $end))
            ->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 25);

        return InvoiceResource::collection($invoices)
            ->additional([
                'meta' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                    'from' => $invoices->firstItem(),
                    'to' => $invoices->lastItem(),
                ],
            ]);
    }

    /**
     * Show invoice details.
     *
     * Returns detailed information about a specific invoice including all its items.
     *
     * @response InvoiceResource
     *
     * @throws AuthorizationException
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return InvoiceResource::make($invoice->load('items'))->response();
    }

    /**
     * Create a new invoice.
     *
     * Creates a new invoice with the specified recipient, billing period, and items.
     * The invoice number is automatically generated based on the invoice type and period.
     * VAT calculations are performed automatically for all items.
     *
     * @response InvoiceResource
     *
     * @status 201
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function store(CreateInvoiceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $vatRate = (float) $validated['vat_rate'];
        $currency = $validated['currency'] ?? 'EUR';
        $billingEnd = Date::parse($validated['billing_period_end']);
        $dueDate = $validated['due_date'] ?? $billingEnd->clone()->addDays(30)->toDateString();

        [$invoiceType, $issuerType, $issuerId] = $this->resolveIssuerContext($validated['recipient_type'], $validated['recipient_id']);

        $this->authorize('create', [Invoice::class, $invoiceType]);

        $items = $validated['items'] ?? [];

        $invoice = DB::transaction(function () use ($validated, $items, $vatRate, $currency, $dueDate, $invoiceType, $issuerType, $issuerId) {
            $invoiceNumber = $this->invoiceNumberService->generate(
                $invoiceType,
                Date::parse($validated['billing_period_end'])
            );

            $totals = $this->calculateTotals($items, $vatRate);

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'invoice_type' => $invoiceType,
                'issuer_type' => $issuerType,
                'issuer_id' => $issuerId,
                'recipient_type' => $validated['recipient_type'],
                'recipient_id' => $validated['recipient_id'],
                'billing_period_start' => $validated['billing_period_start'],
                'billing_period_end' => $validated['billing_period_end'],
                'subtotal_htva' => $totals['subtotal'],
                'vat_rate' => number_format($vatRate, 2, '.', ''),
                'vat_amount' => $totals['vat_amount'],
                'total_ttc' => $totals['total'],
                'currency' => $currency,
                'status' => InvoiceStatus::DRAFT,
                'due_date' => $dueDate,
                'notes' => $validated['notes'] ?? null,
                'metadata' => $validated['metadata'] ?? [],
            ]);

            foreach ($this->prepareInvoiceItems($items, $invoice->id, $vatRate) as $itemData) {
                InvoiceItem::create($itemData);
            }

            return $invoice;
        });

        return InvoiceResource::make($invoice->fresh('items'))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update invoice.
     *
     * Updates an existing invoice. Only draft invoices can be fully updated.
     * Confirmed or sent invoices have limited update capabilities.
     *
     * @response InvoiceResource
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $invoice->update($request->validated());

        return InvoiceResource::make($invoice->fresh('items'))->response();
    }

    /**
     * Delete invoice.
     *
     * Permanently deletes an invoice. Only draft invoices can be deleted.
     *
     * @status 204
     *
     * @throws AuthorizationException
     */
    public function destroy(Invoice $invoice): Response
    {
        $this->authorize('delete', $invoice);

        $invoice->delete();

        return response()->noContent();
    }

    /**
     * Confirm invoice.
     *
     * Confirms a draft invoice, making it official and preventing further modifications.
     * This transitions the invoice from DRAFT to CONFIRMED status.
     *
     * @response InvoiceResource
     *
     * @throws AuthorizationException
     */
    public function confirm(ConfirmInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('confirm', $invoice);

        $this->confirmInvoiceAction->execute($invoice);

        return InvoiceResource::make($invoice->fresh('items'))->response();
    }

    /**
     * Mark invoice as sent.
     *
     * Marks a confirmed invoice as sent to the recipient.
     * This transitions the invoice from CONFIRMED to SENT status.
     *
     * @response InvoiceResource
     *
     * @throws AuthorizationException
     */
    public function markSent(ConfirmInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('markSent', $invoice);

        $this->markInvoiceAsSentAction->execute($invoice);

        return InvoiceResource::make($invoice->fresh('items'))->response();
    }

    /**
     * Mark invoice as paid.
     *
     * Marks an invoice as paid, optionally with a specific payment amount.
     * If no amount is provided, the invoice's total amount is used.
     * This transitions the invoice to PAID status.
     *
     * @response InvoiceResource
     *
     * @throws AuthorizationException
     */
    public function markPaid(MarkInvoiceAsPaidRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('markPaid', $invoice);

        $amountPaid = (int) ($request->validated()['amount_paid'] ?? $invoice->total_ttc);

        $this->markInvoiceAsPaidAction->execute($invoice, $amountPaid);

        return InvoiceResource::make($invoice->fresh('items'))->response();
    }

    /**
     * Bulk update invoice statuses.
     *
     * Updates the status of multiple invoices at once.
     * Useful for batch operations like marking several invoices as sent or paid.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function bulkUpdateStatus(BulkUpdateInvoiceStatusRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->authorize('bulkUpdateStatus', [Invoice::class, $validated['status'], $validated['invoice_ids']]);

        $result = $this->bulkUpdateInvoiceStatusAction->execute($validated);

        return response()->json([
            'message' => 'Invoices status updated',
            'data' => $result,
        ]);
    }

    /**
     * @return array{0:string,1:string,2:?string}
     */
    private function resolveIssuerContext(string $recipientType, string $recipientId): array
    {
        if ($recipientType === 'division') {
            return [InvoiceType::HEXEKO_TO_DIVISION, InvoiceIssuerType::HEXEKO, null];
        }

        if ($recipientType === 'financer') {
            $financer = Financer::query()->findOrFail($recipientId);

            return [
                InvoiceType::DIVISION_TO_FINANCER,
                InvoiceIssuerType::DIVISION,
                $financer->division_id,
            ];
        }

        throw new LogicException('Unsupported recipient type.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal:int, vat_amount:int, total:int}
     */
    private function calculateTotals(array $items, float $vatRate): array
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += ((int) $item['unit_price_htva']) * ((int) $item['quantity']);
        }

        $vatAmount = (int) round($subtotal * ($vatRate / 100));

        return [
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'total' => $subtotal + $vatAmount,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function prepareInvoiceItems(array $items, string $invoiceId, float $vatRate): array
    {
        $prepared = [];

        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $unitPrice = (int) $item['unit_price_htva'];
            $subtotal = $unitPrice * $quantity;
            $vatAmount = (int) round($subtotal * ($vatRate / 100));
            $total = $subtotal + $vatAmount;

            $prepared[] = [
                'id' => $item['id'] ?? Str::uuid()->toString(),
                'invoice_id' => $invoiceId,
                'item_type' => $item['item_type'],
                'module_id' => $item['module_id'] ?? null,
                'label' => $item['label'] ?? null,
                'description' => $item['description'] ?? null,
                'beneficiaries_count' => $item['beneficiaries_count'] ?? null,
                'unit_price_htva' => $unitPrice,
                'quantity' => $quantity,
                'subtotal_htva' => $subtotal,
                'vat_rate' => number_format($vatRate, 2, '.', ''),
                'vat_amount' => $vatAmount,
                'total_ttc' => $total,
                'prorata_percentage' => isset($item['prorata_percentage']) ? number_format((float) $item['prorata_percentage'], 2, '.', '') : null,
                'prorata_days' => Arr::get($item, 'prorata.days'),
                'total_days' => Arr::get($item, 'prorata.total_days'),
                'metadata' => $item['metadata'] ?? [],
            ];
        }

        return $prepared;
    }
}
