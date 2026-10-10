<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPurchaseInvoice extends Model
{
    protected $fillable = [
        'supplier_id', 'supplier_code_snapshot', 'supplier_name_snapshot', 'invoice_number',
        'invoice_number_normalized', 'recognition_date', 'due_date', 'gross_amount_cents',
        'description', 'terms', 'receipt_confirmed', 'status', 'prepared_by', 'posted_by',
        'posted_at', 'accounting_journal_id',
    ];

    protected function casts(): array
    {
        return [
            'recognition_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'receipt_confirmed' => 'boolean',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (SupplierPurchaseInvoice $invoice): void {
            if ($invoice->getOriginal('status') === 'posted') {
                throw new DomainException('Posted supplier purchase invoices are immutable.');
            }
        });
        static::deleting(function (SupplierPurchaseInvoice $invoice): void {
            if ($invoice->status === 'posted') {
                throw new DomainException('Posted supplier purchase invoices cannot be deleted.');
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierPurchaseLine::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(AccountingJournal::class, 'accounting_journal_id');
    }

    public function isOverdueOn(string $date): bool
    {
        return $this->status === 'posted' && $this->due_date->toDateString() < $date;
    }
}
