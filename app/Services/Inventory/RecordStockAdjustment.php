<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordStockAdjustment
{
    public function __construct(private StockMovementTimeline $timeline) {}

    public function handle(
        int $inventoryId,
        float $countedQuantity,
        string $reasonCategory,
        ?string $notes,
        ?string $reference,
        string $effectiveDate,
        int $employeeId,
    ): StockMovement {
        if ($countedQuantity < 0) {
            throw ValidationException::withMessages(['countedQuantity' => 'The counted quantity cannot be negative.']);
        }

        return DB::transaction(function () use ($inventoryId, $countedQuantity, $reasonCategory, $notes, $reference, $effectiveDate, $employeeId) {
            app(EnsureQuantityOnlyMovementIsPreCutover::class)->assertAllowed($effectiveDate);
            $item = Inventory::query()->lockForUpdate()->findOrFail($inventoryId);

            if ($item->status !== 'active') {
                throw ValidationException::withMessages(['adjustmentItemId' => 'Inactive inventory items cannot be adjusted.']);
            }

            $priorBalance = $this->timeline->balanceThroughDate($item, $effectiveDate);
            $countedCents = (int) round($countedQuantity * 100);
            $adjustmentCents = $countedCents - $priorBalance;
            $adjustment = $adjustmentCents / 100;

            $movement = StockMovement::create([
                'inventory_id' => $item->id,
                'posted_by' => $employeeId,
                'type' => 'adjustment',
                'quantity' => $adjustment,
                'reason_category' => $reasonCategory,
                'notes' => $notes,
                'reference' => $reference,
                'effective_date' => $effectiveDate,
                'posted_at' => now(),
            ]);

            $this->timeline->assertNonNegative($item, 'countedQuantity');

            Inventory::query()->whereKey($item->id)->update([
                'qty' => (float) $item->qty + $adjustment,
            ]);

            return $movement;
        });
    }
}
