<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingJournalLine extends Model
{
    protected $fillable = ['accounting_account_id', 'description', 'debit_cents', 'credit_cents'];

    protected static function booted(): void
    {
        static::creating(function (AccountingJournalLine $line): void {
            if (in_array($line->journal?->status, ['pending_review', 'approved', 'posted'], true)) {
                throw new DomainException($line->journal->status === 'posted'
                    ? 'Posted journal lines are immutable.'
                    : 'Journal lines are immutable after submission for review.');
            }
        });
        static::updating(function (AccountingJournalLine $line): void {
            if (in_array($line->journal?->status, ['pending_review', 'approved', 'posted'], true)) {
                throw new DomainException($line->journal->status === 'posted'
                    ? 'Posted journal lines are immutable.'
                    : 'Journal lines are immutable after submission for review.');
            }
        });
        static::deleting(function (AccountingJournalLine $line): void {
            if (in_array($line->journal?->status, ['pending_review', 'approved', 'posted'], true)) {
                throw new DomainException($line->journal->status === 'posted'
                    ? 'Posted journal lines are immutable.'
                    : 'Journal lines are immutable after submission for review.');
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
