<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use Illuminate\Validation\ValidationException;

class StockMovementTimeline
{
    public function balanceThroughDate(Inventory $item, string $effectiveDate): int
    {
        return $item->stockMovements()
            ->whereDate('effective_date', '<=', $effectiveDate)
            ->get(['quantity'])
            ->sum(fn ($movement) => $this->cents($movement->quantity));
    }

    public function assertNonNegative(Inventory $item, string $errorKey): void
    {
        $balance = 0;

        foreach ($item->stockMovements()
            ->reorder()
            ->orderBy('effective_date')
            ->orderBy('posted_at')
            ->orderBy('id')
            ->get(['quantity']) as $movement) {
            $balance += $this->cents($movement->quantity);

            if ($balance < 0) {
                throw ValidationException::withMessages([
                    $errorKey => 'This movement would make a chronological stock balance negative.',
                ]);
            }
        }
    }

    private function cents(string|float $quantity): int
    {
        return (int) round((float) $quantity * 100);
    }
}
