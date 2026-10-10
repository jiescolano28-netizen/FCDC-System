<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CashDisbursementLine extends Model
{
    protected static function booted(): void
    {
        static::updating(function (CashDisbursementLine $line): void {
            if ($line->disbursement?->status === 'posted') {
                throw new LogicException('Posted disbursement allocations are immutable.');
            }
        });
        static::deleting(function (CashDisbursementLine $line): void {
            if ($line->disbursement?->status === 'posted') {
                throw new LogicException('Posted disbursement allocations are immutable.');
            }
        });
    }

    protected $fillable = ['accounting_account_id', 'supplier_purchase_invoice_id', 'supplier_opening_invoice_id', 'inventory_id', 'quantity', 'stock_movement_id', 'description', 'amount_cents'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'amount_cents' => 'integer'];
    }

    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(CashDisbursement::class, 'cash_disbursement_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchaseInvoice::class, 'supplier_purchase_invoice_id');
    }

    public function openingInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierOpeningInvoice::class, 'supplier_opening_invoice_id');
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
