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

    public function mount(): void
    {
        $this->paymentDate = now('Asia/Manila')->toDateString();
        $this->allocations = [$this->emptyAllocation()];
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
        $otherMethods = AccountingPostingMapping::query()->where('source', 'like', 'disbursement_method:%')
            ->with('account')->get()->filter(fn ($mapping) => $mapping->isApprovedForPosting())
            ->map(fn ($mapping) => substr($mapping->source, strlen('disbursement_method:')))->values();

        $suppliers = Supplier::query()->orderBy('name')->get();
        $eligibleInvoices = SupplierPurchaseInvoice::query()->where('status', 'posted')
            ->when($this->supplierId !== '', fn ($query) => $query->where('supplier_id', $this->supplierId))
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

        return view('livewire.accounting.cash-disbursements', [
            'disbursements' => $disbursements,
            'selectedDisbursement' => $selected,
            'suppliers' => $suppliers,
            'eligibleInvoices' => $eligibleInvoices,
            'moneyAccounts' => AccountingAccount::query()->where('is_active', true)->whereNotNull('approved_at')
                ->where('type', 'Asset')->whereIn('classification', ['cash', 'bank'])->orderBy('code')->get(),
            'debitAccounts' => AccountingAccount::query()->where('is_active', true)->whereNotNull('approved_at')
                ->whereIn('type', ['Asset', 'Expense'])
                ->whereNotIn('classification', ['cash', 'bank', 'accounts_receivable', 'card_clearing', 'accounts_payable', 'inventory', 'input_vat', 'output_vat', 'cost_of_goods_sold'])
                ->orderBy('code')->get(),
            'inventoryItems' => $inventoryItems,
            'otherMethods' => $otherMethods,
            'postedTotalCents' => $disbursements->where('status', 'posted')->whereNull('reversal_of_id')->sum('amount_cents'),
        ])->layout('layouts.app', ['title' => 'Cash Disbursements']);
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
