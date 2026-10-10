<?php

namespace App\Services\Inventory;

use App\Models\AccountingAccount;
use App\Models\Inventory;
use App\Models\RecoveredMaterial;
use App\Models\RecoveredMaterialAssessment;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessRecoveredMaterial
{

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
                throw ValidationException::withMessages(['assessment' => 'This recovery has already been assessed. Use correction to reassess it with a linked current-period effect.']);
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

            if ($correction) {
                return app(CorrectRecoveredMaterialAssessment::class)->handle(
                    $recovery,
                    $prior,
                    $assessment,
                    $employeeId,
                );
            }


            $item = $this->inventoryItem($recovery, $assessment);
            if ($item->status !== 'active') {
                throw ValidationException::withMessages(['inventoryId' => 'Inactive inventory items cannot receive recovered stock.']);
            }
            if ($item->unit !== $recovery->unit) {
                throw ValidationException::withMessages(['inventoryId' => 'The inventory unit must exactly match the recovered material unit.']);
            }
            $assessmentAttributes = [
                'recovered_material_id' => $recovery->id,
                'inventory_id' => $item->id,
                'assessed_by' => $employeeId,
                'accepted_quantity' => number_format($accepted / 100, 2, '.', ''),
                'rejected_quantity' => number_format($rejected / 100, 2, '.', ''),
                'rejection_reason' => $rejected > 0 ? trim($assessment['rejection_reason']) : null,
                'supersedes_assessment_id' => $prior?->id,
            ];

            if ($accepted > 0) {
                if (! isset($assessment['assigned_unit_value']) || trim((string) $assessment['assigned_unit_value']) === '') {
                    throw ValidationException::withMessages(['recoveryUnitValue' => 'An approved recovery unit value is required for accepted material.']);
                }
                $unitValueCents = $this->cents($assessment['assigned_unit_value']);
                if ($unitValueCents <= 0) {
                    throw ValidationException::withMessages(['recoveryUnitValue' => 'Recovery unit value must be greater than zero.']);
                }

                $movement = app(RecordValuedStockMovement::class)->handle(
                    $item->id,
                    'stock_in',
                    number_format($accepted / 100, 2, '.', ''),
                    'recovered_material',
                    'Recovery #'.$recovery->id,
                    now('Asia/Manila')->toDateString(),
                    $employeeId,
                    number_format($unitValueCents / 100, 2, '.', ''),
                    'Accepted recovered material from project '.$recovery->demolition_project_id,
                    $recovery->demolition_project_id,
                    $recovery->id,
                    function (StockMovement $movement, AccountingAccount $counterpart, int $approvedUnitValueCents, int $movementValueCents) use ($assessmentAttributes, $employeeId): RecoveredMaterialAssessment {
                        return RecoveredMaterialAssessment::create([
                            ...$assessmentAttributes,
                            'stock_movement_id' => $movement->id,
                            'assigned_unit_value_cents' => $approvedUnitValueCents,
                            'assigned_value_cents' => $movementValueCents,
                            'counterpart_accounting_account_id' => $counterpart->id,
                            'valuation_approved_by' => $employeeId,
                            'valuation_approved_at' => now('UTC'),
                        ]);
                    },
                );

                return RecoveredMaterialAssessment::query()->where('stock_movement_id', $movement->id)->firstOrFail();
            }

            return RecoveredMaterialAssessment::create($assessmentAttributes);

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
                'unit_cost' => '0.00',
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
