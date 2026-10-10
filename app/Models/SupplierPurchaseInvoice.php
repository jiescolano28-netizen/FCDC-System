<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class SupplierPurchaseInvoice extends Model
{
    protected $fillable = [
        'supplier_id', 'supplier_code_snapshot', 'supplier_name_snapshot', 'invoice_number',
        'invoice_number_normalized', 'recognition_date', 'due_date', 'gross_amount_cents',
        'description', 'terms', 'receipt_confirmed', 'status', 'prepared_by', 'posted_by',
        'posted_at', 'accounting_journal_id', 'correction_of_id', 'correction_reason', 'supplier_purchase_correction_id',
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

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(CashDisbursementLine::class, 'supplier_purchase_invoice_id');
    }
    public function correctionChildren(): HasMany
    {
        return $this->hasMany(self::class, 'correction_of_id');
    }

    public function correctionParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'correction_of_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(SupplierPurchaseCorrection::class, 'supplier_purchase_invoice_id');
    }

    public function activeCorrectedAmountCents(): int
    {
        $sourceId = $this->correction_of_id ?? $this->id;
        $correction = SupplierPurchaseCorrection::query()->where('supplier_purchase_invoice_id', $sourceId)->latest('id')->first();

        return $correction ? (int) $correction->corrected_amount_cents : (int) $this->gross_amount_cents;
    }

    public function supplierRefundDueCents(): int
    {
        $sourceId = $this->correction_of_id ?? $this->id;

        return max(0, (int) SupplierPurchaseCorrection::query()->where('supplier_purchase_invoice_id', $sourceId)->sum('refund_due_cents')
            - (int) DB::table('supplier_refund_receipts')
                ->join('supplier_purchase_corrections', 'supplier_purchase_corrections.id', '=', 'supplier_refund_receipts.supplier_purchase_correction_id')
                ->where('supplier_purchase_corrections.supplier_purchase_invoice_id', $sourceId)
                ->sum('supplier_refund_receipts.amount_cents'));
    }


    public function paidAmountCents(): int
    {
        $sourceId = $this->correction_of_id ?? $this->id;

        return (int) DB::table('cash_disbursement_lines')
            ->join('cash_disbursements', 'cash_disbursements.id', '=', 'cash_disbursement_lines.cash_disbursement_id')
            ->whereIn('cash_disbursement_lines.supplier_purchase_invoice_id', function ($query) use ($sourceId): void {
                $query->select('id')->from('supplier_purchase_invoices')
                    ->where('id', $sourceId)->orWhere('correction_of_id', $sourceId);
            })
            ->where('cash_disbursements.status', 'posted')
            ->selectRaw('COALESCE(SUM(CASE WHEN cash_disbursements.reversal_of_id IS NULL THEN cash_disbursement_lines.amount_cents ELSE -cash_disbursement_lines.amount_cents END), 0) as paid_cents')
            ->value('paid_cents');
    }
    public function outstandingAmountCents(): int
    {
        return max(0, $this->activeCorrectedAmountCents() - $this->paidAmountCents());
    }

    public function payableStatus(): string
    {
        if ($this->status !== 'posted') {
            return ucfirst($this->status);
        }
        $paid = $this->paidAmountCents();

        return $paid >= $this->activeCorrectedAmountCents() ? 'Paid' : ($paid > 0 ? 'Partially paid' : 'Unpaid');
    }

    public function isOverdueOn(string $date): bool
    {
        return $this->status === 'posted' && $this->outstandingAmountCents() > 0 && $this->due_date->toDateString() < $date;
    }
}
