<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Inventory extends Model
{
    use LogsActivity, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STOCK_STATUS_IN_STOCK = 'in_stock';

    public const STOCK_STATUS_LOW_STOCK = 'low_stock';

    public const STOCK_STATUS_OUT_OF_STOCK = 'out_of_stock';

    protected $fillable = [
        'name',
        'category',
        'size_specification',
        'inventory_batch_category_id',
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
        'carrying_value_cents' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Inventory $inventory): void {
            $inventory->status ??= self::STATUS_ACTIVE;
            $inventory->code ??= 'TMP-'.Str::uuid();
            if (AccountingJournal::query()->where('source_type', 'opening')->where('source_id', 'FCDC')->where('status', 'posted')->exists()) {
                if ((float) $inventory->qty !== 0.0) {
                    throw new LogicException('New post-cutover inventory items must start with zero quantity.');
                }
                $inventory->unit_cost = '0.00';
                $inventory->carrying_value_cents = 0;
            }
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
            if ($inventory->isDirty('unit_cost')
                && AccountingJournal::query()->where('source_type', 'opening')->where('source_id', 'FCDC')->where('status', 'posted')->exists()) {
                throw new LogicException('Post-cutover accounting cost is derived from the valued stock schedule.');
            }
        });

        static::forceDeleting(fn () => throw new LogicException('Inventory items cannot be permanently deleted.'));
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

    public function batchCategory(): BelongsTo
    {
        return $this->belongsTo(InventoryBatchCategory::class, 'inventory_batch_category_id')->withTrashed();
    }

    public function receiptAttachments(): HasMany
    {
        return $this->hasMany(InventoryReceiptAttachment::class)->latest();
    }

    public function scopeAvailableForSale($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('selling_price')
            ->where('qty', '>', 0);
    }

    public function stockStatus(): string
    {
        $quantity = (float) $this->qty;

        if ($quantity <= 0) {
            return self::STOCK_STATUS_OUT_OF_STOCK;
        }

        if ($quantity <= (float) $this->reorder_level) {
            return self::STOCK_STATUS_LOW_STOCK;
        }

        return self::STOCK_STATUS_IN_STOCK;
    }

    public function stockStatusLabel(): string
    {
        return match ($this->stockStatus()) {
            self::STOCK_STATUS_LOW_STOCK => 'Low Stock',
            self::STOCK_STATUS_OUT_OF_STOCK => 'Out of Stock',
            default => 'In Stock',
        };
    }

    public function stockStatusBadgeClass(): string
    {
        return match ($this->stockStatus()) {
            self::STOCK_STATUS_LOW_STOCK => 'status low',
            self::STOCK_STATUS_OUT_OF_STOCK => 'status empty',
            default => 'status',
        };
    }
}
