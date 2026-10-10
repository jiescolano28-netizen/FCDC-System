<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingYtdSummary extends Model
{
    protected $fillable = ['book_key', 'fiscal_year', 'through_date', 'evidence_reference', 'status', 'prepared_by'];

    protected function casts(): array
    {
        return ['fiscal_year' => 'integer', 'through_date' => 'date:Y-m-d', 'approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (AccountingYtdSummary $summary): void {
            if ($summary->getOriginal('status') === 'approved'
                && $summary->isDirty(['book_key', 'fiscal_year', 'through_date', 'evidence_reference', 'status', 'prepared_by', 'approved_at', 'approved_by'])) {
                throw new DomainException('Approved YTD summaries are immutable.');
            }
        });
        static::deleting(function (AccountingYtdSummary $summary): void {
            if ($summary->status === 'approved') {
                throw new DomainException('Approved YTD summaries cannot be deleted.');
            }
        });
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingYtdSummaryLine::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}
