<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Concerns\InteractsWithCurrentUser;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    use InteractsWithCurrentUser;

    public function show(Invoice $invoice): Response
    {
        $user = $this->currentUser();

        $hasAccess = $invoice->client?->portalUsers()->where('user_id', $user->id)->exists() ?? false;
        abort_unless($hasAccess, 403);

        $invoice->load('client', 'lines', 'workspace:id,name,currency');

        return Inertia::render('portal/Invoice', [
            'invoice' => $invoice,
        ]);
    }
}
