<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingJournalLine extends Model
{
    protected $fillable = ['accounting_account_id', 'debit_cents', 'credit_cents'];

    protected static function booted(): void
    {
        static::creating(function (AccountingJournalLine $line): void {
            if ($line->journal?->status === 'posted') {
                throw new DomainException('Posted journal lines are immutable.');
            }
        });
        static::updating(function (AccountingJournalLine $line): void {
            if ($line->journal?->status === 'posted') {
                throw new DomainException('Posted journal lines are immutable.');
            }
        });
        static::deleting(function (AccountingJournalLine $line): void {
            if ($line->journal?->status === 'posted') {
                throw new DomainException('Posted journal lines cannot be deleted.');
            }
        });
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'accounting_journal_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }
}
