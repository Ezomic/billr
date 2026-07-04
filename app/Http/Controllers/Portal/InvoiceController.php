<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function show(Invoice $invoice): Response
    {
        $user = Auth::user();

        $hasAccess = $invoice->client->portalUsers()->where('user_id', $user->id)->exists();
        abort_unless($hasAccess, 403);

        $invoice->load('client', 'lines', 'workspace:id,name,currency');

        return Inertia::render('portal/Invoice', [
            'invoice' => $invoice,
        ]);
    }
}
