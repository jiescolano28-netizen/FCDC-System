<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingPostingMapping;
use App\Models\CashDisbursement as CashDisbursementRecord;
use App\Models\Inventory;
use App\Models\Supplier;
use App\Models\SupplierOpeningInvoice;
use App\Models\SupplierPurchaseInvoice;
use App\Services\Accounting\CashDisbursementService;
use App\Models\SupplierPurchaseCorrection;
use App\Services\Accounting\SupplierPurchaseCorrectionService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class CashDisbursements extends Component
{
    public ?int $editingId = null;

    #[Url(as: 'disbursement')]
    public ?int $selectedId = null;

    public string $payee = '';

    public bool $receiptConfirmed = false;

    public string $paymentDate = '';

    public string $method = 'Cash';

    public string $moneyAccountId = '';

    public string $reference = '';

    public string $checkNumber = '';

    public string $description = '';

    public string $evidenceReference = '';

    public string $amount = '';

    public array $allocations = [];

    public string $supplierId = '';

    public string $methodFilter = 'All';

    public string $search = '';

    public string $status = 'All';

    public string $fromDate = '';

    public string $toDate = '';

    public string $reversalReason = '';

    public string $directCorrectionReason = '';

    public string $directCorrectionSupplierId = '';

    public array $directCorrectionLines = [];

    public string $refundAmount = '';

    public string $refundMoneyAccountId = '';

    public string $refundReference = '';

    public string $refundEvidenceReference = '';

    public string $refundDate = '';

    public function mount(): void
    {
        $this->paymentDate = now('Asia/Manila')->toDateString();
        $this->allocations = [$this->emptyAllocation()];
        $this->refundDate = now('Asia/Manila')->toDateString();
    }

    public function addAllocation(): void
    {
        $this->authorizePermission('accounting.prepare-disbursements');
        $this->allocations[] = $this->emptyAllocation();
    }

    public function removeAllocation(int $index): void
    {
        $this->authorizePermission('accounting.prepare-disbursements');
        if (count($this->allocations) > 1 && array_key_exists($index, $this->allocations)) {
            array_splice($this->allocations, $index, 1);
        }
    }

    public function saveDraft(): void
    {
        $this->authorizePermission('accounting.prepare-disbursements');
        $allocationInputs = array_map(function (array $line): array {
            if ($this->supplierId !== '') {
                return $line;
            }

            return [
                'accounting_account_id' => ($line['allocation_type'] ?? 'account') === 'account' ? ($line['accounting_account_id'] ?? '') : '',
                'inventory_id' => ($line['allocation_type'] ?? 'account') === 'inventory' ? ($line['inventory_id'] ?? '') : '',
                'quantity' => ($line['allocation_type'] ?? 'account') === 'inventory' ? ($line['quantity'] ?? '') : '',
                'description' => $line['description'] ?? '',
                'amount' => $line['amount'] ?? '',
            ];
        }, $this->allocations);
        $disbursement = app(CashDisbursementService::class)->saveDraft([
            'payee' => $this->supplierId !== '' ? Supplier::query()->findOrFail($this->supplierId)->name : $this->payee,
            'supplierId' => $this->supplierId !== '' ? $this->supplierId : null,
            'paymentDate' => $this->paymentDate,
            'method' => $this->method,
            'moneyAccountId' => $this->moneyAccountId,
            'reference' => $this->reference,
            'checkNumber' => $this->checkNumber,
            'description' => $this->description,
            'evidenceReference' => $this->evidenceReference,
            'amount' => $this->amount,
            'receiptConfirmed' => $this->receiptConfirmed,
            'allocations' => $allocationInputs,
        ], (int) auth()->id(), $this->editingId);
        $this->selectedId = $disbursement->id;
        $this->resetForm();
        session()->flash('disbursement-message', 'Direct disbursement draft saved; it has no cash or journal effect.');
    }

    public function editDraft(int $disbursementId): void
    {
        $this->authorizePermission('accounting.prepare-disbursements');
        $record = CashDisbursementRecord::query()->where('status', 'draft')->whereNull('reversal_of_id')
            ->with('lines')->findOrFail($disbursementId);
        $this->editingId = $record->id;
        $this->payee = $record->payee;
        $this->supplierId = (string) ($record->supplier_id ?? '');
        $this->paymentDate = $record->payment_date->toDateString();
        $this->method = $record->method;
        $this->moneyAccountId = (string) $record->money_account_id;
        $this->reference = $record->reference;
        $this->checkNumber = $record->check_number ?? '';
        $this->description = $record->description;
        $this->evidenceReference = $record->evidence_reference;
        $this->receiptConfirmed = $record->receipt_confirmed;
        $this->amount = number_format($record->amount_cents / 100, 2, '.', '');
        $this->allocations = $record->lines->map(fn ($line) => [
            'allocation_type' => $line->inventory_id ? 'inventory' : 'account',
            'accounting_account_id' => $line->inventory_id ? '' : (string) $line->accounting_account_id,
            'inventory_id' => (string) ($line->inventory_id ?? ''),
            'quantity' => $line->quantity ?? '',
            'invoice_id' => $line->supplier_purchase_invoice_id ? 'purchase:'.$line->supplier_purchase_invoice_id : ($line->supplier_opening_invoice_id ? 'opening:'.$line->supplier_opening_invoice_id : ''),
            'description' => $line->description,
            'amount' => number_format($line->amount_cents / 100, 2, '.', ''),
        ])->all();
    }

    public function deleteDraft(int $disbursementId): void
    {
        $this->authorizePermission('accounting.prepare-disbursements');
        app(CashDisbursementService::class)->deleteDraft($disbursementId);
        if ($this->editingId === $disbursementId) {
            $this->resetForm();
        }
        session()->flash('disbursement-message', 'Direct disbursement draft deleted.');
    }

    public function postDisbursement(int $disbursementId): void
    {
        $this->authorizePermission('accounting.post-disbursements');
        app(CashDisbursementService::class)->post($disbursementId, (int) auth()->id());
        session()->flash('disbursement-message', 'Direct payment and balanced cash/bank journal posted atomically.');
    }

    public function reverseDisbursement(int $disbursementId): void
    {
        $this->authorizePermission('accounting.post-disbursements');
        app(CashDisbursementService::class)->reverse($disbursementId, $this->reversalReason, (int) auth()->id());
        $this->reversalReason = '';
        session()->flash('disbursement-message', 'Linked disbursement reversal posted; original payment history is unchanged.');
    }

    public function startDirectPurchaseCorrection(int $disbursementId): void
    {
        $this->authorizePermission('accounting.correct-supplier-purchases');
        $payment = CashDisbursementRecord::query()->with('lines')->findOrFail($disbursementId);
        abort_unless(
            $payment->status === 'posted' && $payment->supplier_id === null
                && $payment->reversal_of_id === null && ! $payment->reversals()->exists(),
            404,
        );
        $lineChanges = DB::table('supplier_purchase_correction_lines')
            ->join('supplier_purchase_corrections', 'supplier_purchase_corrections.id', '=', 'supplier_purchase_correction_lines.supplier_purchase_correction_id')
            ->where('supplier_purchase_corrections.cash_disbursement_id', $payment->id)
            ->get(['cash_disbursement_line_id', 'direction', 'amount_cents'])
            ->groupBy('cash_disbursement_line_id')
            ->map(fn ($lines) => $lines->sum(fn ($line) => $line->direction === 'increase' ? (int) $line->amount_cents : -(int) $line->amount_cents));
        $this->selectedId = $payment->id;
        $this->directCorrectionSupplierId = '';
        $this->directCorrectionReason = '';
        $this->directCorrectionLines = $payment->lines->map(fn ($line) => [
            'line_id' => $line->id,
            'corrected_amount' => number_format(($line->amount_cents + (int) ($lineChanges[$line->id] ?? 0)) / 100, 2, '.', ''),
            'remaining_inventory' => '0.00',
            'consumed_cost' => '0.00',
            'consumed_accounting_account_id' => '',
        ])->all();
    }

    public function postDirectPurchaseCorrection(int $disbursementId): void
    {
        $this->authorizePermission('accounting.correct-supplier-purchases');
        $allocations = array_map(fn (array $line): array => [
            'line_id' => $line['line_id'],
            'corrected_amount_cents' => $this->amountCents($line['corrected_amount']),
            'remaining_inventory_cents' => $this->amountCents($line['remaining_inventory'] ?: '0'),
            'consumed_cost_cents' => $this->amountCents($line['consumed_cost'] ?: '0'),
            'consumed_accounting_account_id' => $line['consumed_accounting_account_id'] ?: null,
        ], $this->directCorrectionLines);
        app(SupplierPurchaseCorrectionService::class)->correctDirectPurchase($disbursementId, [
            'supplier_id' => $this->directCorrectionSupplierId,
            'reason' => $this->directCorrectionReason,
            'allocations' => $allocations,
        ], (int) auth()->id());
        $this->reset(['directCorrectionReason', 'directCorrectionSupplierId', 'directCorrectionLines']);
        session()->flash('disbursement-message', 'Posted linked direct-purchase cost correction and Supplier Refund Receivable; the payment and receipt history remain unchanged.');
    }

    public function receiveSupplierRefund(int $correctionId): void
    {
        $this->authorizePermission('accounting.post-supplier-refunds');
        app(SupplierPurchaseCorrectionService::class)->receiveRefund($correctionId, [
            'amount_cents' => $this->amountCents($this->refundAmount),
            'money_account_id' => $this->refundMoneyAccountId,
            'reference' => $this->refundReference,
            'evidence_reference' => $this->refundEvidenceReference,
            'receipt_date' => $this->refundDate ?: now('Asia/Manila')->toDateString(),
        ], (int) auth()->id());
        $this->reset(['refundAmount', 'refundMoneyAccountId', 'refundReference', 'refundEvidenceReference']);
        $this->refundDate = now('Asia/Manila')->toDateString();
        session()->flash('disbursement-message', 'Supplier refund receipt posted to Cash/Bank and cleared against the linked refund receivable.');
    }

    public function showDisbursement(int $disbursementId): void
    {
        $this->authorizePermission('accounting.view');
        $this->selectedId = CashDisbursementRecord::query()->findOrFail($disbursementId)->id;
    }

    public function render()
    {
        $this->authorizePermission('accounting.view');
        $query = CashDisbursementRecord::query()->with(['lines.account', 'moneyAccount', 'journal', 'reversalOf', 'reversals'])
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('reference', 'like', $term)->orWhere('payee', 'like', $term)
                        ->orWhere('description', 'like', $term)->orWhere('evidence_reference', 'like', $term)
                        ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', $term)->orWhere('code', 'like', $term))
                        ->orWhereHas('lines.invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', $term))
                        ->orWhereHas('lines.openingInvoice', fn ($invoice) => $invoice->where('invoice_number', 'like', $term));
                });
            })
            ->when(in_array($this->status, ['Draft', 'Posted'], true), fn ($query) => $query->where('status', strtolower($this->status)))
            ->when($this->fromDate !== '', fn ($query) => $query->whereDate('payment_date', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn ($query) => $query->whereDate('payment_date', '<=', $this->toDate))
            ->when($this->methodFilter !== 'All', fn ($query) => $query->where('method', $this->methodFilter))
            ->orderByDesc('payment_date')->orderByDesc('id');
        $disbursements = $query->get();
        $selected = $this->selectedId
            ? CashDisbursementRecord::query()->with(['lines.account', 'lines.invoice', 'lines.openingInvoice', 'lines.inventory', 'lines.stockMovement', 'supplier', 'moneyAccount', 'journal.lines.account', 'reversalOf', 'reversals.journal'])->find($this->selectedId)
            : null;
        $directCorrections = $selected
            ? SupplierPurchaseCorrection::query()->where('cash_disbursement_id', $selected->id)
                ->with(['journal.lines.account', 'refundReceipts.journal'])->orderBy('id')->get()
            : collect();
        $otherMethods = AccountingPostingMapping::query()->where('source', 'like', 'disbursement_method:%')
            ->with('account')->get()->filter(fn ($mapping) => $mapping->isApprovedForPosting())
            ->map(fn ($mapping) => substr($mapping->source, strlen('disbursement_method:')))->values();

        $suppliers = Supplier::query()->orderBy('name')->get();
        $eligibleInvoices = SupplierPurchaseInvoice::query()->where('status', 'posted')->whereNull('correction_of_id')
            ->orderBy('due_date')->get()
            ->filter(fn (SupplierPurchaseInvoice $invoice) => $invoice->outstandingAmountCents() > 0)
            ->map(fn (SupplierPurchaseInvoice $invoice) => [
                'key' => 'purchase:'.$invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'kind' => 'Purchase',
                'due_date' => $invoice->due_date->toDateString(),
                'outstanding_cents' => $invoice->outstandingAmountCents(),
            ])
            ->concat(SupplierOpeningInvoice::activePosted()
                ->when($this->supplierId !== '', fn ($query) => $query->where('supplier_id', $this->supplierId))
                ->orderBy('due_date')->get()
                ->filter(fn (SupplierOpeningInvoice $invoice) => $invoice->outstandingAmountCents() > 0)
                ->map(fn (SupplierOpeningInvoice $invoice) => [
                    'key' => 'opening:'.$invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'kind' => 'Opening',
                    'due_date' => $invoice->due_date->toDateString(),
                    'outstanding_cents' => $invoice->outstandingAmountCents(),
                ]))
            ->sortBy('due_date')->values();

        $inventoryMapping = AccountingPostingMapping::query()->where('source', 'inventory')->with('account')->first();
        $inventoryItems = $inventoryMapping?->isApprovedForPosting()
            ? Inventory::query()->where('status', 'active')->orderBy('name')->get()
            : collect();

        $moneyAccounts = AccountingAccount::query()->where('is_active', true)->whereNotNull('approved_at')
            ->where('type', 'Asset')->whereIn('classification', ['cash', 'bank'])->orderBy('code')->get();
        return view('livewire.accounting.cash-disbursements', [
            'disbursements' => $disbursements,
            'selectedDisbursement' => $selected,
            'suppliers' => $suppliers,
            'eligibleInvoices' => $eligibleInvoices,
            'moneyAccounts' => $moneyAccounts,
            'debitAccounts' => AccountingAccount::query()->where('is_active', true)->whereNotNull('approved_at')
                ->whereIn('type', ['Asset', 'Expense'])
                ->whereNotIn('classification', ['cash', 'bank', 'accounts_receivable', 'card_clearing', 'accounts_payable', 'inventory', 'input_vat', 'output_vat', 'cost_of_goods_sold'])
                ->orderBy('code')->get(),
            'inventoryItems' => $inventoryItems,

            'directCorrections' => $directCorrections,
            'canCorrectPurchases' => auth()->user()?->can('accounting.correct-supplier-purchases'),
            'canPostRefunds' => auth()->user()?->can('accounting.post-supplier-refunds'),
            'otherMethods' => $otherMethods,
            'correctionExpenseAccounts' => AccountingAccount::query()->where('is_active', true)->whereNotNull('approved_at')
                ->where('type', 'Expense')->orderBy('code')->get(),
            'postedTotalCents' => $disbursements->where('status', 'posted')->whereNull('reversal_of_id')->sum('amount_cents'),
        ])->layout('layouts.app', ['title' => 'Cash Disbursements']);
    }

    private function amountCents(string $amount): int
    {
        $amount = trim($amount);
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['correctionLines' => 'Enter PHP amounts with no more than two decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $maximumWhole = (string) intdiv(PHP_INT_MAX, 100);
        if (strlen($whole) > strlen($maximumWhole)
            || (strlen($whole) === strlen($maximumWhole) && strcmp($whole, $maximumWhole) > 0)
            || ($whole === $maximumWhole && (int) str_pad($fraction, 2, '0') > PHP_INT_MAX % 100)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['correctionLines' => 'Amount exceeds the supported PHP-centavo range.']);
        }

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function emptyAllocation(): array
    {
        return ['allocation_type' => 'account', 'accounting_account_id' => '', 'inventory_id' => '', 'quantity' => '', 'invoice_id' => '', 'description' => '', 'amount' => ''];
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'payee', 'supplierId', 'receiptConfirmed', 'method', 'moneyAccountId', 'reference', 'checkNumber', 'description', 'evidenceReference', 'amount']);
        $this->paymentDate = now('Asia/Manila')->toDateString();
        $this->method = 'Cash';
        $this->allocations = [$this->emptyAllocation()];
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
