@php
    $field = 'min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-500 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20';
    $label = 'mb-1.5 block text-sm font-medium text-slate-700';
    $primary = 'inline-flex min-h-10 items-center justify-center rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 disabled:cursor-wait disabled:opacity-60';
    $secondary = 'inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700';
    $actionPrimary = 'inline-flex min-h-9 items-center justify-center rounded-lg bg-emerald-800 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700';
    $actionSecondary = 'inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700';
    $actionDanger = 'inline-flex min-h-9 items-center justify-center rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-red-700';
    $table = 'w-full border-collapse text-left text-sm';
    $thead = 'bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600';
    $th = 'px-4 py-3';
    $td = 'px-4 py-3 align-top';
@endphp

<section class="mx-auto max-w-[1440px] space-y-6" aria-labelledby="supplier-purchases-heading">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 id="supplier-purchases-heading" class="text-2xl font-semibold tracking-tight text-slate-900">Supplier Credit Purchases</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">Prepare invoices, then post confirmed receipts as one payable, stock valuation, and journal event.</p>
        </div>
    </header>

    @if (session()->has('purchase-message'))
        <p role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ session('purchase-message') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Posted supplier purchase totals">
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Posted purchases</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900">PHP {{ number_format($purchaseTotalCents / 100, 2) }}</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Paid</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900">PHP {{ number_format($paidTotalCents / 100, 2) }}</p>
        </article>
        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Outstanding</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900">PHP {{ number_format($outstandingCents / 100, 2) }}</p>
        </article>
        <article class="rounded-xl border border-red-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Overdue outstanding</p>
            <p class="mt-2 text-2xl font-semibold tabular-nums text-red-700">PHP {{ number_format($overdueCents / 100, 2) }}</p>
            <p class="mt-1 text-xs leading-5 text-slate-500">Posted invoice balances net of posted payments and linked reversals.</p>
        </article>
    </section>

    @can('accounting.prepare-supplier-purchases')
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="purchase-form-heading">
            <div class="mb-5 flex flex-col gap-1 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 id="purchase-form-heading" class="text-base font-semibold text-slate-900">{{ $editingId ? 'Edit supplier invoice draft' : 'Prepare supplier invoice' }}</h2>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">Saving or editing a draft creates no payable, valued receipt, or journal. Future-dated invoices may remain drafts.</p>
                </div>
                <span @class([
                    'inline-flex items-center self-start rounded-full px-2.5 py-1 text-xs font-medium',
                    'bg-amber-50 text-amber-900' => $editingId,
                    'bg-slate-100 text-slate-600' => ! $editingId,
                ])>{{ $editingId ? 'Editing draft' : 'New draft' }}</span>
            </div>

            <form wire:submit="saveDraft" class="space-y-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label for="purchase-supplier" class="{{ $label }}">Supplier <span class="text-red-700">*</span></label>
                        <select id="purchase-supplier" wire:model="supplierId" required class="{{ $field }}">
                            <option value="">Select supplier</option>
                            @foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label for="purchase-invoice-number" class="{{ $label }}">Supplier invoice number <span class="text-red-700">*</span></label>
                        <input id="purchase-invoice-number" wire:model="invoiceNumber" maxlength="100" required autocomplete="off" class="{{ $field }}">
                    </div>
                    <div class="flex items-end">
                        <label for="purchase-receipt-confirmed" class="flex min-h-10 w-full cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
                            <input id="purchase-receipt-confirmed" type="checkbox" wire:model="receiptConfirmed" class="size-4 rounded border-slate-300 text-emerald-800 focus:ring-emerald-700">
                            Physical receipt confirmed
                        </label>
                    </div>
                    <div>
                        <label for="purchase-recognition-date" class="{{ $label }}">Recognition date <span class="text-red-700">*</span></label>
                        <input id="purchase-recognition-date" type="date" wire:model="recognitionDate" required class="{{ $field }}">
                    </div>
                    <div>
                        <label for="purchase-due-date" class="{{ $label }}">Due date <span class="text-red-700">*</span></label>
                        <input id="purchase-due-date" type="date" wire:model="dueDate" required class="{{ $field }}">
                    </div>
                    <div>
                        <label for="purchase-terms" class="{{ $label }}">Terms <span class="font-normal text-slate-500">Optional</span></label>
                        <input id="purchase-terms" wire:model="terms" maxlength="180" class="{{ $field }}">
                    </div>
                    <div class="sm:col-span-2 xl:col-span-4">
                        <label for="purchase-description" class="{{ $label }}">Description <span class="text-red-700">*</span></label>
                        <textarea id="purchase-description" rows="2" wire:model="description" maxlength="4000" required class="{{ $field }}"></textarea>
                    </div>
                </div>

                <div>
                    <div class="mb-3">
                        <h3 class="text-sm font-semibold text-slate-700">Invoice allocations</h3>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Add one allocation per inventory receipt or non-inventory asset/expense line.</p>
                    </div>
                    <div class="space-y-3">
                        @foreach ($lines as $index => $line)
                            <fieldset wire:key="purchase-line-{{ $index }}" class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                                <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Allocation {{ $index + 1 }}</legend>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    <div>
                                        <label for="purchase-line-{{ $index }}-inventory" class="{{ $label }}">Inventory item</label>
                                        <select id="purchase-line-{{ $index }}-inventory" wire:model="lines.{{ $index }}.inventory_id" class="{{ $field }}">
                                            <option value="">Not inventory</option>
                                            @foreach ($inventoryItems as $item)<option value="{{ $item->id }}">{{ $item->code }} — {{ $item->name }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="purchase-line-{{ $index }}-account" class="{{ $label }}">Non-inventory asset/expense account</label>
                                        <select id="purchase-line-{{ $index }}-account" wire:model="lines.{{ $index }}.accounting_account_id" class="{{ $field }}">
                                            <option value="">Choose when not inventory</option>
                                            @foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="purchase-line-{{ $index }}-description" class="{{ $label }}">Line description <span class="text-red-700">*</span></label>
                                        <input id="purchase-line-{{ $index }}-description" wire:model="lines.{{ $index }}.description" maxlength="255" required class="{{ $field }}">
                                    </div>
                                    <div>
                                        <label for="purchase-line-{{ $index }}-quantity" class="{{ $label }}">Receipt quantity <span class="font-normal text-slate-500">Inventory only</span></label>
                                        <input id="purchase-line-{{ $index }}-quantity" inputmode="decimal" wire:model="lines.{{ $index }}.quantity" class="{{ $field }}">
                                    </div>
                                    <div>
                                        <label for="purchase-line-{{ $index }}-amount" class="{{ $label }}">Gross allocation (PHP) <span class="text-red-700">*</span></label>
                                        <input id="purchase-line-{{ $index }}-amount" inputmode="decimal" wire:model="lines.{{ $index }}.amount" required class="{{ $field }}">
                                    </div>
                                </div>
                                @if (count($lines) > 1)
                                    <div class="mt-3 flex justify-end">
                                        <button type="button" wire:click="removeLine({{ $index }})" class="{{ $actionDanger }}">Remove allocation</button>
                                    </div>
                                @endif
                            </fieldset>
                        @endforeach
                    </div>
                    <button type="button" wire:click="addLine" class="{{ $secondary }} mt-3">Add allocation</button>
                </div>

                <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveDraft" class="{{ $primary }}">
                        <span wire:loading.remove wire:target="saveDraft">{{ $editingId ? 'Save draft changes' : 'Save invoice draft' }}</span>
                        <span wire:loading wire:target="saveDraft">Saving…</span>
                    </button>
                </div>
            </form>
        </section>
    @endcan

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="purchase-register-heading">
        <header class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="purchase-register-heading" class="text-base font-semibold text-slate-900">Supplier invoice register</h2>
                <p class="mt-1 text-sm text-slate-600">Outstanding and overdue are derived from posted purchase and payment records.</p>
            </div>
            <span class="text-sm tabular-nums text-slate-600" aria-live="polite">Showing {{ $invoices->count() }} {{ \Illuminate\Support\Str::plural('invoice', $invoices->count()) }}</span>
        </header>
        <div class="grid grid-cols-1 gap-4 border-b border-slate-100 px-5 py-4 md:grid-cols-2 xl:grid-cols-5">
            <div>
                <label for="purchase-filter-search" class="{{ $label }}">Search</label>
                <input id="purchase-filter-search" type="search" wire:model.live.debounce.250ms="search" placeholder="Supplier, invoice, or description" class="{{ $field }}">
            </div>
            <div>
                <label for="purchase-filter-supplier" class="{{ $label }}">Supplier</label>
                <select id="purchase-filter-supplier" wire:model.live="supplierFilter" class="{{ $field }}">
                    <option value="">All suppliers</option>
                    @foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="purchase-filter-from" class="{{ $label }}">From recognition date</label>
                <input id="purchase-filter-from" type="date" wire:model.live="fromDate" class="{{ $field }}">
            </div>
            <div>
                <label for="purchase-filter-to" class="{{ $label }}">To recognition date</label>
                <input id="purchase-filter-to" type="date" wire:model.live="toDate" class="{{ $field }}">
            </div>
            <div>
                <label for="purchase-filter-status" class="{{ $label }}">Status</label>
                <select id="purchase-filter-status" wire:model.live="status" class="{{ $field }}">
                    <option>All</option><option>Draft</option><option>Unpaid</option><option>Partially Paid</option><option>Paid</option><option>Overdue</option>
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="{{ $table }} min-w-[1100px]">
                <thead class="{{ $thead }}">
                    <tr>
                        <th scope="col" class="{{ $th }}">Supplier</th>
                        <th scope="col" class="{{ $th }}">Invoice</th>
                        <th scope="col" class="{{ $th }}">Recognition</th>
                        <th scope="col" class="{{ $th }}">Due</th>
                        <th scope="col" class="{{ $th }} text-right">Gross</th>
                        <th scope="col" class="{{ $th }} text-right">Paid</th>
                        <th scope="col" class="{{ $th }} text-right">Outstanding</th>
                        <th scope="col" class="{{ $th }}">Status</th>
                        <th scope="col" class="{{ $th }}">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($invoices as $invoice)
                        @php
                            $activeIdentity = $invoice->isActiveCorrectionIdentity();
                            $displayAmountCents = $activeIdentity ? $invoice->activeCorrectedAmountCents() : $invoice->gross_amount_cents;
                            $displayPaidCents = $activeIdentity ? max(0, $invoice->paidAmountCents() - $invoice->refundReceivedAmountCents()) : 0;
                            $displayOutstandingCents = $activeIdentity ? $invoice->outstandingAmountCents() : 0;
                            $overdue = $activeIdentity && $invoice->isOverdueOn($today);
                            $statusText = $activeIdentity ? $invoice->payableStatus() : 'Corrected';
                            $statusLabel = $overdue ? 'Overdue · '.$statusText : $statusText;
                            $statusClass = match (true) {
                                ! $activeIdentity => 'bg-slate-100 text-slate-500',
                                $overdue => 'bg-red-50 text-red-700',
                                $statusText === 'Paid' => 'bg-emerald-50 text-emerald-800',
                                $statusText === 'Partially paid' => 'bg-amber-50 text-amber-900',
                                $statusText === 'Draft' => 'bg-amber-50 text-amber-900',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <tr wire:key="purchase-invoice-{{ $invoice->id }}" class="transition-colors hover:bg-slate-50/70">
                            <td class="{{ $td }} font-medium text-slate-800">{{ $invoice->supplier_name_snapshot }}</td>
                            <td class="{{ $td }} text-slate-700">
                                {{ $invoice->invoice_number }}
                                @if ($invoice->correction_of_id)
                                    <span class="mt-1 block text-xs text-slate-500">Corrected identity for {{ $invoice->correctionParent?->invoice_number }}</span>
                                @elseif ($invoice->correctionChildren->isNotEmpty())
                                    <span class="mt-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Corrected</span>
                                @endif
                            </td>
                            <td class="{{ $td }} whitespace-nowrap text-slate-600">{{ $invoice->recognition_date->format('Y-m-d') }}</td>
                            <td class="{{ $td }} whitespace-nowrap text-slate-600">{{ $invoice->due_date->format('Y-m-d') }}</td>
                            <td class="{{ $td }} text-right tabular-nums text-slate-700">{{ number_format($displayAmountCents / 100, 2) }}</td>
                            <td class="{{ $td }} text-right tabular-nums text-slate-700">{{ number_format($displayPaidCents / 100, 2) }}</td>
                            <td class="{{ $td }} text-right tabular-nums {{ $displayOutstandingCents > 0 ? 'font-semibold text-slate-900' : 'text-slate-500' }}">{{ number_format($displayOutstandingCents / 100, 2) }}</td>
                            <td class="{{ $td }}"><span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td class="{{ $td }}">
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" wire:click="showInvoice({{ $invoice->id }})" class="{{ $actionSecondary }}">Detail / print</button>
                                    @if ($invoice->status === 'draft')
                                        @can('accounting.prepare-supplier-purchases')
                                            <button type="button" wire:click="editDraft({{ $invoice->id }})" class="{{ $actionSecondary }}">Edit</button>
                                            <button type="button" wire:click="deleteDraft({{ $invoice->id }})" wire:confirm="Delete this supplier purchase draft?" class="{{ $actionDanger }}">Delete</button>
                                        @endcan
                                        @can('accounting.post-supplier-purchases')
                                            <button type="button" wire:click="postInvoice({{ $invoice->id }})" class="{{ $actionPrimary }}">Post received invoice</button>
                                        @endcan
                                    @endif
                                    @if ($invoice->status === 'posted' && $activeIdentity && $canCorrectPurchases)
                                        <button type="button" wire:click="startCorrection({{ $invoice->id }})" class="{{ $actionSecondary }}">Correct purchase</button>
                                        <button type="button" wire:click="startVatReclassification({{ $invoice->id }})" class="{{ $actionSecondary }}">Reclass allowable purchase VAT</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-6 py-12 text-center">
                            <p class="text-sm font-semibold text-slate-800">No supplier invoices found</p>
                            <p class="mt-1 text-sm text-slate-600">Try changing the search or filters to see more invoices.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($selectedInvoice)
        <style>@media print { body * { visibility: hidden !important; } #supplier-purchase-print, #supplier-purchase-print * { visibility: visible !important; } #supplier-purchase-print { position: absolute; inset: 0; width: 100%; } #supplier-purchase-print button { display: none !important; } }</style>
        <section id="supplier-purchase-print" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="purchase-detail-heading">
            <header class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 id="purchase-detail-heading" class="text-base font-semibold text-slate-900">Supplier invoice {{ $selectedInvoice->invoice_number }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $selectedInvoice->supplier_name_snapshot }} ({{ $selectedInvoice->supplier_code_snapshot }})</p>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">{{ $selectedInvoice->description }}</p>
                </div>
                <span class="inline-flex items-center self-start rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ ucfirst($selectedInvoice->status) }}</span>
            </header>

            @php
                $facts = [
                    'Recognition date' => $selectedInvoice->recognition_date->format('Y-m-d'),
                    'Due date' => $selectedInvoice->due_date->format('Y-m-d'),
                    'Terms' => $selectedInvoice->terms ?: 'Not recorded',
                    'Receipt confirmed' => $selectedInvoice->receipt_confirmed ? 'Yes' : 'No',
                    'Original gross amount' => 'PHP '.number_format($selectedInvoice->gross_amount_cents / 100, 2),
                    'Corrected active value' => 'PHP '.number_format($selectedInvoice->activeCorrectedAmountCents() / 100, 2),
                    'Paid' => 'PHP '.number_format($selectedInvoice->paidAmountCents() / 100, 2),
                    'Outstanding AP' => 'PHP '.number_format($selectedInvoice->outstandingAmountCents() / 100, 2),
                    'Supplier refund receivable outstanding' => 'PHP '.number_format($selectedInvoice->supplierRefundDueCents() / 100, 2),
                    'Payable status' => ($selectedInvoice->isOverdueOn($today) ? 'Overdue · ' : '').$selectedInvoice->payableStatus(),
                    'Accounting journal' => $selectedInvoice->journal?->reference ?? 'Not posted',
                ];
                if ($selectedInvoice->correction_of_id) {
                    $facts['Corrected invoice'] = $selectedInvoice->correctionParent?->invoice_number.' · '.$selectedInvoice->correction_reason;
                }
            @endphp
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 border-b border-slate-100 px-5 py-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($facts as $term => $value)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $term }}</dt>
                        <dd class="mt-1 text-sm text-slate-800">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="text-sm font-semibold text-slate-700">Allocations and receipt history</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="{{ $table }} min-w-[560px]">
                        <thead class="{{ $thead }}"><tr><th scope="col" class="{{ $th }}">Description</th><th scope="col" class="{{ $th }}">Allocation</th><th scope="col" class="{{ $th }}">Quantity</th><th scope="col" class="{{ $th }} text-right">Amount</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($selectedInvoice->lines as $line)
                                <tr>
                                    <td class="{{ $td }} text-slate-700">{{ $line->description }} @if ($line->inventory) · {{ $line->inventory->name }} @elseif ($line->account) · {{ $line->account->code }} {{ $line->account->name }} @endif</td>
                                    <td class="{{ $td }} text-slate-600">{{ $line->inventory_id ? 'Inventory' : 'Asset / expense' }}</td>
                                    <td class="{{ $td }} text-slate-600">{{ $line->quantity ?? '—' }}</td>
                                    <td class="{{ $td }} text-right tabular-nums text-slate-700">{{ number_format($line->line_amount_cents / 100, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($selectedCorrections->isNotEmpty())
                <div class="border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-700">Financial correction and supplier refund history</h3>
                    <div class="mt-3 space-y-3">
                        @foreach ($selectedCorrections as $correction)
                            <article class="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                                <p class="text-sm font-medium text-slate-800">Correction {{ $correction->id }} · {{ $correction->accounting_date->format('Y-m-d') }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $correction->reason }}</p>
                                <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-3">
                                    <div>
                                        <dt class="text-xs font-medium text-slate-500">Original</dt>
                                        <dd class="text-sm tabular-nums text-slate-800">PHP {{ number_format($correction->original_amount_cents / 100, 2) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium text-slate-500">Corrected</dt>
                                        <dd class="text-sm tabular-nums text-slate-800">PHP {{ number_format($correction->corrected_amount_cents / 100, 2) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium text-slate-500">Refund receivable change</dt>
                                        <dd class="text-sm tabular-nums text-slate-800">PHP {{ number_format($correction->refund_due_cents / 100, 2) }}</dd>
                                    </div>
                                </dl>
                                <p class="mt-2 text-xs text-slate-500">Journal {{ $correction->journal?->reference }} · corrected invoice {{ $correction->replacementInvoice?->invoice_number }}</p>
                                @foreach ($correction->refundReceipts as $refundReceipt)
                                    <p class="mt-1 text-xs text-slate-500">Refund received {{ $refundReceipt->receipt_date->format('Y-m-d') }} · {{ $refundReceipt->reference }} · {{ $refundReceipt->evidence_reference }} · PHP {{ number_format($refundReceipt->amount_cents / 100, 2) }} · Journal {{ $refundReceipt->journal?->reference }}</p>
                                @endforeach
                                @if ($canPostRefunds && $correction->id === $selectedCorrections->last()->id && $selectedInvoice->supplierRefundDueCents() > 0)
                                    <form wire:submit="receiveSupplierRefund({{ $correction->id }})" class="mt-4 grid grid-cols-1 gap-4 border-t border-slate-200 pt-4 sm:grid-cols-2 xl:grid-cols-3">
                                        <div>
                                            <label for="refund-amount-{{ $correction->id }}" class="{{ $label }}">Actual refund received (PHP) <span class="text-red-700">*</span></label>
                                            <input id="refund-amount-{{ $correction->id }}" inputmode="decimal" wire:model="refundAmount" required class="{{ $field }}">
                                        </div>
                                        <div>
                                            <label for="refund-account-{{ $correction->id }}" class="{{ $label }}">Cash/Bank account <span class="text-red-700">*</span></label>
                                            <select id="refund-account-{{ $correction->id }}" wire:model="refundMoneyAccountId" required class="{{ $field }}">
                                                <option value="">Select account</option>
                                                @foreach ($cashAccounts as $cashAccount)<option value="{{ $cashAccount->id }}">{{ $cashAccount->code }} — {{ $cashAccount->name }}</option>@endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="refund-reference-{{ $correction->id }}" class="{{ $label }}">Receipt reference <span class="text-red-700">*</span></label>
                                            <input id="refund-reference-{{ $correction->id }}" wire:model="refundReference" maxlength="100" required class="{{ $field }}">
                                        </div>
                                        <div>
                                            <label for="refund-evidence-{{ $correction->id }}" class="{{ $label }}">Evidence reference <span class="text-red-700">*</span></label>
                                            <input id="refund-evidence-{{ $correction->id }}" wire:model="refundEvidenceReference" maxlength="255" required class="{{ $field }}">
                                        </div>
                                        <div>
                                            <label for="refund-date-{{ $correction->id }}" class="{{ $label }}">Receipt date <span class="text-red-700">*</span></label>
                                            <input id="refund-date-{{ $correction->id }}" type="date" wire:model="refundDate" required class="{{ $field }}">
                                        </div>
                                        <div class="flex items-end">
                                            <button type="submit" class="{{ $primary }} w-full">Post linked refund receipt</button>
                                        </div>
                                    </form>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($selectedVatReclassifications->isNotEmpty())
                <div class="border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-700">Allowable purchase VAT reclassification history</h3>
                    <div class="mt-3 space-y-3">
                        @foreach ($selectedVatReclassifications as $reclassification)
                            <article class="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                                <p class="text-sm font-medium text-slate-800">Input VAT PHP {{ number_format($reclassification->amount_cents / 100, 2) }} · {{ $reclassification->accounting_date->format('Y-m-d') }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ $reclassification->reason }}</p>
                                <p class="mt-1 text-xs text-slate-500">Journal {{ $reclassification->journal?->reference }}</p>
                                <ul class="mt-2 space-y-1 text-xs text-slate-600">
                                    @foreach ($reclassification->lines as $line)
                                        <li>{{ $line->purchaseLine?->description }}: PHP {{ number_format($line->amount_cents / 100, 2) }}
                                            @if ($line->inventory_id) (remaining Inventory PHP {{ number_format($line->remaining_inventory_cents / 100, 2) }}, consumed cost PHP {{ number_format($line->consumed_cost_cents / 100, 2) }} to {{ $line->consumedAccount?->name ?? '—' }}) @else ({{ $line->account?->name }}) @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($vatReclassificationLines && $selectedInvoice->id === $this->selectedId && $canReclassifyPurchaseVat)
                <div class="border-b border-slate-100 px-5 py-4">
                    <form wire:submit="postVatReclassification({{ $selectedInvoice->id }})" class="space-y-4 rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-700">Reclass allowable purchase VAT</h3>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Enter the accountant-approved amount and explicitly allocate it. No tax eligibility or cost split is inferred. AP and physical quantities do not change.</p>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="vat-reclass-amount" class="{{ $label }}">Approved input VAT (PHP) <span class="text-red-700">*</span></label>
                                <input id="vat-reclass-amount" inputmode="decimal" wire:model="vatReclassificationAmount" required class="{{ $field }}">
                            </div>
                            <div class="sm:col-span-2">
                                <label for="vat-reclass-reason" class="{{ $label }}">Supporting rationale <span class="text-red-700">*</span></label>
                                <textarea id="vat-reclass-reason" wire:model="vatReclassificationReason" maxlength="4000" required rows="2" class="{{ $field }}"></textarea>
                            </div>
                        </div>
                        <div class="space-y-3">
                            @foreach ($vatReclassificationLines as $index => $vatLine)
                                @php($sourceLine = $selectedInvoice->lines->firstWhere('id', $vatLine['line_id']))
                                <fieldset wire:key="vat-reclass-line-{{ $sourceLine->id }}" class="rounded-lg border border-slate-200 bg-white p-4">
                                    <legend class="px-1 text-xs font-semibold text-slate-500">{{ $sourceLine->description }} — source allocation PHP {{ number_format($sourceLine->line_amount_cents / 100, 2) }}</legend>
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                        <div>
                                            <label for="vat-line-{{ $index }}-amount" class="{{ $label }}">Allocated input VAT (PHP)</label>
                                            <input id="vat-line-{{ $index }}-amount" inputmode="decimal" wire:model="vatReclassificationLines.{{ $index }}.amount" class="{{ $field }}">
                                        </div>
                                        @if ($sourceLine->inventory_id)
                                            <div>
                                                <label for="vat-line-{{ $index }}-remaining" class="{{ $label }}">Approved remaining Inventory credit (PHP)</label>
                                                <input id="vat-line-{{ $index }}-remaining" inputmode="decimal" wire:model="vatReclassificationLines.{{ $index }}.remaining_inventory" class="{{ $field }}">
                                            </div>
                                            <div>
                                                <label for="vat-line-{{ $index }}-consumed" class="{{ $label }}">Approved consumed-cost credit (PHP)</label>
                                                <input id="vat-line-{{ $index }}-consumed" inputmode="decimal" wire:model="vatReclassificationLines.{{ $index }}.consumed_cost" class="{{ $field }}">
                                            </div>
                                            <div>
                                                <label for="vat-line-{{ $index }}-account" class="{{ $label }}">Consumed-cost account</label>
                                                <select id="vat-line-{{ $index }}-account" wire:model="vatReclassificationLines.{{ $index }}.consumed_accounting_account_id" class="{{ $field }}">
                                                    <option value="">Select approved expense account</option>
                                                    @foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach
                                                </select>
                                            </div>
                                        @else
                                            <p class="text-xs leading-5 text-slate-500 sm:col-span-2">Credits the original purchase account: {{ $sourceLine->account?->code }} — {{ $sourceLine->account?->name }}.</p>
                                        @endif
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" class="{{ $primary }}">Post approved VAT reclassification</button>
                            <button type="button" wire:click="cancelVatReclassification" class="{{ $secondary }}">Cancel</button>
                        </div>
                    </form>
                </div>
            @endif

            @if ($correctionLines && $selectedInvoice->id === $this->selectedId)
                <div class="border-b border-slate-100 px-5 py-4">
                    <form wire:submit="postCorrection({{ $selectedInvoice->id }})" class="space-y-4 rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                        <h3 class="text-sm font-semibold text-slate-700">Correct posted purchase</h3>
                        <div>
                            <label for="correction-reason" class="{{ $label }}">Reason for correction <span class="text-red-700">*</span></label>
                            <textarea id="correction-reason" wire:model="correctionReason" maxlength="4000" required rows="2" class="{{ $field }}"></textarea>
                        </div>
                        <div class="space-y-3">
                            @foreach ($correctionLines as $index => $correctionLine)
                                @php($sourceLine = $selectedInvoice->lines->firstWhere('id', $correctionLine['line_id']))
                                <fieldset wire:key="correction-line-{{ $sourceLine->id }}" class="rounded-lg border border-slate-200 bg-white p-4">
                                    <legend class="px-1 text-xs font-semibold text-slate-500">{{ $sourceLine->description }} — original PHP {{ number_format($sourceLine->line_amount_cents / 100, 2) }}</legend>
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                        <div>
                                            <label for="correction-line-{{ $index }}-amount" class="{{ $label }}">Corrected line value (PHP) <span class="text-red-700">*</span></label>
                                            <input id="correction-line-{{ $index }}-amount" inputmode="decimal" wire:model="correctionLines.{{ $index }}.corrected_amount" required class="{{ $field }}">
                                        </div>
                                        @if ($sourceLine->inventory_id)
                                            <div class="sm:col-span-2">
                                                <p class="text-xs leading-5 text-slate-500">Physical quantity remains {{ $sourceLine->quantity }} unless a separate authorized physical correction is completed.</p>
                                            </div>
                                            <div>
                                                <label for="correction-line-{{ $index }}-remaining" class="{{ $label }}">Reviewed remaining inventory value change (PHP)</label>
                                                <input id="correction-line-{{ $index }}-remaining" inputmode="decimal" wire:model="correctionLines.{{ $index }}.remaining_inventory" class="{{ $field }}">
                                            </div>
                                            <div>
                                                <label for="correction-line-{{ $index }}-consumed" class="{{ $label }}">Reviewed consumed cost change (PHP)</label>
                                                <input id="correction-line-{{ $index }}-consumed" inputmode="decimal" wire:model="correctionLines.{{ $index }}.consumed_cost" class="{{ $field }}">
                                            </div>
                                            <div>
                                                <label for="correction-line-{{ $index }}-account" class="{{ $label }}">Consumed cost account</label>
                                                <select id="correction-line-{{ $index }}-account" wire:model="correctionLines.{{ $index }}.consumed_accounting_account_id" class="{{ $field }}">
                                                    <option value="">Select approved expense account</option>
                                                    @foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach
                                                </select>
                                            </div>
                                        @endif
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>
                        <button type="submit" class="{{ $primary }}">Post linked financial correction</button>
                    </form>
                </div>
            @endif

            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="text-sm font-semibold text-slate-700">Payment allocation and correction history</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="{{ $table }} min-w-[820px]">
                        <thead class="{{ $thead }}"><tr><th scope="col" class="{{ $th }}">Date</th><th scope="col" class="{{ $th }}">Payment reference</th><th scope="col" class="{{ $th }}">Evidence</th><th scope="col" class="{{ $th }}">Journal</th><th scope="col" class="{{ $th }}">Actor</th><th scope="col" class="{{ $th }} text-right">Allocation</th><th scope="col" class="{{ $th }}">Correction</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($selectedPaymentAllocations->filter(fn ($allocation) => $allocation->disbursement->status === 'posted')->sortBy(fn ($allocation) => [$allocation->disbursement->payment_date, $allocation->disbursement->id]) as $allocation)
                                @php($payment = $allocation->disbursement)
                                <tr>
                                    <td class="{{ $td }} whitespace-nowrap text-slate-600">{{ $payment->payment_date->format('Y-m-d') }}</td>
                                    <td class="{{ $td }} text-slate-700">{{ $payment->reference }} · {{ $payment->method }}</td>
                                    <td class="{{ $td }} text-slate-600">{{ $payment->evidence_reference }}</td>
                                    <td class="{{ $td }} text-slate-600">{{ $payment->journal?->reference ?? 'Not posted' }}</td>
                                    <td class="{{ $td }} text-slate-600">{{ $payment->posted_by }}</td>
                                    <td class="{{ $td }} text-right tabular-nums text-slate-700">{{ $payment->reversal_of_id ? '−' : '' }}PHP {{ number_format($allocation->amount_cents / 100, 2) }}</td>
                                    <td class="{{ $td }} text-slate-600">@if ($payment->reversal_of_id) Reversal of {{ $payment->reversalOf?->reference }}: {{ $payment->correction_reason }} @elseif ($payment->reversals->isNotEmpty()) Reversed by {{ $payment->reversals->first()->reference }}: {{ $payment->reversals->first()->correction_reason }} @else — @endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-6 py-8 text-center text-sm text-slate-500">No posted payment allocations.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="border-b border-slate-100 px-5 py-4">
                <h3 class="text-sm font-semibold text-slate-700">Valued receipt movements</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="{{ $table }} min-w-[720px]">
                        <thead class="{{ $thead }}"><tr><th scope="col" class="{{ $th }}">Inventory item</th><th scope="col" class="{{ $th }}">Receipt date</th><th scope="col" class="{{ $th }}">Supplier reference</th><th scope="col" class="{{ $th }}">Quantity</th><th scope="col" class="{{ $th }} text-right">Receipt value</th><th scope="col" class="{{ $th }} text-right">Carrying value after</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($receiptMovements as $movement)
                                <tr>
                                    <td class="{{ $td }} text-slate-700">{{ $movement->inventory?->name }}</td>
                                    <td class="{{ $td }} whitespace-nowrap text-slate-600">{{ $movement->effective_date->format('Y-m-d') }}</td>
                                    <td class="{{ $td }} text-slate-600">{{ $movement->reference }}</td>
                                    <td class="{{ $td }} tabular-nums text-slate-600">{{ $movement->quantity }}</td>
                                    <td class="{{ $td }} text-right tabular-nums text-slate-700">{{ number_format($movement->value_cents / 100, 2) }}</td>
                                    <td class="{{ $td }} text-right tabular-nums text-slate-700">{{ number_format($movement->carrying_value_after_cents / 100, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">No valued receipt has been posted for this draft.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($selectedInvoice->journal)
                <div class="border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-700">Posted journal</h3>
                    <div class="mt-3 overflow-x-auto">
                        <table class="{{ $table }} min-w-[480px]">
                            <thead class="{{ $thead }}"><tr><th scope="col" class="{{ $th }}">Account</th><th scope="col" class="{{ $th }} text-right">Debit</th><th scope="col" class="{{ $th }} text-right">Credit</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($selectedInvoice->journal->lines as $line)
                                    <tr>
                                        <td class="{{ $td }} text-slate-700">{{ $line->account?->code }} — {{ $line->account?->name }}</td>
                                        <td class="{{ $td }} text-right tabular-nums text-slate-700">{{ number_format($line->debit_cents / 100, 2) }}</td>
                                        <td class="{{ $td }} text-right tabular-nums text-slate-700">{{ number_format($line->credit_cents / 100, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="flex justify-end px-5 py-4">
                <button type="button" onclick="window.print()" class="{{ $primary }}">Print invoice / receipt allocations</button>
            </div>
        </section>
    @endif
</section>
