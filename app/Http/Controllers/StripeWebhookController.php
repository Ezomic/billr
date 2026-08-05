<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    private const SETTLEMENT_EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
    ];

    public function handle(Request $request, StripeService $stripe): Response
    {
        $sig = $request->header('Stripe-Signature', '');

        try {
            $event = $stripe->constructWebhookEvent(
                payload: $request->getContent(),
                sig: $sig,
            );
        } catch (SignatureVerificationException) {
            abort(400, 'Invalid signature.');
        }

        if ($event->type === 'checkout.session.async_payment_failed') {
            $this->recordFailure($event->data->object);

            return $this->ok();
        }

        if (! in_array($event->type, self::SETTLEMENT_EVENTS, true)) {
            return $this->ok();
        }

        /** @var Session $session */
        $session = $event->data->object;

        // checkout.session.completed also fires for delayed payment methods
        // (SEPA debit, bank transfer) while payment_status is still 'unpaid'.
        // Those settle later on async_payment_succeeded.
        if ($session->payment_status !== 'paid') {
            return $this->ok();
        }

        $invoice = $this->resolveInvoice($session);

        if ($invoice === null || $invoice->status === 'paid') {
            return $this->ok();
        }

        if (! $this->settlementMatchesInvoice($invoice, $session)) {
            Log::warning('Stripe settlement does not match the invoice it references.', [
                'invoice_id' => $invoice->id,
                'session_id' => $session->id,
                'expected' => [$invoice->total, strtolower($invoice->currency)],
                'received' => [$session->amount_total, $session->currency],
            ]);

            return $this->ok();
        }

        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
            'stripe_session_id' => $session->id,
        ]);

        return $this->ok();
    }

    private function resolveInvoice(Session $session): ?Invoice
    {
        $invoiceId = $session->metadata->invoice_id ?? null;

        return is_numeric($invoiceId) ? Invoice::find((int) $invoiceId) : null;
    }

    private function settlementMatchesInvoice(Invoice $invoice, Session $session): bool
    {
        return $session->amount_total === $invoice->total
            && is_string($session->currency)
            && strtolower($session->currency) === strtolower($invoice->currency);
    }

    private function recordFailure(mixed $session): void
    {
        if ($session instanceof Session) {
            Log::warning('Stripe reported a failed asynchronous payment.', [
                'session_id' => $session->id,
                'invoice_id' => $session->metadata->invoice_id ?? null,
            ]);
        }
    }

    private function ok(): Response
    {
        return response('', 200);
    }
}
