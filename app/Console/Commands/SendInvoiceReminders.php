<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\InvoiceReminderMail;
use App\Models\Invoice;
use App\Models\InvoiceReminder;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Mail;

class SendInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-reminders {--dry-run : List what would be sent without mailing anything}';

    protected $description = 'Email clients about invoices that are past their due date';

    /**
     * Days past due at which a reminder goes out. One per milestone, so a client
     * gets a nudge on a sensible cadence rather than a mail every morning.
     *
     * @var list<int>
     */
    private const MILESTONES = [3, 7, 14];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;
        $skipped = 0;

        $invoices = Invoice::query()
            ->whereIn('status', ['sent', 'overdue'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', today())
            ->whereHas('workspace', fn ($q) => $q->where('send_payment_reminders', true))
            ->with('workspace', 'client', 'lines', 'reminders')
            ->lazy();

        foreach ($invoices as $invoice) {
            $milestone = $this->dueMilestone($invoice);

            if ($milestone === null) {
                continue;
            }

            $email = $invoice->client?->email;

            if ($email === null || $email === '') {
                $skipped++;
                $this->line("Skipped {$invoice->number}: client has no email address.");

                continue;
            }

            if ($dryRun) {
                $this->line("Would send {$invoice->number} to {$email} ({$milestone} days overdue).");
                $sent++;

                continue;
            }

            if ($this->send($invoice, $milestone, $email)) {
                $sent++;
            }
        }

        $this->info($dryRun
            ? "{$sent} reminder(s) would be sent, {$skipped} skipped."
            : "Sent {$sent} reminder(s), skipped {$skipped}.");

        return self::SUCCESS;
    }

    private function send(Invoice $invoice, int $milestone, string $email): bool
    {
        // Claim the milestone first. The unique index on
        // (invoice_id, days_overdue) is what makes a concurrent or repeated run
        // safe: losing the race means somebody else already mailed this one.
        try {
            $reminder = InvoiceReminder::create([
                'invoice_id' => $invoice->id,
                'days_overdue' => $milestone,
                'sent_to' => $email,
                'sent_at' => now(),
            ]);
        } catch (QueryException) {
            return false;
        }

        try {
            Mail::to($email)->send(new InvoiceReminderMail($invoice, $milestone));
        } catch (\Throwable $e) {
            // Release the claim so the next run retries rather than the client
            // silently never hearing from us.
            $reminder->delete();

            $this->error("Failed to send {$invoice->number} to {$email}: {$e->getMessage()}");

            return false;
        }

        $this->line("Sent {$invoice->number} to {$email} ({$milestone} days overdue).");

        return true;
    }

    /** The highest milestone the invoice has passed and not yet been reminded at. */
    private function dueMilestone(Invoice $invoice): ?int
    {
        if ($invoice->due_at === null) {
            return null;
        }

        $daysOverdue = (int) $invoice->due_at->startOfDay()->diffInDays(today(), absolute: false);

        $alreadySent = $invoice->reminders->pluck('days_overdue')->all();

        $candidates = array_filter(
            self::MILESTONES,
            fn (int $m): bool => $daysOverdue >= $m && ! in_array($m, $alreadySent, true),
        );

        return $candidates === [] ? null : max($candidates);
    }
}
