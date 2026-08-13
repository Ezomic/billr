<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invoice;

class AllocateInvoiceNumber
{
    /**
     * Counting rows would skip soft-deleted invoices while their numbers stay in
     * the unique index, so the sequence is read off the highest number instead.
     * Call inside a transaction: the row lock is what stops two concurrent
     * creations in the same workspace landing on the same number.
     */
    public function handle(int $workspaceId): string
    {
        $prefix = sprintf('INV-%d-', now()->year);

        $last = Invoice::withTrashed()
            ->where('workspace_id', $workspaceId)
            ->where('number', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(number) desc, number desc')
            ->lockForUpdate()
            ->value('number');

        $sequence = is_string($last)
            ? ((int) substr($last, strlen($prefix))) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
