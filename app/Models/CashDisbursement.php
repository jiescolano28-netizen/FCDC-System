<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class CashDisbursement extends Model
{
    protected $fillable = [
        'reference', 'payee', 'supplier_id', 'receipt_confirmed', 'payment_date', 'method', 'check_number', 'money_account_id', 'description',
        'evidence_reference', 'amount_cents', 'status', 'posting_period_id', 'journal_id', 'reversal_of_id',
        'correction_reason', 'prepared_by', 'posted_by', 'posted_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (CashDisbursement $disbursement): void {
            if ($disbursement->getOriginal('status') === 'posted') {
                throw new LogicException('Posted disbursements are immutable.');
            }
        });
        static::deleting(function (CashDisbursement $disbursement): void {
            if ($disbursement->status === 'posted') {
                throw new LogicException('Posted disbursements are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['payment_date' => 'date:Y-m-d', 'posted_at' => 'datetime', 'receipt_confirmed' => 'boolean'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CashDisbursementLine::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'money_account_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'journal_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }
}
