<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; color: #1a1a1a; line-height: 1.5; }
        .container { padding: 48px; max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 48px; }
        .brand { font-size: 24px; font-weight: 700; color: #111; }
        .invoice-meta { text-align: right; }
        .invoice-number { font-size: 20px; font-weight: 700; color: #111; }
        .status { display: inline-block; padding: 2px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 4px; }
        .status-draft { background: #f3f4f6; color: #6b7280; }
        .status-sent { background: #dbeafe; color: #1d4ed8; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        .parties { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .party-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 8px; }
        .party-name { font-size: 16px; font-weight: 600; color: #111; }
        .party-detail { color: #4b5563; margin-top: 2px; }
        .dates { display: flex; gap: 40px; margin-bottom: 40px; padding: 16px 20px; background: #f9fafb; border-radius: 8px; }
        .date-item { }
        .date-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; }
        .date-value { font-size: 14px; font-weight: 500; color: #111; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        thead th { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; padding: 8px 12px; border-bottom: 2px solid #e5e7eb; text-align: left; }
        thead th.right { text-align: right; }
        tbody td { padding: 12px; border-bottom: 1px solid #f3f4f6; color: #374151; }
        tbody td.right { text-align: right; }
        tbody tr:last-child td { border-bottom: none; }
        .totals { margin-left: auto; width: 280px; }
        .total-row { display: flex; justify-content: space-between; padding: 6px 0; color: #4b5563; }
        .total-row.grand { font-size: 18px; font-weight: 700; color: #111; border-top: 2px solid #e5e7eb; padding-top: 12px; margin-top: 4px; }
        .notes { margin-top: 40px; padding: 16px 20px; background: #f9fafb; border-radius: 8px; }
        .notes-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 6px; }
        .notes-text { color: #4b5563; white-space: pre-wrap; }
    </style>
</head>
<body>
<div class="container">

    <div class="header">
        <div class="brand">{{ $invoice->workspace->name }}</div>
        <div class="invoice-meta">
            <div class="invoice-number">{{ $invoice->number }}</div>
            <div class="status status-{{ $invoice->status }}">{{ $invoice->status }}</div>
        </div>
    </div>

    <div class="parties">
        <div>
            <div class="party-label">From</div>
            <div class="party-name">{{ $invoice->workspace->name }}</div>
        </div>
        <div style="text-align: right;">
            <div class="party-label">Bill To</div>
            <div class="party-name">{{ $invoice->client->name }}</div>
            @if($invoice->client->email)
                <div class="party-detail">{{ $invoice->client->email }}</div>
            @endif
            @if($invoice->client->address)
                <div class="party-detail">{{ $invoice->client->address }}</div>
            @endif
            @if($invoice->client->city)
                <div class="party-detail">{{ $invoice->client->city }}@if($invoice->client->postal_code), {{ $invoice->client->postal_code }}@endif</div>
            @endif
            @if($invoice->client->vat_number)
                <div class="party-detail">VAT: {{ $invoice->client->vat_number }}</div>
            @endif
        </div>
    </div>

    <div class="dates">
        @if($invoice->issued_at)
            <div class="date-item">
                <div class="date-label">Issue Date</div>
                <div class="date-value">{{ $invoice->issued_at->format('d M Y') }}</div>
            </div>
        @endif
        @if($invoice->due_at)
            <div class="date-item">
                <div class="date-label">Due Date</div>
                <div class="date-value">{{ $invoice->due_at->format('d M Y') }}</div>
            </div>
        @endif
        @if($invoice->paid_at)
            <div class="date-item">
                <div class="date-label">Paid On</div>
                <div class="date-value">{{ $invoice->paid_at->format('d M Y') }}</div>
            </div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Qty</th>
                <th class="right">Unit Price</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="right">
                        @if($line->unit === 'hours')
                            {{ number_format($line->quantity / 60, 2) }} hrs
                        @else
                            {{ $line->quantity }}
                        @endif
                    </td>
                    <td class="right">{{ number_format($line->unit_price / 100, 2) }} {{ $invoice->currency }}</td>
                    <td class="right">{{ number_format($line->amount / 100, 2) }} {{ $invoice->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row">
            <span>Subtotal</span>
            <span>{{ number_format($invoice->subtotal / 100, 2) }} {{ $invoice->currency }}</span>
        </div>
        @if($invoice->tax_amount > 0)
            <div class="total-row">
                <span>Tax ({{ $invoice->tax_rate }}%)</span>
                <span>{{ number_format($invoice->tax_amount / 100, 2) }} {{ $invoice->currency }}</span>
            </div>
        @endif
        <div class="total-row grand">
            <span>Total</span>
            <span>{{ number_format($invoice->total / 100, 2) }} {{ $invoice->currency }}</span>
        </div>
    </div>

    @if($invoice->notes)
        <div class="notes">
            <div class="notes-label">Notes</div>
            <div class="notes-text">{{ $invoice->notes }}</div>
        </div>
    @endif

</div>
</body>
</html>
