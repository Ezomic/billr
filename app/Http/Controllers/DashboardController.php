<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\InteractsWithCurrentUser;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use InteractsWithCurrentUser;

    public function __invoke(): Response
    {
        $workspace = $this->currentUser()->currentWorkspace;

        abort_unless($workspace !== null, 403);

        $base = $workspace->invoices();

        $totalInvoices = (clone $base)->count();

        $totalOutstanding = (clone $base)
            ->whereNotIn('status', ['paid', 'void'])
            ->sum('total');

        $paidThisMonth = (clone $base)
            ->where('status', 'paid')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('total');

        $overdueCount = (clone $base)
            ->where(function ($q) {
                $q->where('status', 'overdue')
                    ->orWhere(function ($q2) {
                        $q2->whereIn('status', ['sent'])
                            ->whereNotNull('due_at')
                            ->where('due_at', '<', today());
                    });
            })
            ->count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalInvoices' => $totalInvoices,
                'totalOutstanding' => (int) $totalOutstanding,
                'paidThisMonth' => (int) $paidThisMonth,
                'overdueCount' => $overdueCount,
            ],
        ]);
    }
}
