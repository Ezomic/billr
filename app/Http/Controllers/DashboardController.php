<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\InteractsWithCurrentUser;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use InteractsWithCurrentUser;

    public function __invoke(): Response
    {
        $workspace = $this->currentUser()->requireCurrentWorkspace();

        $invoices = fn (): Builder => Invoice::query()->where('workspace_id', $workspace->id);

        // Net of payments received: with partial payments an invoice can be
        // half settled, and reporting its full total as outstanding overstates
        // what is actually owed.
        $outstanding = $this->totalsByCurrency(
            $invoices()->whereNotIn('status', ['paid', 'void']),
            'total - COALESCE((SELECT SUM(amount) FROM invoice_payments WHERE invoice_payments.invoice_id = invoices.id), 0)',
        );

        $paidThisMonth = $this->totalsByCurrency(
            $invoices()
                ->where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
        );

        $overdueCount = $invoices()
            ->where(function (Builder $q): void {
                $q->where('status', 'overdue')
                    ->orWhere(function (Builder $q2): void {
                        $q2->where('status', 'sent')
                            ->whereNotNull('due_at')
                            ->where('due_at', '<', today());
                    });
            })
            ->count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalInvoices' => $invoices()->count(),
                'overdueCount' => $overdueCount,
            ],
            // Money is reported per currency. Clients override the workspace
            // currency, so one workspace holds invoices in several, and adding
            // them produces a number that looks plausible and means nothing.
            'outstanding' => $outstanding,
            'paidThisMonth' => $paidThisMonth,
            'workspaceCurrency' => $workspace->currency,
        ]);
    }

    /**
     * @param  Builder<Invoice>  $query
     * @param  literal-string  $expression  SQL summed per row. Typed literal so
     *                                      request input can never reach it.
     * @return list<array{currency: string, total: int}>
     */
    private function totalsByCurrency(Builder $query, string $expression = 'total'): array
    {
        $rows = [];

        $aggregated = $query
            ->selectRaw('currency, SUM('.$expression.') as aggregate_total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        foreach ($aggregated as $row) {
            $total = $row->getAttribute('aggregate_total');

            $rows[] = [
                'currency' => (string) $row->currency,
                'total' => is_numeric($total) ? (int) $total : 0,
            ];
        }

        return $rows;
    }
}
