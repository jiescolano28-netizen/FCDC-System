<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPurchaseVatReclassificationLine extends Model
{
    protected $fillable = [
        'supplier_purchase_vat_reclassification_id', 'supplier_purchase_line_id', 'cash_disbursement_line_id', 'inventory_id',
        'accounting_account_id', 'consumed_accounting_account_id', 'remaining_inventory_cents',
        'consumed_cost_cents', 'amount_cents',
    ];

    protected function casts(): array
    {
        return [
            'remaining_inventory_cents' => 'integer', 'consumed_cost_cents' => 'integer', 'amount_cents' => 'integer',
        ];
    }

    public function reclassification(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchaseVatReclassification::class, 'supplier_purchase_vat_reclassification_id');
    }

    public function purchaseLine(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchaseLine::class, 'supplier_purchase_line_id');
    }
    public function disbursementLine(): BelongsTo
    {
        return $this->belongsTo(CashDisbursementLine::class, 'cash_disbursement_line_id');
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }

    public function consumedAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'consumed_accounting_account_id');
    }
}
