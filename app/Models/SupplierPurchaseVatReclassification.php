<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPurchaseVatReclassification extends Model
{
    protected $fillable = [
        'supplier_purchase_invoice_id', 'cash_disbursement_id', 'idempotency_key', 'journal_id', 'amount_cents', 'reason', 'accounting_date',
        'prepared_by', 'posted_by',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'accounting_date' => 'date:Y-m-d'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchaseInvoice::class, 'supplier_purchase_invoice_id');
    }

    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(CashDisbursement::class, 'cash_disbursement_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'journal_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierPurchaseVatReclassificationLine::class);
    }
}
