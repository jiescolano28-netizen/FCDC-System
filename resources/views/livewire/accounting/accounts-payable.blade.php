<section class="accounting-page accounts-payable-page" aria-labelledby="accounts-payable-heading">
    <header class="dashboard-heading mb-5">
        <div>
            <h1 id="accounts-payable-heading">Accounts Payable</h1>
            <p class="dashboard-subtitle mb-0 max-w-3xl leading-6">Review supplier invoices and posted payment allocations recorded at accounting cutover.</p>
        </div>
    </header>

    <p class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950">
        This schedule covers opening invoices only. Drafts are not posted, and balances below reflect posted opening invoices and payment allocations.
    </p>

    @if (session()->has('payable-message'))<p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">{{ session('payable-message') }}</p>@endif
    @if ($errors->any())<div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><ul class="m-0 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3" aria-label="Posted opening payable balances">
        <article class="dashboard-card"><p class="dashboard-stat-label">Posted opening paid</p><p class="accounting-unavailable tabular-nums">PHP {{ number_format($paidCents / 100, 2) }}</p><p class="dashboard-stat-note">Net posted payment allocations.</p></article>
        <article class="dashboard-card"><p class="dashboard-stat-label">Posted opening outstanding</p><p class="accounting-unavailable tabular-nums">PHP {{ number_format($outstandingCents / 100, 2) }}</p><p class="dashboard-stat-note">After posted payments and reversals.</p></article>
        <article class="dashboard-card"><p class="dashboard-stat-label">Posted opening overdue</p><p class="accounting-unavailable tabular-nums">PHP {{ number_format($overdueCents / 100, 2) }}</p><p class="dashboard-stat-note">Only overdue invoices with a positive balance.</p></article>
    </section>

    <details class="group mb-4 rounded-xl border border-emerald-100 bg-white shadow-sm">
        <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold text-slate-800 marker:hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
            <span>Supplier directory <span class="ml-2 font-normal text-slate-500">Manage supplier details</span></span>
            <span aria-hidden="true" class="text-emerald-800 transition-transform group-open:rotate-45">+</span>
        </summary>
        <div class="border-t border-slate-100 p-5">
            <p class="mb-4 max-w-3xl text-sm leading-6 text-slate-600">Supplier codes are stable identity. Posted invoices retain the supplier code and name recorded when prepared.</p>
            @can('accounting.maintain-suppliers')
                <form class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" wire:submit="saveSupplier">
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Supplier code<input class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" wire:model="supplierCode" maxlength="30" required></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Supplier name<input class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" wire:model="supplierName" maxlength="180" required></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Legal name<input class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" wire:model="supplierLegalName" maxlength="180"></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Tax identifier<input class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" wire:model="supplierTaxIdentifier" maxlength="80"></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Address<textarea class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" wire:model="supplierAddress" maxlength="4000"></textarea></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Contact name<input class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" wire:model="supplierContactName" maxlength="150"></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Email<input class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" type="email" wire:model="supplierEmail" maxlength="254"></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Phone<input class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" wire:model="supplierPhone" maxlength="50"></label>
                    <button class="min-h-10 self-end rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" type="submit">{{ $supplierEditId ? 'Update supplier' : 'Save supplier' }}</button>
                </form>
            @endcan
            <ul class="divide-y divide-slate-100">
                @forelse ($visibleSuppliers as $supplier)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm" wire:key="supplier-{{ $supplier->id }}"><span><strong class="text-slate-800">{{ $supplier->code }}</strong> — {{ $supplier->name }}@if ($supplier->legal_name) <span class="text-slate-500">({{ $supplier->legal_name }})</span>@endif</span>
                        @can('accounting.maintain-suppliers') <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" type="button" wire:click="editSupplier({{ $supplier->id }})">Edit</button>@endcan
                    </li>
                @empty
                    <li class="py-3 text-sm text-slate-500">No suppliers match this search.</li>
                @endforelse
            </ul>
        </div>
    </details>

    <details class="group mb-5 rounded-xl border border-emerald-100 bg-white shadow-sm">
        <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-semibold text-slate-800 marker:hidden focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
            <span>Prepare opening unpaid invoice <span class="ml-2 font-normal text-slate-500">Record an unpaid invoice at cutover</span></span>
            <span aria-hidden="true" class="text-emerald-800 transition-transform group-open:rotate-45">+</span>
        </summary>
        <div class="border-t border-slate-100 p-5">
            <p class="mb-4 max-w-3xl text-sm leading-6 text-slate-600">Enter invoices outstanding at cutover. Recognition date cannot be after cutover; preparation creates no purchase, expense, receipt, or journal posting.</p>
            @can('accounting.maintain-opening-books')
                <form class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" wire:submit="saveOpeningInvoice">
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Supplier<select class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" wire:model="supplierId" required><option value="">Select supplier</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}</option>@endforeach</select></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Supplier invoice number<input class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" wire:model="invoiceNumber" maxlength="100" required></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Recognition date<input class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" type="date" wire:model="recognitionDate" required></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Due date<input class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" type="date" wire:model="dueDate" required></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Amount (PHP)<input class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" inputmode="decimal" wire:model="amount" required></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Terms<input class="min-h-10 rounded-lg border border-slate-300 px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" wire:model="terms" maxlength="180"></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700 sm:col-span-2">Description<textarea class="min-h-20 rounded-lg border border-slate-300 px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" wire:model="description" maxlength="4000" required></textarea></label>
                    <button class="min-h-10 self-end rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" type="submit">{{ $invoiceEditId ? 'Update opening invoice draft' : 'Save opening invoice draft' }}</button>
                </form>
            @endcan
        </div>
    </details>

    <section class="dashboard-card accounting-activity" aria-labelledby="payable-invoices-heading">
        <header class="dashboard-card-heading mb-4"><div><h2 id="payable-invoices-heading">Supplier invoices</h2><p class="dashboard-chart-note">Opening payable status reflects posted payment allocations and linked reversals. Drafts remain outside the books.</p></div><span class="whitespace-nowrap rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700" aria-live="polite">{{ $invoices->count() }} {{ \Illuminate\Support\Str::plural('invoice', $invoices->count()) }}</span></header>
        <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_14rem]" aria-label="Filter supplier invoices">
            <label class="grid gap-1.5 text-sm font-medium text-slate-700"><span>Search supplier or invoice</span><input class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" type="search" wire:model.live.debounce.250ms="search" placeholder="Search supplier or invoice number"></label>
            <label class="grid gap-1.5 text-sm font-medium text-slate-700"><span>Invoice state</span><select class="min-h-10 rounded-lg border border-slate-300 bg-white px-3 py-2 font-normal focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700" wire:model.live="status"><option value="All">All states</option><option value="Draft">Draft</option><option value="Unpaid">Unpaid</option><option value="Partially Paid">Partially paid</option><option value="Paid">Paid</option><option value="Overdue">Overdue</option></select></label>
        </div>
        @if ($search !== '' || $status !== 'All')
            <div class="mb-3 flex justify-end">
                <button class="rounded-md px-3 py-2 text-sm font-semibold text-emerald-800 underline underline-offset-2 hover:text-emerald-950 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" type="button" wire:click="clearFilters">Clear filters</button>
            </div>
        @endif
        <div class="reports-table-wrap rounded-lg border border-slate-100">
            <table class="reports-table min-w-[1040px]">
                <thead><tr><th scope="col">Supplier</th><th scope="col">Invoice</th><th scope="col">Recognition date</th><th scope="col">Due date</th><th scope="col" class="numeric">Amount (PHP)</th><th scope="col" class="numeric">Paid (PHP)</th><th scope="col" class="numeric">Outstanding (PHP)</th><th scope="col">State</th><th scope="col">Actions</th></tr></thead>

                <tbody>
                    @forelse ($invoices as $invoice)
                        @php($invoiceState = $invoice->status === 'draft' ? 'Draft' : ($invoice->isOverdueOn($today) ? 'Overdue' : $invoice->payableStatus()))
                        <tr wire:key="payable-invoice-{{ $invoice->id }}">
                            <td class="max-w-56 whitespace-normal"><strong class="text-slate-800">{{ $invoice->supplier_name_snapshot }}</strong><small class="mt-1 block text-slate-500">{{ $invoice->supplier_code_snapshot }}</small></td>
                            <td class="font-medium text-slate-800">{{ $invoice->invoice_number }}</td><td>{{ $invoice->recognition_date->format('Y-m-d') }}</td><td>{{ $invoice->due_date->format('Y-m-d') }}</td>
                            <td class="numeric tabular-nums">{{ number_format($invoice->amount_cents / 100, 2) }}</td>
                            <td class="numeric tabular-nums">{{ number_format($invoice->paidAmountCents() / 100, 2) }}</td>
                            <td class="numeric tabular-nums font-semibold text-slate-800">{{ number_format($invoice->outstandingAmountCents() / 100, 2) }}</td>
                            <td><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $invoiceState === 'Overdue' ? 'bg-red-100 text-red-800' : ($invoiceState === 'Paid' ? 'bg-emerald-100 text-emerald-900' : ($invoiceState === 'Draft' ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-900')) }}">Opening · {{ $invoiceState }}</span></td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    <button class="rounded-md border border-emerald-800 px-3 py-1.5 text-xs font-semibold text-emerald-900 hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" type="button" wire:click="showInvoice({{ $invoice->id }})">View / print</button>
                                    @if ($invoice->status === 'draft')
                                        @can('accounting.maintain-opening-books')<button class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700" type="button" wire:click="editOpeningInvoice({{ $invoice->id }})">Edit draft</button><button class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-800 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700" type="button" wire:click="deleteOpeningInvoice({{ $invoice->id }})">Delete draft</button>@endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="reports-empty payable-empty whitespace-normal py-12 text-center text-slate-600" colspan="9">No supplier invoices match this search or state.</td></tr>
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
        <section id="supplier-invoice-print" class="mt-5 rounded-xl border border-emerald-100 bg-white p-5 shadow-sm" aria-labelledby="invoice-detail-heading">
            <h2 id="invoice-detail-heading">Opening invoice {{ $selectedInvoice->invoice_number }} — {{ $selectedInvoice->stateAsOf($today) }}</h2>
            <p>{{ $selectedInvoice->supplier_name_snapshot }} ({{ $selectedInvoice->supplier_code_snapshot }})</p>
            @if ($selectedInvoice->supplier_legal_name_snapshot)<p>Legal name: {{ $selectedInvoice->supplier_legal_name_snapshot }}</p>@endif
            @if ($selectedInvoice->supplier_tax_identifier_snapshot)<p>Tax identifier: {{ $selectedInvoice->supplier_tax_identifier_snapshot }}</p>@endif
            @if ($selectedInvoice->supplier_address_snapshot)<p>Supplier address at preparation: {{ $selectedInvoice->supplier_address_snapshot }}</p>@endif
            <p>{{ $selectedInvoice->description }}</p>
            <dl><dt>Recognition date</dt><dd>{{ $selectedInvoice->recognition_date->format('Y-m-d') }}</dd><dt>Due date</dt><dd>{{ $selectedInvoice->due_date->format('Y-m-d') }}</dd><dt>Amount</dt><dd>PHP {{ number_format($selectedInvoice->amount_cents / 100, 2) }}</dd><dt>Paid</dt><dd>PHP {{ number_format($selectedInvoice->paidAmountCents() / 100, 2) }}</dd><dt>Outstanding</dt><dd>PHP {{ number_format($selectedInvoice->outstandingAmountCents() / 100, 2) }}</dd><dt>Terms</dt><dd>{{ $selectedInvoice->terms ?: 'Not recorded' }}</dd><dt>Prepared by</dt><dd>{{ $selectedInvoice->preparer?->username ?? 'Unavailable' }}</dd><dt>Approved by</dt><dd>{{ $selectedInvoice->approver?->username ?? 'Unavailable' }} · {{ $selectedInvoice->approved_at?->timezone('UTC')->format('Y-m-d H:i:s') }} UTC</dd><dt>Opening journal</dt><dd>{{ $selectedInvoice->openingJournal?->reference ?? $selectedInvoice->opening_journal_id }}</dd></dl>
            @if ($selectedInvoice->reversal_of_id)<p>Linked historical reversal of invoice #{{ $selectedInvoice->reversal_of_id }}.</p>@endif
            @if ($selectedInvoice->reversals->isNotEmpty())
                <p>Linked historical reversals:</p>
                <ul>@foreach ($selectedInvoice->reversals as $reversal)<li>Invoice {{ $reversal->invoice_number }} · {{ ucfirst($reversal->status) }}</li>@endforeach</ul>
            @endif
            <h3>Payment allocation and correction history</h3>
            <ul>
                @forelse ($selectedInvoice->paymentAllocations->filter(fn ($allocation) => $allocation->disbursement?->status === 'posted') as $allocation)
                    <li>
                        {{ $allocation->disbursement->reference }} · PHP {{ number_format($allocation->amount_cents / 100, 2) }}
                        · {{ $allocation->disbursement->evidence_reference }} · posted by #{{ $allocation->disbursement->posted_by }}
                        @if ($allocation->disbursement->reversal_of_id)
                            · reversal of {{ $allocation->disbursement->reversalOf?->reference }}: {{ $allocation->disbursement->correction_reason }}
                        @elseif ($allocation->disbursement->reversals->isNotEmpty())
                            · reversed by {{ $allocation->disbursement->reversals->first()->reference }}: {{ $allocation->disbursement->reversals->first()->correction_reason }}
                        @endif
                    </li>
                @empty
                    <li>No posted payment allocations.</li>
                @endforelse
            </ul>
            <button type="button" onclick="window.print()">Print invoice</button>
        </section>
    @endif
</section>
