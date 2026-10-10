<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingAccount;
use App\Models\CashDisbursementLine;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPurchaseInvoice;
use App\Models\SupplierPurchaseCorrection;
use App\Services\Accounting\SupplierPurchaseService;
use App\Services\Accounting\SupplierPurchaseCorrectionService;
use Livewire\Attributes\Url;
use Livewire\Component;

class SupplierPurchases extends Component
{
    public ?int $editingId = null;

    #[Url(as: 'invoice')]
    public ?int $selectedId = null;

    public string $supplierId = '';

    public string $invoiceNumber = '';

    public string $recognitionDate = '';

    public string $dueDate = '';

    public string $description = '';

    public string $terms = '';

    public bool $receiptConfirmed = false;

    public array $lines = [];

    public string $search = '';

    public string $status = 'All';

    public string $supplierFilter = '';

    public string $fromDate = '';

    public string $toDate = '';

    public string $correctionReason = '';

    public array $correctionLines = [];

    public string $refundAmount = '';

    public string $refundMoneyAccountId = '';

    public string $refundReference = '';

    public string $refundEvidenceReference = '';

    public string $refundDate = '';

    public function mount(): void
    {
        $this->recognitionDate = now('Asia/Manila')->toDateString();
        $this->dueDate = $this->recognitionDate;
        $this->lines = [$this->emptyLine()];
    }

    public function addLine(): void
    {
        $this->authorizePermission('accounting.prepare-supplier-purchases');
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $index): void
    {
        $this->authorizePermission('accounting.prepare-supplier-purchases');
        if (count($this->lines) > 1 && array_key_exists($index, $this->lines)) {
            array_splice($this->lines, $index, 1);
        }
    }

    public function saveDraft(): void
    {
        $this->authorizePermission('accounting.prepare-supplier-purchases');
        $invoice = app(SupplierPurchaseService::class)->saveDraft([
            'id' => $this->editingId,
            'supplier_id' => $this->supplierId,
            'invoice_number' => $this->invoiceNumber,
            'recognition_date' => $this->recognitionDate,
            'due_date' => $this->dueDate,
            'description' => $this->description,
            'terms' => $this->terms,
            'receipt_confirmed' => $this->receiptConfirmed,
            'lines' => $this->lines,
        ], (int) auth()->id());
        $this->selectedId = $invoice->id;
        $this->resetForm();
        session()->flash('purchase-message', 'Supplier purchase draft saved. It has no payable, stock, or journal effect.');
    }

    public function editDraft(int $invoiceId): void
    {
        $this->authorizePermission('accounting.prepare-supplier-purchases');
        $invoice = SupplierPurchaseInvoice::query()->where('status', 'draft')->with('lines')->findOrFail($invoiceId);
        $this->editingId = $invoice->id;
        $this->supplierId = (string) $invoice->supplier_id;
        $this->invoiceNumber = $invoice->invoice_number;
        $this->recognitionDate = $invoice->recognition_date->toDateString();
        $this->dueDate = $invoice->due_date->toDateString();
        $this->description = $invoice->description;
        $this->terms = $invoice->terms ?? '';
        $this->receiptConfirmed = $invoice->receipt_confirmed;
        $this->lines = $invoice->lines->map(fn ($line) => [
            'inventory_id' => (string) ($line->inventory_id ?? ''),
            'accounting_account_id' => (string) ($line->accounting_account_id ?? ''),
            'description' => $line->description,
            'quantity' => $line->quantity ?? '',
            'amount' => number_format($line->line_amount_cents / 100, 2, '.', ''),
        ])->all();
    }

    public function deleteDraft(int $invoiceId): void
    {
        $this->authorizePermission('accounting.prepare-supplier-purchases');
        app(SupplierPurchaseService::class)->deleteDraft($invoiceId);
        if ($this->editingId === $invoiceId) {
            $this->resetForm();
        }
        session()->flash('purchase-message', 'Supplier purchase draft deleted.');
    }

    public function postInvoice(int $invoiceId): void
    {
        $this->authorizePermission('accounting.post-supplier-purchases');
        app(SupplierPurchaseService::class)->post($invoiceId, (int) auth()->id());
        session()->flash('purchase-message', 'Supplier purchase, payable, valued receipts, and journal posted atomically.');
    }

    public function startCorrection(int $invoiceId): void
    {
        $this->authorizePermission('accounting.correct-supplier-purchases');
        $invoice = SupplierPurchaseInvoice::query()->with('lines')->findOrFail($invoiceId);
        abort_unless($invoice->status === 'posted' && ! $invoice->correctionChildren()->exists(), 404);
        $this->selectedId = $invoice->id;
        $this->correctionReason = '';
        $this->correctionLines = $invoice->lines->map(fn ($line) => [
            'line_id' => $line->id,
            'corrected_amount' => number_format($line->line_amount_cents / 100, 2, '.', ''),
            'remaining_inventory' => '0.00',
            'consumed_cost' => '0.00',
            'consumed_accounting_account_id' => '',
        ])->all();
    }

    public function postCorrection(int $invoiceId): void
    {
        $this->authorizePermission('accounting.correct-supplier-purchases');
        $allocations = array_map(function (array $line): array {
            return [
                'line_id' => $line['line_id'],
                'corrected_amount_cents' => $this->amountCents($line['corrected_amount']),
                'remaining_inventory_cents' => $this->amountCents($line['remaining_inventory'] ?: '0'),
                'consumed_cost_cents' => $this->amountCents($line['consumed_cost'] ?: '0'),
                'consumed_accounting_account_id' => $line['consumed_accounting_account_id'] ?: null,
            ];
        }, $this->correctionLines);
        app(SupplierPurchaseCorrectionService::class)->correct($invoiceId, [
            'reason' => $this->correctionReason,
            'allocations' => $allocations,
        ], (int) auth()->id());
        $this->reset(['correctionReason', 'correctionLines']);
        session()->flash('purchase-message', 'Posted linked purchase correction; original invoice, payments, and stock history remain intact.');
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
        session()->flash('purchase-message', 'Supplier refund receipt posted to Cash/Bank and cleared against the linked refund receivable.');
    }

    public function showInvoice(int $invoiceId): void
    {
        $this->authorizePermission('accounting.view');
        $this->selectedId = SupplierPurchaseInvoice::query()->findOrFail($invoiceId)->id;
    }

    public function render()
    {
        $this->authorizePermission('accounting.view');
        $today = now('Asia/Manila')->toDateString();
        $query = SupplierPurchaseInvoice::query()->with(['lines.inventory', 'lines.account', 'journal', 'correctionParent', 'correctionChildren'])
            ->when(trim($this->search) !== '', function ($query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('invoice_number', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('supplier_name_snapshot', 'like', $term)
                        ->orWhereHas('supplier', fn ($supplier) => $supplier->where('code', 'like', $term)->orWhere('name', 'like', $term));
                });
            })
            ->when($this->supplierFilter !== '', fn ($query) => $query->where('supplier_id', $this->supplierFilter))
            ->when($this->fromDate !== '', fn ($query) => $query->whereDate('recognition_date', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn ($query) => $query->whereDate('recognition_date', '<=', $this->toDate))
            ->orderByDesc('recognition_date')->orderByDesc('id');
        $all = $query->get();
        $invoices = $all->filter(fn (SupplierPurchaseInvoice $invoice) => match ($this->status) {
            'Draft' => $invoice->status === 'draft',
            'Overdue' => $invoice->isOverdueOn($today),
            'Paid' => $invoice->payableStatus() === 'Paid',
            'Partially Paid' => $invoice->payableStatus() === 'Partially paid',
            'Unpaid' => $invoice->payableStatus() === 'Unpaid',
            'Outstanding' => $invoice->status === 'posted' && $invoice->outstandingAmountCents() > 0,
            default => true,
        })->values();
        $posted = $all->where('status', 'posted')->whereNull('correction_of_id');
        $selected = $this->selectedId
            ? SupplierPurchaseInvoice::query()->with([
                'lines.inventory', 'lines.account', 'journal.lines.account',
                'paymentAllocations.disbursement.reversalOf', 'paymentAllocations.disbursement.reversals',
                'corrections.journal.lines.account', 'corrections.refundReceipts.journal',
                'correctionChildren.corrections', 'correctionParent.corrections',
            ])->find($this->selectedId)
            : null;
        $selectedCorrections = collect();
        $selectedPaymentAllocations = collect();
        $receiptMovements = collect();
        if ($selected) {
            $chainIds = $selected->correctionChainInvoiceIds();
            $selectedCorrections = SupplierPurchaseCorrection::query()
                ->with(['journal.lines.account', 'refundReceipts.journal', 'replacementInvoice'])
                ->whereIn('supplier_purchase_invoice_id', $chainIds)->orderBy('id')->get();
            $selectedPaymentAllocations = CashDisbursementLine::query()
                ->with(['disbursement.journal', 'disbursement.reversalOf', 'disbursement.reversals'])
                ->whereIn('supplier_purchase_invoice_id', $chainIds)->get();
            $sourceReferences = collect($chainIds)->map(fn ($id) => 'supplier_purchase:'.$id)
                ->concat($selectedCorrections->pluck('id')->map(fn ($id) => 'supplier_purchase_correction:'.$id));
            $receiptMovements = StockMovement::query()->whereIn('source_reference', $sourceReferences)
                ->with('inventory')->orderBy('effective_date')->orderBy('id')->get();
        }
        $cashAccounts = AccountingAccount::query()->where('is_active', true)->whereNotNull('approved_at')
            ->where('type', 'Asset')->whereIn('classification', ['cash', 'bank'])->orderBy('code')->get();

        return view('livewire.accounting.supplier-purchases', [
            'invoices' => $invoices,
            'selectedInvoice' => $selected,
            'selectedCorrections' => $selectedCorrections,
            'selectedPaymentAllocations' => $selectedPaymentAllocations,
            'receiptMovements' => $receiptMovements,
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'accounts' => AccountingAccount::query()->where('is_active', true)->whereNotNull('approved_at')
                ->whereIn('type', ['Asset', 'Expense'])
                ->whereNotIn('classification', ['inventory', 'accounts_payable', 'input_vat', 'output_vat'])
                ->orderBy('code')->get(),
            'inventoryItems' => Inventory::query()->where('status', 'active')->orderBy('name')->get(),
            'today' => $today,
            'purchaseTotalCents' => $posted->sum(fn (SupplierPurchaseInvoice $invoice) => $invoice->activeCorrectedAmountCents()),
            'paidTotalCents' => $posted->sum(fn (SupplierPurchaseInvoice $invoice) => $invoice->paidAmountCents()),
            'outstandingCents' => $posted->sum(fn (SupplierPurchaseInvoice $invoice) => $invoice->outstandingAmountCents()),
            'overdueCents' => $posted->filter(fn (SupplierPurchaseInvoice $invoice) => $invoice->isOverdueOn($today))
                ->sum(fn (SupplierPurchaseInvoice $invoice) => $invoice->outstandingAmountCents()),
            'cashAccounts' => $cashAccounts,
            'canCorrectPurchases' => auth()->user()?->can('accounting.correct-supplier-purchases'),
            'canPostRefunds' => auth()->user()?->can('accounting.post-supplier-refunds'),
        ])->layout('layouts.app', ['title' => 'Supplier Purchases']);
    }

    private function emptyLine(): array
    {
        return ['inventory_id' => '', 'accounting_account_id' => '', 'description' => '', 'quantity' => '', 'amount' => ''];
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'supplierId', 'invoiceNumber', 'description', 'terms', 'receiptConfirmed']);
        $this->recognitionDate = now('Asia/Manila')->toDateString();
        $this->dueDate = $this->recognitionDate;
        $this->lines = [$this->emptyLine()];
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

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
