<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['workspace_id', 'client_id', 'created_by', 'name', 'interval', 'start_on', 'end_on', 'next_run_on', 'currency', 'tax_rate', 'notes', 'status'])]
class RecurringInvoice extends Model
{
    use SoftDeletes;

    public const INTERVALS = ['monthly', 'quarterly', 'yearly'];

    protected function casts(): array
    {
        return [
            'start_on' => 'date',
            'end_on' => 'date',
            'next_run_on' => 'date',
            'tax_rate' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<RecurringInvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(RecurringInvoiceLine::class)->orderBy('sort_order');
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** A schedule past its end date is finished, whatever next_run_on still says. */
    public function hasFinished(): bool
    {
        return $this->end_on !== null && $this->end_on->isBefore(today());
    }

    public function isDue(): bool
    {
        return $this->isActive()
            && ! $this->hasFinished()
            && ! $this->next_run_on->isAfter(today());
    }

    public function advance(CarbonImmutable $from): CarbonImmutable
    {
        return match ($this->interval) {
            'quarterly' => $from->addMonths(3),
            'yearly' => $from->addYear(),
            default => $from->addMonth(),
        };
    }

    /**
     * The first run on or after today, so editing a schedule never schedules a
     * run in the past and a catch-up cannot fire once per missed period.
     */
    public function firstRunOnOrAfterToday(): CarbonImmutable
    {
        $next = CarbonImmutable::parse($this->start_on->toDateString());

        while ($next->isBefore(today())) {
            $next = $this->advance($next);
        }

        return $next;
    }
}
