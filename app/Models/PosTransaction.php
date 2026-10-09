<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PosTransaction extends Model
{
    protected $fillable = [
        'transaction_number',
        'employee_id',
        'customer_name',
        'subtotal',
        'vat_rate',
        'vat_amount',
        'total',
        'payment_method',
        'amount_received',
        'change_due',
        'payment_reference',
        'status',
        'completed_at',
        'receipt_company_name',
        'receipt_company_address',
        'receipt_company_phone',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'vat_rate' => 'decimal:4',
        'vat_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'change_due' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PosTransaction $transaction): void {
            $transaction->status ??= 'completed';

            if ($transaction->status !== 'completed') {
                throw new LogicException('POS transactions can only be recorded as completed.');
            }
        });
        static::updating(fn () => throw new LogicException('Completed POS transactions are immutable.'));
        static::deleting(fn () => throw new LogicException('Completed POS transactions are immutable.'));
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PosTransactionLine::class);
    }
}
