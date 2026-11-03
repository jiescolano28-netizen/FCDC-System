<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalanceCorrection extends Model
{
    protected $fillable = [
        'opening_journal_id', 'correction_type', 'status', 'posting_date', 'reason', 'details',
        'prepared_by', 'reviewed_by', 'reviewed_at', 'review_note', 'journal_id', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'posting_date' => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
            'posted_at' => 'datetime',
        ];
    }

    public function openingJournal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'opening_journal_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'journal_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'prepared_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'posted_by');
    }
}
