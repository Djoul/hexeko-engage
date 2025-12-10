<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\CreateInvoiceItemRequest;
use App\Http\Requests\Invoicing\UpdateInvoiceItemRequest;
use App\Http\Resources\Invoicing\InvoiceItemResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Group('Invoicing')]
class InvoiceItemController extends Controller
{
    /**
     * List invoice items.
     *
     * Retrieves all items belonging to a specific invoice, ordered by creation date.
     * Each item includes pricing details, VAT calculations, and optional prorata information.
     *
     * @response AnonymousResourceCollection<InvoiceItemResource>
     *
     * @throws AuthorizationException
     */
    public function index(Invoice $invoice): AnonymousResourceCollection
    {
        $this->authorize('view', $invoice);

        $items = $invoice->items()->orderByDesc('created_at')->get();

        return InvoiceItemResource::collection($items);
    }

    /**
     * Create invoice item.
     *
     * Adds a new item to an existing invoice.
     * VAT calculations are performed automatically based on the invoice's VAT rate.
     * The invoice must be in DRAFT status to allow item creation.
     *
     * @response InvoiceItemResource
     *
     * @status 201
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function store(CreateInvoiceItemRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $data = $request->validated();
        $item = $this->createOrUpdateItem($invoice, new InvoiceItem, $data);

        return InvoiceItemResource::make($item)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update invoice item.
     *
     * Updates an existing invoice item.
     * VAT calculations are automatically recalculated when quantity or unit price changes.
     * The invoice must be in DRAFT status to allow item updates.
     *
     * @response InvoiceItemResource
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(UpdateInvoiceItemRequest $request, Invoice $invoice, InvoiceItem $item): JsonResponse
    {
        $this->authorize('update', $invoice);

        $this->ensureOwnership($invoice, $item);

        $updatedItem = $this->createOrUpdateItem($invoice, $item, $request->validated());

        return InvoiceItemResource::make($updatedItem)->response();
    }

    /**
     * Delete invoice item.
     *
     * Permanently removes an item from an invoice.
     * The invoice must be in DRAFT status to allow item deletion.
     *
     * @status 204
     *
     * @throws AuthorizationException
     */
    public function destroy(Invoice $invoice, InvoiceItem $item): Response
    {
        $this->authorize('update', $invoice);

        $this->ensureOwnership($invoice, $item);

        $item->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createOrUpdateItem(Invoice $invoice, InvoiceItem $item, array $data): InvoiceItem
    {
        $item->fill([
            'invoice_id' => $invoice->id,
            'item_type' => $data['item_type'] ?? $item->item_type,
            'module_id' => $data['module_id'] ?? $item->module_id,
            'label' => $data['label'] ?? $item->label,
            'description' => $data['description'] ?? $item->description,
            'beneficiaries_count' => $data['beneficiaries_count'] ?? $item->beneficiaries_count,
            'metadata' => $data['metadata'] ?? $item->metadata ?? [],
        ]);

        $quantity = (int) ($data['quantity'] ?? $item->quantity ?? 1);
        $unitPrice = (int) ($data['unit_price_htva'] ?? $item->unit_price_htva ?? 0);
        $vatRate = (float) $invoice->vat_rate;

        $subtotal = $unitPrice * $quantity;
        $vatAmount = (int) round($subtotal * ($vatRate / 100));
        $total = $subtotal + $vatAmount;

        $item->quantity = $quantity;
        $item->unit_price_htva = $unitPrice;
        $item->subtotal_htva = $subtotal;
        $item->vat_rate = number_format($vatRate, 2, '.', '');
        $item->vat_amount = $vatAmount;
        $item->total_ttc = $total;

        if (! $item->exists) {
            $item->id = $item->id ?: Str::uuid()->toString();
        }

        $item->save();

        return $item->refresh();
    }

    private function ensureOwnership(Invoice $invoice, InvoiceItem $item): void
    {
        if ($item->invoice_id !== $invoice->id) {
            abort(Response::HTTP_NOT_FOUND, 'Invoice item does not belong to the specified invoice.');
        }
    }
}
