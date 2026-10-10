<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPurchaseLine extends Model
{
    protected static function booted(): void
    {
        static::saving(function (SupplierPurchaseLine $line): void {
            if (SupplierPurchaseInvoice::query()->whereKey($line->supplier_purchase_invoice_id)->value('status') === 'posted') {
                throw new DomainException('Posted supplier purchase allocations are immutable.');
            }
        });
        static::deleting(function (SupplierPurchaseLine $line): void {
            if (SupplierPurchaseInvoice::query()->whereKey($line->supplier_purchase_invoice_id)->value('status') === 'posted') {
                throw new DomainException('Posted supplier purchase allocations cannot be deleted.');
            }
        });
    }

    protected $fillable = [
        'supplier_purchase_invoice_id', 'inventory_id', 'accounting_account_id',
        'description', 'quantity', 'line_amount_cents',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchaseInvoice::class, 'supplier_purchase_invoice_id');
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }
}
