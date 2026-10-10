<section class="accounting-page accounts-payable-page" aria-labelledby="accounts-payable-heading">
    <header class="dashboard-heading">
        <div>
            <h1 id="accounts-payable-heading">Accounts Payable</h1>
            <p class="dashboard-subtitle">Opening supplier invoices are controlled by the approved cutover journal; drafts do not affect the books.</p>
        </div>
    </header>

    @if (session()->has('payable-message'))<p role="status">{{ session('payable-message') }}</p>@endif
    @if ($errors->any())<div role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="accounting-balance-grid" aria-label="Posted opening payable balances">
        <article class="dashboard-card"><p class="dashboard-stat-label">Posted opening outstanding</p><p class="accounting-unavailable">PHP {{ number_format($outstandingCents / 100, 2) }}</p><p class="dashboard-stat-note">Opening invoices only; payment allocation is not part of this workflow.</p></article>
        <article class="dashboard-card"><p class="dashboard-stat-label">Posted opening overdue</p><p class="accounting-unavailable">PHP {{ number_format($overdueCents / 100, 2) }}</p><p class="dashboard-stat-note">Due date is authoritative; overdue requires a positive outstanding opening invoice.</p></article>
    </section>

    <section class="chart-account-card" aria-labelledby="supplier-maintenance-heading">
        <h2 id="supplier-maintenance-heading">Suppliers</h2>
        <p>Supplier codes are stable identity. Posted invoices retain the supplier code and name recorded when prepared.</p>
        @can('accounting.maintain-suppliers')
            <form wire:submit="saveSupplier">
                <label>Supplier code<input wire:model="supplierCode" maxlength="30" required></label>
                <label>Supplier name<input wire:model="supplierName" maxlength="180" required></label>
                <label>Legal name<input wire:model="supplierLegalName" maxlength="180"></label>
                <label>Tax identifier<input wire:model="supplierTaxIdentifier" maxlength="80"></label>
                <label>Address<textarea wire:model="supplierAddress" maxlength="4000"></textarea></label>
                <label>Contact name<input wire:model="supplierContactName" maxlength="150"></label>
                <label>Email<input type="email" wire:model="supplierEmail" maxlength="254"></label>
                <label>Phone<input wire:model="supplierPhone" maxlength="50"></label>
                <button type="submit">{{ $supplierEditId ? 'Update supplier' : 'Save supplier' }}</button>
            </form>
        @endcan
        <ul>
            @forelse ($visibleSuppliers as $supplier)
                <li wire:key="supplier-{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}@if ($supplier->legal_name) ({{ $supplier->legal_name }})@endif
                    @can('accounting.maintain-suppliers') <button type="button" wire:click="editSupplier({{ $supplier->id }})">Edit</button>@endcan
                </li>
            @empty
                <li>No suppliers match this search.</li>
            @endforelse
        </ul>
    </section>

    <section class="chart-account-card" aria-labelledby="opening-invoice-heading">
        <h2 id="opening-invoice-heading">Prepare opening unpaid invoice</h2>
        <p>Enter invoices outstanding at cutover. Recognition date cannot be after cutover; preparation creates no purchase, expense, receipt, or journal posting.</p>
        @can('accounting.maintain-opening-books')
            <form wire:submit="saveOpeningInvoice">
                <label>Supplier<select wire:model="supplierId" required><option value="">Select supplier</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}</option>@endforeach</select></label>
                <label>Supplier invoice number<input wire:model="invoiceNumber" maxlength="100" required></label>
                <label>Recognition date<input type="date" wire:model="recognitionDate" required></label>
                <label>Due date<input type="date" wire:model="dueDate" required></label>
                <label>Amount (PHP)<input inputmode="decimal" wire:model="amount" required></label>
                <label>Description<textarea wire:model="description" maxlength="4000" required></textarea></label>
                <label>Terms<input wire:model="terms" maxlength="180"></label>
                <button type="submit">{{ $invoiceEditId ? 'Update opening invoice draft' : 'Save opening invoice draft' }}</button>
            </form>
        @endcan
    </section>

    <section class="dashboard-card accounting-activity" aria-labelledby="payable-invoices-heading">
        <header class="dashboard-card-heading"><div><h2 id="payable-invoices-heading">Supplier invoices</h2><p class="dashboard-chart-note">Outstanding and overdue totals include posted opening invoices only. Drafts remain outside the books.</p></div></header>
        <div class="payable-filters" aria-label="Filter supplier invoices">
            <label><span>Search supplier or invoice</span><input type="search" wire:model.live.debounce.250ms="search" placeholder="Search supplier or invoice number"></label>
            <label><span>Invoice state</span><select wire:model.live="status"><option value="All">All states</option><option value="Draft">Draft</option><option value="Unpaid">Unpaid</option><option value="Overdue">Overdue</option></select></label>
        </div>
        <div class="reports-table-wrap">
            <table class="reports-table">
                <thead><tr><th scope="col">Supplier</th><th scope="col">Invoice</th><th scope="col">Recognition date</th><th scope="col">Due date</th><th scope="col" class="numeric">Amount</th><th scope="col">State</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr wire:key="payable-invoice-{{ $invoice->id }}">
                            <td>{{ $invoice->supplier_name_snapshot }} <small>({{ $invoice->supplier_code_snapshot }})</small></td>
                            <td>{{ $invoice->invoice_number }}</td><td>{{ $invoice->recognition_date->format('Y-m-d') }}</td><td>{{ $invoice->due_date->format('Y-m-d') }}</td>
                            <td class="numeric">{{ number_format($invoice->amount_cents / 100, 2) }}</td>
                            <td>{{ $invoice->status === 'draft' ? 'Opening · Draft' : 'Opening · Posted' }}@if ($invoice->status === 'posted' && $invoice->due_date->toDateString() < now('Asia/Manila')->toDateString()) · Overdue @endif</td>
                            <td>
                                @if ($invoice->status === 'posted')<button type="button" wire:click="showInvoice({{ $invoice->id }})">Invoice detail / print</button>@else
                                    @can('accounting.maintain-opening-books')<button type="button" wire:click="editOpeningInvoice({{ $invoice->id }})">Edit draft</button><button type="button" wire:click="deleteOpeningInvoice({{ $invoice->id }})">Delete draft</button>@endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="reports-empty payable-empty" colspan="7">No supplier invoices match this search or state.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($selectedInvoice)
        <style>
            @media print {
                body * { visibility: hidden !important; }
                #supplier-invoice-print, #supplier-invoice-print * { visibility: visible !important; }
                #supplier-invoice-print { position: absolute; inset: 0; width: 100%; }
                #supplier-invoice-print button { display: none !important; }
            }
        </style>
        <section id="supplier-invoice-print" class="chart-account-card" aria-labelledby="invoice-detail-heading">
            <h2 id="invoice-detail-heading">Opening invoice {{ $selectedInvoice->invoice_number }} — Posted</h2>
            <p>{{ $selectedInvoice->supplier_name_snapshot }} ({{ $selectedInvoice->supplier_code_snapshot }})</p>
            @if ($selectedInvoice->supplier_legal_name_snapshot)<p>Legal name: {{ $selectedInvoice->supplier_legal_name_snapshot }}</p>@endif
            @if ($selectedInvoice->supplier_tax_identifier_snapshot)<p>Tax identifier: {{ $selectedInvoice->supplier_tax_identifier_snapshot }}</p>@endif
            @if ($selectedInvoice->supplier_address_snapshot)<p>Supplier address at preparation: {{ $selectedInvoice->supplier_address_snapshot }}</p>@endif
            <p>{{ $selectedInvoice->description }}</p>
            <dl><dt>Recognition date</dt><dd>{{ $selectedInvoice->recognition_date->format('Y-m-d') }}</dd><dt>Due date</dt><dd>{{ $selectedInvoice->due_date->format('Y-m-d') }}</dd><dt>Amount</dt><dd>PHP {{ number_format($selectedInvoice->amount_cents / 100, 2) }}</dd><dt>Terms</dt><dd>{{ $selectedInvoice->terms ?: 'Not recorded' }}</dd><dt>Prepared by</dt><dd>{{ $selectedInvoice->preparer?->username ?? 'Unavailable' }}</dd><dt>Approved by</dt><dd>{{ $selectedInvoice->approver?->username ?? 'Unavailable' }} · {{ $selectedInvoice->approved_at?->timezone('UTC')->format('Y-m-d H:i:s') }} UTC</dd><dt>Opening journal</dt><dd>{{ $selectedInvoice->openingJournal?->reference ?? $selectedInvoice->opening_journal_id }}</dd></dl>
            @if ($selectedInvoice->reversal_of_id)<p>Linked historical reversal of invoice #{{ $selectedInvoice->reversal_of_id }}.</p>@endif
            @if ($selectedInvoice->reversals->isNotEmpty())
                <p>Linked historical reversals:</p>
                <ul>@foreach ($selectedInvoice->reversals as $reversal)<li>Invoice {{ $reversal->invoice_number }} · {{ ucfirst($reversal->status) }}</li>@endforeach</ul>
            @endif
            <button type="button" onclick="window.print()">Print invoice</button>
        </section>
    @endif
</section>
