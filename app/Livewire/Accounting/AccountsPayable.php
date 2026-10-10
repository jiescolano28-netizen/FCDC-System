<?php

namespace App\Livewire\Accounting;

use App\Models\Supplier;
use App\Models\SupplierOpeningInvoice;
use App\Services\Accounting\SupplierPayablesService;
use Livewire\Component;

class AccountsPayable extends Component
{
    public string $search = '';

    public string $status = 'All';

    public function clearFilters(): void
    {
        $this->reset('search');
        $this->status = 'All';
    }

    public ?int $supplierId = null;

    public ?int $supplierEditId = null;

    public ?int $invoiceEditId = null;

    public ?int $selectedInvoiceId = null;

    public string $supplierCode = '';

    public string $supplierName = '';

    public string $supplierLegalName = '';

    public string $supplierTaxIdentifier = '';

    public string $supplierAddress = '';

    public string $supplierContactName = '';

    public string $supplierEmail = '';

    public string $supplierPhone = '';

    public string $invoiceNumber = '';

    public string $recognitionDate = '';

    public string $dueDate = '';

    public string $amount = '';

    public string $description = '';

    public string $terms = '';

    public function saveSupplier(): void
    {
        $this->authorizePermission('accounting.maintain-suppliers');
        $supplier = app(SupplierPayablesService::class)->saveSupplier([
            'id' => $this->supplierEditId,
            'code' => $this->supplierCode,
            'name' => $this->supplierName,
            'legal_name' => $this->supplierLegalName,
            'tax_identifier' => $this->supplierTaxIdentifier,
            'address' => $this->supplierAddress,
            'contact_name' => $this->supplierContactName,
            'email' => $this->supplierEmail,
            'phone' => $this->supplierPhone,
        ], auth()->id());
        $this->supplierId = $supplier->id;
        $this->supplierEditId = null;
        $this->resetSupplierForm();
        session()->flash('payable-message', 'Supplier saved.');
    }

    public function editSupplier(int $supplierId): void
    {
        $this->authorizePermission('accounting.maintain-suppliers');
        $supplier = Supplier::findOrFail($supplierId);
        $this->supplierEditId = $supplier->id;
        $this->supplierId = $supplier->id;
        $this->supplierCode = $supplier->code;
        $this->supplierName = $supplier->name;
        $this->supplierLegalName = $supplier->legal_name ?? '';
        $this->supplierTaxIdentifier = $supplier->tax_identifier ?? '';
        $this->supplierAddress = $supplier->address ?? '';
        $this->supplierContactName = $supplier->contact_name ?? '';
        $this->supplierEmail = $supplier->email ?? '';
        $this->supplierPhone = $supplier->phone ?? '';
    }

    public function saveOpeningInvoice(): void
    {
        $this->authorizePermission('accounting.maintain-opening-books');
        $invoice = app(SupplierPayablesService::class)->saveOpeningInvoice([
            'id' => $this->invoiceEditId,
            'supplier_id' => $this->supplierId,
            'invoice_number' => $this->invoiceNumber,
            'recognition_date' => $this->recognitionDate,
            'due_date' => $this->dueDate,
            'amount' => $this->amount,
            'description' => $this->description,
            'terms' => $this->terms,
        ], auth()->id());
        $this->invoiceEditId = null;
        $this->resetInvoiceForm();
        session()->flash('payable-message', 'Opening invoice saved as a draft; it is not yet part of posted Accounts Payable.');
    }

    public function editOpeningInvoice(int $invoiceId): void
    {
        $this->authorizePermission('accounting.maintain-opening-books');
        $invoice = SupplierOpeningInvoice::where('status', 'draft')->findOrFail($invoiceId);
        $this->invoiceEditId = $invoice->id;
        $this->supplierId = $invoice->supplier_id;
        $this->invoiceNumber = $invoice->invoice_number;
        $this->recognitionDate = $invoice->recognition_date->format('Y-m-d');
        $this->dueDate = $invoice->due_date->format('Y-m-d');
        $this->amount = number_format($invoice->amount_cents / 100, 2, '.', '');
        $this->description = $invoice->description;
        $this->terms = $invoice->terms ?? '';
    }

    public function deleteOpeningInvoice(int $invoiceId): void
    {
        $this->authorizePermission('accounting.maintain-opening-books');
        app(SupplierPayablesService::class)->deleteOpeningInvoice($invoiceId);
        session()->flash('payable-message', 'Opening invoice draft deleted.');
    }

    public function showInvoice(int $invoiceId): void
    {
        $this->selectedInvoiceId = SupplierOpeningInvoice::findOrFail($invoiceId)->id;
    }

    public function render()
    {
        $today = now('Asia/Manila')->toDateString();
        $matchingInvoices = SupplierOpeningInvoice::with(['supplier', 'preparer', 'approver', 'reversalOf', 'reversals'])
            ->where(function ($query): void {
                $query->whereHas('supplier', fn ($supplier) => $supplier->search($this->search))
                    ->orWhere('supplier_name_snapshot', 'like', '%'.trim($this->search).'%')
                    ->orWhere('supplier_code_snapshot', 'like', '%'.trim($this->search).'%')
                    ->orWhere('invoice_number', 'like', '%'.trim($this->search).'%');
            })
            ->orderBy('due_date')->orderBy('id')->get();
        $invoices = $matchingInvoices->filter(fn (SupplierOpeningInvoice $invoice) => match ($this->status) {
            'Draft' => $invoice->status === 'draft',
            'Unpaid' => $invoice->payableStatus() === 'Unpaid' && ! $invoice->isOverdueOn($today),
            'Partially Paid' => $invoice->payableStatus() === 'Partially paid',
            'Paid' => $invoice->payableStatus() === 'Paid',
            'Overdue' => $invoice->isOverdueOn($today),
            default => true,
        })->values();
        $postedInvoices = SupplierOpeningInvoice::activePosted()->with('reversals')->get();
        $selectedInvoice = $this->selectedInvoiceId
            ? SupplierOpeningInvoice::with(['supplier', 'preparer', 'approver', 'reversalOf', 'reversals', 'openingJournal', 'paymentAllocations.disbursement.reversalOf', 'paymentAllocations.disbursement.reversals'])->find($this->selectedInvoiceId)
            : null;

        return view('livewire.accounting.accounts-payable', [
            'suppliers' => Supplier::orderBy('name')->get(),
            'visibleSuppliers' => Supplier::search($this->search)->orderBy('name')->get(),
            'invoices' => $invoices,
            'selectedInvoice' => $selectedInvoice,
            'today' => $today,
            'paidCents' => $postedInvoices->sum(fn (SupplierOpeningInvoice $invoice) => $invoice->paidAmountCents()),
            'outstandingCents' => $postedInvoices->sum(fn (SupplierOpeningInvoice $invoice) => $invoice->outstandingAmountCents()),
            'overdueCents' => $postedInvoices->filter(fn (SupplierOpeningInvoice $invoice) => $invoice->isOverdueOn($today))
                ->sum(fn (SupplierOpeningInvoice $invoice) => $invoice->outstandingAmountCents()),
        ])->layout('layouts.app', ['title' => 'Accounts Payable']);
    }

    private function resetSupplierForm(): void
    {
        $this->reset('supplierCode', 'supplierName', 'supplierLegalName', 'supplierTaxIdentifier', 'supplierAddress', 'supplierContactName', 'supplierEmail', 'supplierPhone');
    }

    private function resetInvoiceForm(): void
    {
        $this->reset('invoiceNumber', 'recognitionDate', 'dueDate', 'amount', 'description', 'terms');
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
