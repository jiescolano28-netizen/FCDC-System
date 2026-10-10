<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RecoveredMaterialAssessment extends Model
{
    protected $fillable = [
        'recovered_material_id',
        'inventory_id',
        'assessed_by',
        'accepted_quantity',
        'rejected_quantity',
        'rejection_reason',
        'supersedes_assessment_id',
        'stock_movement_id',
        'assigned_unit_value_cents',
        'assigned_value_cents',
        'counterpart_accounting_account_id',
        'valuation_approved_by',
        'valuation_approved_at',
    ];

    protected $casts = [
        'accepted_quantity' => 'decimal:2',
        'rejected_quantity' => 'decimal:2',
        'assigned_unit_value_cents' => 'integer',
        'assigned_value_cents' => 'integer',
        'valuation_approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Recovered-material assessments are immutable.'));
        static::deleting(fn () => throw new LogicException('Recovered-material assessments are immutable.'));
    }

    public function recoveredMaterial(): BelongsTo
    {
        return $this->belongsTo(RecoveredMaterial::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
    public function valuationCounterpart(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'counterpart_accounting_account_id');
    }

    public function valuationApprover(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'valuation_approved_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_assessment_id');
    }
}
