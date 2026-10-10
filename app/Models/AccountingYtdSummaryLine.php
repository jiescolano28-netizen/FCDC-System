<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingYtdSummaryLine extends Model
{
    protected $fillable = ['accounting_account_id', 'amount_cents'];

    protected static function booted(): void
    {
        static::creating(function (AccountingYtdSummaryLine $line): void {
            if ($line->summary?->status === 'approved') {
                throw new DomainException('Approved YTD summary lines are immutable.');
            }
        });
        static::updating(function (AccountingYtdSummaryLine $line): void {
            if ($line->summary?->status === 'approved') {
                throw new DomainException('Approved YTD summary lines are immutable.');
            }
        });
        static::deleting(function (AccountingYtdSummaryLine $line): void {
            if ($line->summary?->status === 'approved') {
                throw new DomainException('Approved YTD summary lines cannot be deleted.');
            }
        });
    }

    public function summary(): BelongsTo
    {
        return $this->belongsTo(AccountingYtdSummary::class, 'accounting_ytd_summary_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }
}
