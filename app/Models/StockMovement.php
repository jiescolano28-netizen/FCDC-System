<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;
class StockMovement extends Model
{
    protected $fillable = [
        'inventory_id',
        'demolition_project_id',
        'recovered_material_id',
        'posted_by',
        'type',
        'quantity',
        'reason_category',
        'notes',
        'reference',
        'effective_date',
        'posted_at',
        'reverses_movement_id',
    ];


    protected $casts = [
        'quantity' => 'decimal:2',
        'effective_date' => 'date',
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Posted stock movements are immutable.'));
        static::deleting(fn () => throw new LogicException('Posted stock movements are immutable.'));
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }
    public function demolitionProject(): BelongsTo
    {
        return $this->belongsTo(DemolitionProject::class);
    }

    public function recoveredMaterial(): BelongsTo
    {
        return $this->belongsTo(RecoveredMaterial::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'posted_by');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_movement_id');
    }

    public function reversedMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_movement_id');
    }
}
