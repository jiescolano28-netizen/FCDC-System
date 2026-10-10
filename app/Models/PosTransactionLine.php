<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PosTransactionLine extends Model
{
    protected $fillable = [
        'pos_transaction_id',
        'inventory_id',
        'inventory_code',
        'item_name',
        'category',
        'unit',
        'quantity',
        'selling_price',
        'unit_cost',
        'line_cost_cents',
        'line_subtotal',
        'vat_amount',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'line_cost_cents' => 'integer',
        'line_subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('POS transaction lines are immutable.'));
        static::deleting(fn () => throw new LogicException('POS transaction lines are immutable.'));
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PosTransaction::class, 'pos_transaction_id');
    }
}
