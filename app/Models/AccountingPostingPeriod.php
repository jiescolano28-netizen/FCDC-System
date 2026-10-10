<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingPostingPeriod extends Model
{
    protected $fillable = [
        'book_key', 'fiscal_year', 'period_month', 'starts_on', 'ends_on', 'status',
        'closed_at', 'closed_by', 'close_reason', 'reopened_at', 'reopened_by', 'reopen_reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d',
            'fiscal_year' => 'integer', 'period_month' => 'integer',
            'closed_at' => 'datetime', 'reopened_at' => 'datetime',
        ];
    }

    public static function firstOrCreateForDate(string $date): self
    {
        $month = CarbonImmutable::parse($date, 'Asia/Manila')->startOfMonth();

        return static::query()->firstOrCreate(
            ['book_key' => 'FCDC', 'fiscal_year' => $month->year, 'period_month' => $month->month],
            [
                'starts_on' => $month->toDateString(),
                'ends_on' => $month->endOfMonth()->toDateString(),
                'status' => 'open',
            ],
        );
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'closed_by');
    }

    public function reopener(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reopened_by');
    }

    public function journals(): HasMany
    {
        return $this->hasMany(AccountingJournal::class, 'posting_period_id');
    }
}
