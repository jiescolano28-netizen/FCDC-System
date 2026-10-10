<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPurchaseCorrection extends Model
{
    protected $fillable = [
        'supplier_purchase_invoice_id', 'cash_disbursement_id', 'replacement_invoice_id',
        'journal_id', 'corrected_journal_id', 'supplier_id', 'source_type', 'original_amount_cents',
        'corrected_amount_cents', 'refund_due_cents', 'reason', 'accounting_date', 'prepared_by', 'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'accounting_date' => 'date:Y-m-d',
            'original_amount_cents' => 'integer',
            'corrected_amount_cents' => 'integer',
            'refund_due_cents' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchaseInvoice::class, 'supplier_purchase_invoice_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'journal_id');
    }

    public function refundReceipts(): HasMany
    {
        return $this->hasMany(SupplierRefundReceipt::class);
    }

    public function replacementInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchaseInvoice::class, 'replacement_invoice_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
