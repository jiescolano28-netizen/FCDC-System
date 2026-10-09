<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\RecoveredMaterial;
use App\Models\RecoveredMaterialAssessment;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessRecoveredMaterial
{
    public function __construct(private ReverseStockMovement $reversals) {}

    public function handle(
        int $recoveredMaterialId,
        array $assessment,
        int $employeeId,
        bool $correction = false,
    ): RecoveredMaterialAssessment {
        return DB::transaction(function () use ($recoveredMaterialId, $assessment, $employeeId, $correction) {
            $recovery = RecoveredMaterial::query()->lockForUpdate()->findOrFail($recoveredMaterialId);
            $prior = $recovery->assessments()->lockForUpdate()->first();

            if ($prior && ! $correction) {
                throw ValidationException::withMessages(['assessment' => 'This recovery has already been assessed. Use correction to reverse and reassess it.']);
            }
            if (! $prior && $correction) {
                throw ValidationException::withMessages(['assessment' => 'An unassessed recovery cannot be corrected.']);
            }

            $accepted = $this->cents($assessment['accepted_quantity']);
            $rejected = $this->cents($assessment['rejected_quantity']);
            if ($accepted < 0 || $rejected < 0 || $accepted + $rejected !== $this->cents($recovery->quantity)) {
                throw ValidationException::withMessages(['acceptedQuantity' => 'Accepted and rejected quantities must equal the recovered quantity.']);
            }
            if ($rejected > 0 && trim((string) ($assessment['rejection_reason'] ?? '')) === '') {
                throw ValidationException::withMessages(['rejectionReason' => 'A reason is required for rejected material.']);
            }

            if ($prior?->stock_movement_id) {
                $movement = StockMovement::query()->findOrFail($prior->stock_movement_id);
                if ($movement->reversal()->exists()) {
                    throw ValidationException::withMessages(['assessment' => 'This receipt was already reversed; refresh the recovery history before correcting it.']);
                }
                $this->reversals->handle($movement->id, $employeeId);
            }

            $item = $this->inventoryItem($recovery, $assessment);
            if ($item->status !== 'active') {
                throw ValidationException::withMessages(['inventoryId' => 'Inactive inventory items cannot receive recovered stock.']);
            }
            if ($item->unit !== $recovery->unit) {
                throw ValidationException::withMessages(['inventoryId' => 'The inventory unit must exactly match the recovered material unit.']);
            }

            $movement = null;
            if ($accepted > 0) {
                $movement = StockMovement::create([
                    'inventory_id' => $item->id,
                    'demolition_project_id' => $recovery->demolition_project_id,
                    'recovered_material_id' => $recovery->id,
                    'posted_by' => $employeeId,
                    'type' => 'stock_in',
                    'quantity' => number_format($accepted / 100, 2, '.', ''),
                    'reason_category' => 'recovered_material',
                    'notes' => 'Accepted recovered material from project '.$recovery->demolition_project_id,
                    'reference' => 'Recovery #'.$recovery->id,
                    'effective_date' => now()->toDateString(),
                    'posted_at' => now(),
                ]);

                Inventory::query()->whereKey($item->id)->update([
                    'qty' => (float) $item->qty + ($accepted / 100),
                ]);
            }

            return RecoveredMaterialAssessment::create([
                'recovered_material_id' => $recovery->id,
                'inventory_id' => $item->id,
                'assessed_by' => $employeeId,
                'accepted_quantity' => number_format($accepted / 100, 2, '.', ''),
                'rejected_quantity' => number_format($rejected / 100, 2, '.', ''),
                'rejection_reason' => $rejected > 0 ? trim($assessment['rejection_reason']) : null,
                'supersedes_assessment_id' => $prior?->id,
                'stock_movement_id' => $movement?->id,
            ]);

        });
    }

    private function inventoryItem(RecoveredMaterial $recovery, array $assessment): Inventory
    {
        if (($assessment['create_inventory'] ?? false) === true) {
            return Inventory::create([
                'name' => $assessment['new_item_name'],
                'category' => $assessment['new_item_category'],
                'qty' => 0,
                'unit' => $recovery->unit,
                'unit_cost' => $assessment['new_item_unit_cost'],
                'selling_price' => null,
                'reorder_level' => 0,
                'status' => 'active',
                'description' => 'Recovered from demolition project #'.$recovery->demolition_project_id,
            ]);
        }

        return Inventory::query()->lockForUpdate()->findOrFail($assessment['inventory_id']);
    }

    private function cents(string|int|float $quantity): int
    {
        return (int) round((float) $quantity * 100);
    }
}
