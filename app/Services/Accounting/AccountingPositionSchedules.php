<?php

namespace App\Services\Accounting;

use App\Models\AccountingAccount;
use App\Models\OpeningInventoryValuation;
use App\Models\StockMovement;
use App\Models\SupplierOpeningInvoice;
use App\Models\SupplierPurchaseInvoice;
use Illuminate\Support\Facades\DB;

class AccountingPositionSchedules
{
    public function accountsPayableAsOf(string $date): int
    {
        $openingInvoices = SupplierOpeningInvoice::query()->where('status', 'posted')->with('openingJournal')
            ->get()->filter(fn (SupplierOpeningInvoice $invoice) => $invoice->openingJournal?->accounting_date?->toDateString() <= $date);
        $purchaseInvoices = SupplierPurchaseInvoice::query()->where('status', 'posted')->whereNull('correction_of_id')
            ->whereDate('recognition_date', '<=', $date)->get();
        $invoiceCents = (int) $openingInvoices->sum(fn ($invoice) => $invoice->reversal_of_id ? -(int) $invoice->amount_cents : (int) $invoice->amount_cents)
            + (int) $purchaseInvoices->sum('gross_amount_cents');
        $apAccountIds = AccountingAccount::query()->where('classification', 'accounts_payable')->pluck('id');
        $correctionApCents = (int) DB::table('accounting_journals')
            ->join('accounting_journal_lines', 'accounting_journal_lines.accounting_journal_id', '=', 'accounting_journals.id')
            ->whereIn('accounting_journals.source_type', ['supplier_purchase_correction', 'direct_purchase_correction'])
            ->where('accounting_journals.status', 'posted')->whereDate('accounting_journals.accounting_date', '<=', $date)
            ->whereIn('accounting_journal_lines.accounting_account_id', $apAccountIds)
            ->selectRaw('COALESCE(SUM(accounting_journal_lines.credit_cents - accounting_journal_lines.debit_cents), 0) as ap_change')
            ->value('ap_change');
        $invoiceCents += $correctionApCents;
        $paymentsCents = (int) DB::table('cash_disbursement_lines')
            ->join('cash_disbursements', 'cash_disbursements.id', '=', 'cash_disbursement_lines.cash_disbursement_id')
            ->where('cash_disbursements.status', 'posted')
            ->whereDate('cash_disbursements.payment_date', '<=', $date)
            ->where(function ($query): void {
                $query->whereNotNull('cash_disbursement_lines.supplier_purchase_invoice_id')
                    ->orWhereNotNull('cash_disbursement_lines.supplier_opening_invoice_id');
            })
            ->selectRaw('COALESCE(SUM(CASE WHEN cash_disbursements.reversal_of_id IS NULL THEN cash_disbursement_lines.amount_cents ELSE -cash_disbursement_lines.amount_cents END), 0) as paid_cents')
            ->value('paid_cents');

        return $invoiceCents - $paymentsCents;
    }

    /** @return array{?int, ?string} */
    public function inventoryValueAsOf(string $date, string $cutoverDate): array
    {
        $valuation = OpeningInventoryValuation::query()->where('book_key', 'FCDC')->where('status', 'approved')->with('lines')->first();
        if (! $valuation || $valuation->cutover_date->toDateString() !== $cutoverDate) {
            return [null, 'An approved opening inventory valuation matching accounting cutover is required.'];
        }
        if (StockMovement::query()->whereNull('value_cents')->where('type', '!=', 'opening_balance')
            ->whereDate('effective_date', '>=', $cutoverDate)->whereDate('effective_date', '<=', $date)->exists()) {
            return [null, 'An unvalued stock movement prevents reliable inventory schedule reconciliation.'];
        }

        $values = $valuation->lines->mapWithKeys(fn ($line) => [$line->inventory_id => (int) $line->carrying_value_cents]);
        $latestMovements = StockMovement::query()->where('type', '!=', 'opening_balance')
            ->whereNotNull('carrying_value_after_cents')->whereDate('effective_date', '>=', $cutoverDate)->whereDate('effective_date', '<=', $date)
            ->orderBy('effective_date')->orderBy('id')->get()->groupBy('inventory_id');
        foreach ($latestMovements as $inventoryId => $movements) {
            $values[$inventoryId] = (int) $movements->last()->carrying_value_after_cents;
        }

        return [(int) $values->sum(), null];
    }
}
