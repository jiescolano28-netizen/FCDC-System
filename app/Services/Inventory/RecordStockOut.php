<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordStockOut
{
    public function __construct(private StockMovementTimeline $timeline) {}

    public function handle(
        int $inventoryId,
        float $quantity,
        string $reasonCategory,
        ?string $notes,
        ?string $reference,
        string $effectiveDate,
        int $employeeId,
    ): StockMovement {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['issueQuantity' => 'The issued quantity must be greater than zero.']);
        }

        return DB::transaction(function () use ($inventoryId, $quantity, $reasonCategory, $notes, $reference, $effectiveDate, $employeeId) {
            $item = Inventory::query()->lockForUpdate()->findOrFail($inventoryId);

            if ($item->status !== 'active') {
                throw ValidationException::withMessages(['issueItemId' => 'Inactive inventory items cannot issue stock.']);
            }

            $movement = StockMovement::create([
                'inventory_id' => $item->id,
                'posted_by' => $employeeId,
                'type' => 'stock_out',
                'quantity' => -1 * $quantity,
                'reason_category' => $reasonCategory,
                'notes' => $notes,
                'reference' => $reference,
                'effective_date' => $effectiveDate,
                'posted_at' => now(),
            ]);

            $this->timeline->assertNonNegative($item, 'issueQuantity');

            Inventory::query()->whereKey($item->id)->update([
                'qty' => (float) $item->qty - $quantity,
            ]);

            return $movement;
        });
    }
}
