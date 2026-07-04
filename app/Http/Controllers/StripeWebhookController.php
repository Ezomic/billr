<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
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

        if ($event->type === 'checkout.session.completed') {
            /** @var Session $session */
            $session = $event->data->object;

            $invoiceId = $session->metadata->invoice_id ?? null;

            if ($invoiceId !== null) {
                $invoice = Invoice::find((int) $invoiceId);

                if ($invoice !== null && $invoice->status !== 'paid') {
                    $invoice->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'stripe_session_id' => $session->id,
                    ]);
                }
            }
        }

        return response('', 200);
    }
}
