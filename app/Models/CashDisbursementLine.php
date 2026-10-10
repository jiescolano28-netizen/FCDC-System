<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDisbursementLine extends Model
{
    protected $fillable = ['accounting_account_id', 'description', 'amount_cents'];

    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(CashDisbursement::class, 'cash_disbursement_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }
}
