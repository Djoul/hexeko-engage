<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
        .totals { margin-top: 24px; width: 40%; float: right; }
        .totals table { width: 100%; }
        .text-right { text-align: right; }
        .section-title { font-weight: bold; margin-top: 24px; text-transform: uppercase; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>Invoice</h1>
            <p><strong>Number:</strong> {{ $invoice->invoice_number }}</p>
            <p><strong>Date:</strong> {{ $invoice->created_at?->format('Y-m-d') ?? now()->format('Y-m-d') }}</p>
        </div>
        <div>
            <p><strong>Billing Period:</strong></p>
            <p>{{ $invoice->billing_period_start?->format('Y-m-d') }} to {{ $invoice->billing_period_end?->format('Y-m-d') }}</p>
            <p><strong>Due Date:</strong> {{ $invoice->due_date?->format('Y-m-d') }}</p>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Recipient</div>
        <p>{{ $invoice->recipient?->name ?? ucfirst($invoice->recipient_type) }}</p>
        <p>Status: {{ \App\Enums\InvoiceStatus::getDescription($invoice->status) }}</p>
    </div>

    <div class="section">
        <div class="section-title">Items</div>
        <table>
            <thead>
                <tr>
                    <th>Label</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Unit (HTVA)</th>
                    <th class="text-right">Subtotal (HTVA)</th>
                    <th class="text-right">VAT Rate</th>
                    <th class="text-right">VAT Amount</th>
                    <th class="text-right">Total (TTC)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->label['en'] ?? $item->label['fr'] ?? 'Item' }}</td>
                        <td class="text-right">{{ number_format($item->quantity, 0, '.', ' ') }}</td>
                        <td class="text-right">EUR {{ number_format($item->unit_price_htva / 100, 2, '.', ' ') }}</td>
                        <td class="text-right">EUR {{ number_format($item->subtotal_htva / 100, 2, '.', ' ') }}</td>
                        <td class="text-right">{{ number_format((float) $item->vat_rate, 2) }}%</td>
                        <td class="text-right">EUR {{ number_format($item->vat_amount / 100, 2, '.', ' ') }}</td>
                        <td class="text-right">EUR {{ number_format($item->total_ttc / 100, 2, '.', ' ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="totals">
        <table>
            <tbody>
                <tr>
                    <th>Subtotal (HTVA)</th>
                    <td class="text-right">EUR {{ number_format($invoice->subtotal_htva / 100, 2, '.', ' ') }}</td>
                </tr>
                <tr>
                    <th>VAT ({{ number_format((float) $invoice->vat_rate, 2) }}%)</th>
                    <td class="text-right">EUR {{ number_format($invoice->vat_amount / 100, 2, '.', ' ') }}</td>
                </tr>
                <tr>
                    <th>Total (TTC)</th>
                    <td class="text-right">EUR {{ number_format($invoice->total_ttc / 100, 2, '.', ' ') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
