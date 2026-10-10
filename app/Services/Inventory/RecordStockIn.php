<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordStockIn
{
    public function handle(
        int $inventoryId,
        float $quantity,
        string $reasonCategory,
        ?string $notes,
        ?string $reference,
        string $effectiveDate,
        int $employeeId,
    ): StockMovement {
        return DB::transaction(function () use ($inventoryId, $quantity, $reasonCategory, $notes, $reference, $effectiveDate, $employeeId) {
            app(EnsureQuantityOnlyMovementIsPreCutover::class)->assertAllowed($effectiveDate);
            $item = Inventory::query()->lockForUpdate()->findOrFail($inventoryId);

            if ($item->status !== 'active') {
                throw ValidationException::withMessages(['inventoryId' => 'Inactive inventory items cannot receive stock.']);
            }

            $movement = StockMovement::create([
                'inventory_id' => $item->id,
                'posted_by' => $employeeId,
                'type' => 'stock_in',
                'quantity' => $quantity,
                'reason_category' => $reasonCategory,
                'notes' => $notes,
                'reference' => $reference,
                'effective_date' => $effectiveDate,
                'posted_at' => now(),
            ]);

            Inventory::query()->whereKey($item->id)->update([
                'qty' => (float) $item->qty + $quantity,
            ]);

            return $movement;
        });
    }
}
