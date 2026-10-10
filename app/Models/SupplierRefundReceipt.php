<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierRefundReceipt extends Model
{
    protected $fillable = [
        'supplier_purchase_correction_id', 'money_account_id', 'journal_id', 'reference',
        'evidence_reference', 'amount_cents', 'receipt_date', 'posted_by',
    ];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'receipt_date' => 'date:Y-m-d'];
    }

    public function correction(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchaseCorrection::class, 'supplier_purchase_correction_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'journal_id');
    }
}
