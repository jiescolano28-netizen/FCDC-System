<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
