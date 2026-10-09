<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseStockMovement
{
    public function handle(int $movementId, int $employeeId): StockMovement
    {
        return DB::transaction(function () use ($movementId, $employeeId) {
            $movement = StockMovement::query()->lockForUpdate()->findOrFail($movementId);

            if ($movement->type === 'reversal' || $movement->reversal()->exists()) {
                throw ValidationException::withMessages([
                    'movement' => 'This stock movement cannot be reversed again.',
                ]);
            }

            $item = Inventory::query()->lockForUpdate()->findOrFail($movement->inventory_id);
            $newQuantity = (float) $item->qty - (float) $movement->quantity;

            if ($newQuantity < 0) {
                throw ValidationException::withMessages([
                    'movement' => 'This movement cannot be reversed because the current balance would become negative.',
                ]);
            }

            $reversal = StockMovement::create([
                'inventory_id' => $item->id,
                'posted_by' => $employeeId,
                'type' => 'reversal',
                'quantity' => -1 * (float) $movement->quantity,
                'reason_category' => $movement->reason_category,
                'reference' => $movement->reference,
                'notes' => 'Reversal of stock movement #'.$movement->id.'. Post the corrected stock transaction separately.',
                'effective_date' => now()->toDateString(),
                'posted_at' => now(),
                'reverses_movement_id' => $movement->id,
            ]);

            Inventory::query()->whereKey($item->id)->update(['qty' => $newQuantity]);

            return $reversal;
        });
    }
}
