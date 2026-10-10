<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class OpeningInventoryValuation extends Model
{

    protected static function booted(): void
    {
        static::updating(function (OpeningInventoryValuation $valuation): void {
            if (self::openingJournalPosted()) {
                throw new LogicException('Posted opening inventory valuation is immutable.');
            }
            if ($valuation->getOriginal('status') === 'approved'
                && $valuation->status === 'approved'
                && $valuation->isDirty(['cutover_date', 'evidence_reference', 'prepared_by', 'approved_by', 'approved_at'])) {
                throw new LogicException('Approved opening inventory valuation must be revised and reapproved.');
            }
        });
        static::deleting(function (OpeningInventoryValuation $valuation): void {
            if ($valuation->status === 'approved'
                || self::openingJournalPosted()) {
                throw new LogicException('Approved opening inventory valuation is immutable.');
            }
        });
    }

    public static function openingJournalPosted(): bool
    {
        return AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')->where('status', 'posted')->exists();
    }
    protected $fillable = ['book_key', 'cutover_date', 'evidence_reference', 'status', 'prepared_by', 'approved_by', 'approved_at'];

    protected $casts = [
        'cutover_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(OpeningInventoryValuationLine::class);
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
