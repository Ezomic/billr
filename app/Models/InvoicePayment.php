<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'recorded_by', 'amount', 'paid_on', 'method', 'note', 'stripe_session_id'])]
class InvoicePayment extends Model
{
    public const METHODS = ['bank', 'card', 'cash', 'stripe', 'other'];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
