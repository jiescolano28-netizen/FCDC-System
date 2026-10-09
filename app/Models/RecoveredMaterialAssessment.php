<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\StockMovement;
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
    ];

    protected $casts = [
        'accepted_quantity' => 'decimal:2',
        'rejected_quantity' => 'decimal:2',
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

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_assessment_id');
    }
}
