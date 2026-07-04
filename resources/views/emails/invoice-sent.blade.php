<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->number }}</title>
</head>
<body style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 14px; color: #1a1a1a; line-height: 1.6; margin: 0; padding: 0; background: #f9fafb;">
<div style="max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb;">
    <div style="background: #111827; padding: 32px 40px;">
        <h1 style="color: #ffffff; margin: 0; font-size: 20px; font-weight: 600;">{{ $invoice->workspace->name }}</h1>
    </div>
    <div style="padding: 40px;">
        <p style="margin-top: 0;">Hi {{ $invoice->client->name }},</p>
        <p>Please find attached your invoice <strong>{{ $invoice->number }}</strong>.</p>

        <table style="width: 100%; border-collapse: collapse; margin: 24px 0; background: #f9fafb; border-radius: 8px; overflow: hidden;">
            <tr>
                <td style="padding: 12px 16px; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em;">Invoice</td>
                <td style="padding: 12px 16px; font-weight: 600;">{{ $invoice->number }}</td>
            </tr>
            <tr>
                <td style="padding: 12px 16px; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em;">Amount</td>
                <td style="padding: 12px 16px; font-weight: 600;">{{ number_format($invoice->total / 100, 2) }} {{ $invoice->currency }}</td>
            </tr>
            @if($invoice->due_at)
            <tr>
                <td style="padding: 12px 16px; color: #6b7280; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em;">Due Date</td>
                <td style="padding: 12px 16px; font-weight: 600;">{{ $invoice->due_at->format('d M Y') }}</td>
            </tr>
            @endif
        </table>

        @if($invoice->notes)
            <p style="color: #4b5563;"><strong>Notes:</strong> {{ $invoice->notes }}</p>
        @endif

        <p>The invoice is attached to this email as a PDF.</p>
        <p style="margin-bottom: 0;">Thank you for your business!</p>
    </div>
    <div style="padding: 20px 40px; background: #f9fafb; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 12px;">
        Sent by {{ $invoice->workspace->name }} via Billr
    </div>
</div>
</body>
</html>
