<?php

namespace App\Services\Inventory;

use App\Models\AccountingJournal;
use Illuminate\Validation\ValidationException;

class EnsureQuantityOnlyMovementIsPreCutover
{
    public function assertAllowed(string $effectiveDate): void
    {
        $cutoverDate = AccountingJournal::query()
            ->where('source_type', 'opening')
            ->where('source_id', 'FCDC')
            ->where('status', 'posted')
            ->value('accounting_date');

        if ($cutoverDate !== null && substr($effectiveDate, 0, 10) >= substr((string) $cutoverDate, 0, 10)) {
            throw ValidationException::withMessages([
                'movement' => 'Quantity-only stock movements are unavailable on or after the approved accounting cutover; use valued stock posting.',
            ]);
        }
    }
}
