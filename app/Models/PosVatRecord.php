<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PosVatRecord extends Model
{
    protected $fillable = [
        'pos_transaction_id',
        'taxable_sales',
        'vat_rate',
        'output_vat',
        'total',
        'completed_at',
    ];

    protected $casts = [
        'taxable_sales' => 'decimal:2',
        'vat_rate' => 'decimal:4',
        'output_vat' => 'decimal:2',
        'total' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PosVatRecord $record): void {
            if (! $record->posTransaction()->where('status', 'completed')->exists()) {
                throw new LogicException('VAT records require a completed POS transaction.');
            }
        });
        static::updating(fn () => throw new LogicException('VAT records are immutable.'));
        static::deleting(fn () => throw new LogicException('VAT records are immutable.'));
    }

    public function posTransaction(): BelongsTo
    {
        return $this->belongsTo(PosTransaction::class);
    }
}
