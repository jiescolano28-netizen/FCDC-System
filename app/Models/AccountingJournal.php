<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingJournal extends Model
{
    public const ENTRY_TYPE_STANDARD = 'standard';

    public const ENTRY_TYPE_ADJUSTING = 'adjusting';

    protected $fillable = [
        'book_key', 'reference', 'source_type', 'entry_type', 'source_id', 'accounting_date', 'posting_period_id',
        'description', 'external_reference', 'correction_of_id', 'correction_reason', 'status', 'prepared_by',
    ];

    protected function casts(): array
    {
        return ['accounting_date' => 'date:Y-m-d', 'posted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (AccountingJournal $journal): void {
            $persisted = $journal->exists
                ? static::whereKey($journal->getKey())->first(['status', 'source_type'])
                : null;
            if (! $persisted) {
                return;
            }

            if ($persisted->source_type === 'manual' && $persisted->status === 'draft'
                && $journal->status !== 'draft' && $journal->status !== 'pending_review') {
                throw new DomainException('Manual drafts must be submitted for approval before posting.');
            }
            if ($persisted->source_type === 'manual'
                && in_array($persisted->status, ['pending_review', 'approved', 'posted'], true)) {
                $allowedDirty = match ($persisted->status) {
                    'pending_review' => ['status', 'approved_at', 'approved_by', 'updated_at'],
                    'approved' => ['status', 'posted_at', 'posted_by', 'updated_at'],
                    default => ['updated_at'],
                };
                if (array_diff(array_keys($journal->getDirty()), $allowedDirty) !== []) {
                    throw new DomainException('Manual journal details are immutable after submission for review.');
                }
                $allowedStatus = match ($persisted->status) {
                    'pending_review' => ['approved', 'draft'],
                    'approved' => ['posted'],
                    default => [],
                };
                if ($journal->status !== $persisted->status && ! in_array($journal->status, $allowedStatus, true)) {
                    throw new DomainException('Invalid manual journal status transition.');
                }
            }
            if ($journal->status === 'posted' && $journal->isDirty('status')
                && $journal->lines()->whereHas('account', fn ($query) => $query->where('code', '3040'))->exists()) {
                throw new DomainException('Current-year earnings are derived in reporting and cannot be posted to account 3040.');
            }
        });

        static::deleting(function (AccountingJournal $journal): void {
            $persistedStatus = $journal->exists
                ? static::whereKey($journal->getKey())->value('status')
                : null;
            if ($persistedStatus === 'posted') {
                throw new DomainException('Posted accounting journals cannot be deleted.');
            }
            if ($journal->source_type === 'manual' && $persistedStatus !== null && $persistedStatus !== 'draft') {
                throw new DomainException('Only manual draft journals can be deleted.');
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

    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'correction_of_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'correction_of_id');
    }
}
