@php
    $dueDate = $invoice->due_date?->format('Y-m-d');
@endphp

<p>Bonjour,</p>
<p>Veuillez trouver en piece jointe la facture <strong>{{ $invoice->invoice_number }}</strong>.</p>
<p>
    Montant TTC : <strong>EUR {{ number_format($invoice->total_ttc / 100, 2, '.', ' ') }}</strong><br>
    Periode de facturation : {{ $invoice->billing_period_start?->format('Y-m-d') }} to {{ $invoice->billing_period_end?->format('Y-m-d') }}<br>
    Date d'echeance : {{ $dueDate }}
</p>
<p>Merci,<br>L'equipe Finance</p>
