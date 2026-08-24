<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\InteractsWithCurrentUser;
use App\Models\Invoice;
use App\Models\TimeEntry;
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
            'revenueByMonth' => $this->revenueByMonth($workspace->id, $workspace->currency),
            'unbilled' => $this->unbilledTime($workspace->id),
            'overdueInvoices' => $this->overdueInvoices($workspace->id),
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
     * Twelve months of settled money in the workspace currency. Other currencies
     * are deliberately excluded rather than converted: the chart is a trend, and
     * a trend built on invented exchange rates is worse than a narrower one.
     *
     * @return list<array{month: string, total: int}>
     */
    private function revenueByMonth(int $workspaceId, string $currency): array
    {
        $start = today()->startOfMonth()->subMonths(11);

        $paid = Invoice::query()
            ->where('workspace_id', $workspaceId)
            ->where('currency', $currency)
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $start)
            ->get(['paid_at', 'total']);

        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $month = $start->addMonths($i);
            $months[$month->format('Y-m')] = 0;
        }

        foreach ($paid as $invoice) {
            $key = $invoice->paid_at?->format('Y-m');

            if ($key !== null && array_key_exists($key, $months)) {
                $months[$key] += $invoice->total;
            }
        }

        $rows = [];

        foreach ($months as $month => $total) {
            $rows[] = ['month' => (string) $month, 'total' => (int) $total];
        }

        return $rows;
    }

    /**
     * Time that has been worked but never invoiced. The most actionable number
     * on the page: it is money the freelancer has earned and not yet asked for.
     *
     * @return array{minutes: int, amount: int}
     */
    private function unbilledTime(int $workspaceId): array
    {
        $minutes = 0;
        $amount = 0;

        $entries = TimeEntry::query()
            ->whereHas('project', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->whereNotNull('stopped_at')
            ->where('billable', true)
            ->whereDoesntHave('invoices')
            ->with('project:id,hourly_rate')
            ->lazy();

        foreach ($entries as $entry) {
            $entryMinutes = $entry->duration_minutes ?? 0;
            $project = $entry->project;
            $projectRate = $project !== null ? $project->hourly_rate : null;
            $rate = $entry->hourly_rate ?? $projectRate ?? 0;

            $minutes += $entryMinutes;
            $amount += (int) round(($entryMinutes / 60) * $rate);
        }

        return ['minutes' => $minutes, 'amount' => $amount];
    }

    /** @return list<array{id: int, number: string, client: string, currency: string, balance: int, due_at: string|null, days_overdue: int}> */
    private function overdueInvoices(int $workspaceId): array
    {
        $invoices = Invoice::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('status', ['sent', 'overdue'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', today())
            ->with('client:id,name', 'payments')
            ->orderBy('due_at')
            ->limit(5)
            ->get();

        $rows = [];

        foreach ($invoices as $invoice) {
            $client = $invoice->client;

            $rows[] = [
                'id' => $invoice->id,
                'number' => (string) $invoice->number,
                'client' => $client !== null ? (string) $client->name : '',
                'currency' => (string) $invoice->currency,
                'balance' => $invoice->balance(),
                'due_at' => $invoice->due_at?->toDateString(),
                'days_overdue' => $invoice->due_at !== null
                    ? (int) $invoice->due_at->startOfDay()->diffInDays(today(), absolute: false)
                    : 0,
            ];
        }

        return $rows;
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
