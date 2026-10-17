<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingDisclosureNote extends Model
{
    public const TYPE_GENERATED = 'generated';

    public const TYPE_MANUAL = 'manual';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'book_key', 'reporting_period_start', 'reporting_period_end', 'note_reference', 'title', 'body',
        'disclosure_type', 'source_metric', 'generated_amount_cents', 'status', 'prepared_by',
        'approved_by', 'approved_at', 'finalized_by', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'reporting_period_start' => 'date:Y-m-d',
            'reporting_period_end' => 'date:Y-m-d',
            'generated_amount_cents' => 'integer',
            'approved_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $note): void {
            $persisted = $note->exists
                ? static::whereKey($note->getKey())->first(['status'])
                : null;
            if (! $persisted) {
                return;
            }
            if ($persisted->status === self::STATUS_FINALIZED) {
                throw new DomainException('Finalized disclosure notes are protected and cannot be changed.');
            }
            if (in_array($persisted->status, [self::STATUS_PENDING_REVIEW, self::STATUS_APPROVED], true)) {
                $allowedDirty = match ($persisted->status) {
                    self::STATUS_PENDING_REVIEW => ['status', 'approved_at', 'approved_by', 'updated_at'],
                    self::STATUS_APPROVED => ['status', 'finalized_at', 'finalized_by', 'updated_at'],
                };
                if (array_diff(array_keys($note->getDirty()), $allowedDirty) !== []) {
                    throw new DomainException('Disclosure note content is immutable after submission for review.');
                }
                $allowedStatus = match ($persisted->status) {
                    self::STATUS_PENDING_REVIEW => [self::STATUS_APPROVED, self::STATUS_DRAFT],
                    self::STATUS_APPROVED => [self::STATUS_FINALIZED],
                };
                if ($note->status !== $persisted->status && ! in_array($note->status, $allowedStatus, true)) {
                    throw new DomainException('Invalid disclosure note status transition.');
                }
            }
        });

        static::deleting(function (self $note): void {
            $status = $note->exists ? static::whereKey($note->getKey())->value('status') : null;
            if ($status !== null && $status !== self::STATUS_DRAFT) {
                throw new DomainException('Only draft disclosure notes can be deleted.');
            }
        });
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'prepared_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'finalized_by');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isGenerated(): bool
    {
        return $this->disclosure_type === self::TYPE_GENERATED;
    }
}
