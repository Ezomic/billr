<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Concerns\InteractsWithCurrentUser;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use InteractsWithCurrentUser;

    /** @var list<string> */
    private const STATUSES = ['draft', 'sent', 'paid', 'overdue', 'void'];

    public function __invoke(Request $request): Response
    {
        $user = $this->currentUser();

        $clientIds = $user->accessibleClients()->pluck('clients.id');
        $status = $request->string('status')->toString();

        $invoices = Invoice::whereIn('client_id', $clientIds)
            ->when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->with('client:id,name')
            ->orderByDesc('issued_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('portal/Dashboard', [
            'invoices' => $invoices,
            'statuses' => self::STATUSES,
            'filters' => [
                'status' => in_array($status, self::STATUSES, true) ? $status : '',
            ],
        ]);
    }
}
