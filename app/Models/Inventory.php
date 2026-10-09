<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Inventory extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'category',
        'qty',
        'unit',
        'unit_cost',
        'selling_price',
        'reorder_level',
        'image',
        'description',
        'status',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'reorder_level' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Inventory $inventory): void {
            $inventory->status ??= 'active';
            $inventory->code ??= 'TMP-'.Str::uuid();
        });

        static::created(function (Inventory $inventory): void {
            $inventory->forceFill([
                'code' => 'INV-'.str_pad((string) $inventory->id, 6, '0', STR_PAD_LEFT),
            ])->saveQuietly();

            if ((float) $inventory->qty !== 0.0) {
                $inventory->stockMovements()->create([
                    'posted_by' => auth()->id(),
                    'type' => 'opening_balance',
                    'quantity' => $inventory->qty,
                    'reason_category' => 'opening_balance',
                    'effective_date' => $inventory->created_at->toDateString(),
                    'posted_at' => now(),
                ]);
            }
        });

        static::updating(function (Inventory $inventory): void {
            if ($inventory->isDirty('qty')) {
                throw new LogicException('Inventory quantities must be changed through stock movements.');
            }
        });

        static::deleting(fn () => throw new LogicException('Inventory items must be deactivated, not deleted.'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('effective_date')->orderByDesc('posted_at');
    }

    public function scopeAvailableForSale($query)
    {
        return $query->where('status', 'active')
            ->whereNotNull('selling_price')
            ->where('qty', '>', 0);
    }
}
