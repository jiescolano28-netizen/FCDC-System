<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SupplierOpeningInvoice extends Model
{
    protected $fillable = [
        'supplier_id', 'supplier_code_snapshot', 'supplier_name_snapshot', 'supplier_legal_name_snapshot',
        'supplier_tax_identifier_snapshot', 'supplier_address_snapshot', 'invoice_number',
        'invoice_number_normalized', 'recognition_date', 'due_date', 'amount_cents', 'description',
        'terms', 'status', 'opening_journal_id', 'prepared_by', 'posted_at', 'posted_by', 'approved_at',
        'approved_by', 'reversal_of_id',
    ];

    protected function casts(): array
    {
        return [
            'recognition_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'posted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SupplierOpeningInvoice $invoice): void {
            if ($invoice->reversal_of_id === null) {
                return;
            }

            if (DB::transactionLevel() === 0) {
                throw new DomainException('Linked supplier opening invoice reversals must be created inside an accounting transaction.');
            }

            $openingJournal = AccountingJournal::where('source_type', 'opening')
                ->where('source_id', 'FCDC')
                ->lockForUpdate()
                ->first();
            $original = self::whereKey($invoice->reversal_of_id)->lockForUpdate()->first();
            if (
                $original === null
                || $original->status !== 'posted'
                || $original->opening_journal_id !== null
                || $openingJournal?->status === 'posted'
                || $invoice->status !== 'posted'
                || (int) $invoice->supplier_id !== (int) $original->supplier_id
                || (int) $invoice->amount_cents !== (int) $original->amount_cents
                || $original->reversals()->where('status', 'posted')->exists()
            ) {
                throw new DomainException('A linked supplier opening invoice reversal must fully reverse a posted pre-opening invoice for the same supplier.');
            }
        });
        static::updating(function (SupplierOpeningInvoice $invoice): void {
            if ($invoice->getOriginal('status') === 'posted') {
                throw new DomainException('Posted supplier opening invoices are immutable.');
            }
        });
        static::deleting(function (SupplierOpeningInvoice $invoice): void {
            if ($invoice->status === 'posted') {
                throw new DomainException('Posted supplier opening invoices cannot be deleted.');
            }
        });
    }

    public function scopeActivePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted')
            ->whereNull('reversal_of_id')
            ->whereDoesntHave('reversals', fn (Builder $reversals) => $reversals->where('status', 'posted'));
    }

    public function isActivePosted(): bool
    {
        return $this->status === 'posted' && $this->reversal_of_id === null && ! $this->hasPostedReversal();
    }

    public function isOverdueOn(string $date): bool
    {
        return $this->isActivePosted() && $this->due_date->toDateString() < $date;
    }

    public function stateAsOf(string $date): string
    {
        if ($this->status === 'draft') {
            return 'Draft';
        }
        if ($this->reversal_of_id !== null) {
            return 'Reversal · Posted';
        }
        if ($this->hasPostedReversal()) {
            return 'Reversed';
        }

        return $this->isOverdueOn($date) ? 'Posted · Overdue' : 'Posted';
    }

    private function hasPostedReversal(): bool
    {
        return $this->relationLoaded('reversals')
            ? $this->reversals->contains('status', 'posted')
            : $this->reversals()->where('status', 'posted')->exists();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function openingJournal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'opening_journal_id');
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

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }
}
