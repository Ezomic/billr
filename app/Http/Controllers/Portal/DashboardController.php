<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = Auth::user();

        $clientIds = $user->accessibleClients()->pluck('clients.id');

        $invoices = Invoice::whereIn('client_id', $clientIds)
            ->with('client:id,name')
            ->orderByDesc('issued_at')
            ->get();

        return Inertia::render('portal/Dashboard', [
            'invoices' => $invoices,
        ]);
    }
}
