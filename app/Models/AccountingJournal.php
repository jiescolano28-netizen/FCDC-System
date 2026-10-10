<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingJournal extends Model
{
    protected $fillable = [
        'book_key', 'reference', 'source_type', 'source_id', 'accounting_date', 'posting_period_id',
        'description', 'status', 'prepared_by',
    ];

    protected function casts(): array
    {
        return ['accounting_date' => 'date:Y-m-d', 'posted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (AccountingJournal $journal): void {
            if ($journal->getOriginal('status') === 'posted'
                && $journal->isDirty(['book_key', 'reference', 'source_type', 'source_id', 'accounting_date', 'posting_period_id', 'description', 'status', 'prepared_by', 'posted_at', 'posted_by', 'approved_at', 'approved_by'])) {
                throw new DomainException('Posted accounting journals are immutable.');
            }
        });
        static::deleting(function (AccountingJournal $journal): void {
            if ($journal->status === 'posted') {
                throw new DomainException('Posted accounting journals cannot be deleted.');
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingJournalLine::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPostingPeriod::class, 'posting_period_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'prepared_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'posted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}
