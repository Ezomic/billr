<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['workspace_id', 'client_id', 'created_by', 'number', 'status', 'currency', 'subtotal', 'tax_amount', 'total', 'tax_rate', 'notes', 'issued_at', 'due_at', 'paid_at', 'stripe_payment_link', 'stripe_session_id'])]
class Invoice extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'due_at' => 'date',
            'paid_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    /** @return BelongsToMany<TimeEntry, $this> */
    public function timeEntries(): BelongsToMany
    {
        return $this->belongsToMany(TimeEntry::class, 'invoice_time_entries');
    }
}
