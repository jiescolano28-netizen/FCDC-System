<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingAccount;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPurchaseInvoice;
use App\Services\Accounting\SupplierPurchaseService;
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

    public function showInvoice(int $invoiceId): void
    {
        $this->authorizePermission('accounting.view');
        $this->selectedId = SupplierPurchaseInvoice::query()->findOrFail($invoiceId)->id;
    }

    public function render()
    {
        $this->authorizePermission('accounting.view');
        $today = now('Asia/Manila')->toDateString();
        $query = SupplierPurchaseInvoice::query()->with(['lines.inventory', 'lines.account', 'journal'])
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
        $posted = $all->where('status', 'posted');
        $selected = $this->selectedId
            ? SupplierPurchaseInvoice::query()->with(['lines.inventory', 'lines.account', 'journal.lines.account', 'paymentAllocations.disbursement.reversalOf', 'paymentAllocations.disbursement.reversals'])->find($this->selectedId)
            : null;
        $receiptMovements = $selected
            ? StockMovement::query()->where('source_reference', 'supplier_purchase:'.$selected->id)
                ->with('inventory')->orderBy('effective_date')->orderBy('id')->get()
            : collect();

        return view('livewire.accounting.supplier-purchases', [
            'invoices' => $invoices,
            'selectedInvoice' => $selected,
            'receiptMovements' => $receiptMovements,
            'suppliers' => Supplier::query()->orderBy('name')->get(),
            'accounts' => AccountingAccount::query()->where('is_active', true)->whereNotNull('approved_at')
                ->whereIn('type', ['Asset', 'Expense'])
                ->whereNotIn('classification', ['inventory', 'accounts_payable', 'input_vat', 'output_vat'])
                ->orderBy('code')->get(),
            'inventoryItems' => Inventory::query()->where('status', 'active')->orderBy('name')->get(),
            'today' => $today,
            'purchaseTotalCents' => $posted->sum('gross_amount_cents'),
            'paidTotalCents' => $posted->sum(fn (SupplierPurchaseInvoice $invoice) => $invoice->paidAmountCents()),
            'outstandingCents' => $posted->sum(fn (SupplierPurchaseInvoice $invoice) => $invoice->outstandingAmountCents()),
            'overdueCents' => $posted->filter(fn (SupplierPurchaseInvoice $invoice) => $invoice->isOverdueOn($today))
                ->sum(fn (SupplierPurchaseInvoice $invoice) => $invoice->outstandingAmountCents()),
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

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
