<section class="accounting-page" aria-labelledby="supplier-purchases-heading">
    <header class="dashboard-heading">
        <div><h1 id="supplier-purchases-heading">Supplier Credit Purchases</h1><p class="dashboard-subtitle">Prepare invoices, then post confirmed receipts as one payable, stock valuation, and journal event.</p></div>
    </header>
    @if (session()->has('purchase-message'))<p role="status">{{ session('purchase-message') }}</p>@endif
    @if ($errors->any())<div role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="accounting-balance-grid" aria-label="Posted supplier purchase totals">
        <article class="dashboard-card"><p class="dashboard-stat-label">Posted purchases</p><p>PHP {{ number_format($purchaseTotalCents / 100, 2) }}</p></article>
        <article class="dashboard-card"><p class="dashboard-stat-label">Paid</p><p>PHP {{ number_format($paidTotalCents / 100, 2) }}</p></article>
        <article class="dashboard-card"><p class="dashboard-stat-label">Outstanding</p><p>PHP {{ number_format($outstandingCents / 100, 2) }}</p></article>
        <article class="dashboard-card"><p class="dashboard-stat-label">Overdue outstanding</p><p>PHP {{ number_format($overdueCents / 100, 2) }}</p><p class="dashboard-stat-note">Posted invoice balances net of posted payments and linked reversals.</p></article>
    </section>

    @can('accounting.prepare-supplier-purchases')
        <section class="chart-account-card" aria-labelledby="purchase-form-heading">
            <h2 id="purchase-form-heading">{{ $editingId ? 'Edit supplier invoice draft' : 'Prepare supplier invoice' }}</h2>
            <p>Saving or editing a draft creates no payable, valued receipt, or journal. Future-dated invoices may remain drafts.</p>
            <form wire:submit="saveDraft">
                <label>Supplier<select wire:model="supplierId" required><option value="">Select supplier</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}</option>@endforeach</select></label>
                <label>Supplier invoice number<input wire:model="invoiceNumber" maxlength="100" required></label>
                <label>Recognition date<input type="date" wire:model="recognitionDate" required></label>
                <label>Due date<input type="date" wire:model="dueDate" required></label>
                <label>Description<textarea wire:model="description" maxlength="4000" required></textarea></label>
                <label>Terms<input wire:model="terms" maxlength="180"></label>
                <label><input type="checkbox" wire:model="receiptConfirmed"> Physical receipt confirmed</label>
                <h3>Invoice allocations</h3>
                @foreach ($lines as $index => $line)
                    <fieldset wire:key="purchase-line-{{ $index }}"><legend>Allocation {{ $index + 1 }}</legend>
                        <label>Inventory item<select wire:model="lines.{{ $index }}.inventory_id"><option value="">Not inventory</option>@foreach ($inventoryItems as $item)<option value="{{ $item->id }}">{{ $item->code }} — {{ $item->name }}</option>@endforeach</select></label>
                        <label>Non-inventory asset/expense account<select wire:model="lines.{{ $index }}.accounting_account_id"><option value="">Choose when not inventory</option>@foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</select></label>
                        <label>Line description<input wire:model="lines.{{ $index }}.description" maxlength="255" required></label>
                        <label>Receipt quantity (inventory only)<input inputmode="decimal" wire:model="lines.{{ $index }}.quantity"></label>
                        <label>Gross allocation (PHP)<input inputmode="decimal" wire:model="lines.{{ $index }}.amount" required></label>
                        @if (count($lines) > 1)<button type="button" wire:click="removeLine({{ $index }})">Remove allocation</button>@endif
                    </fieldset>
                @endforeach
                <button type="button" wire:click="addLine">Add allocation</button>
                <button type="submit">{{ $editingId ? 'Save invoice draft' : 'Save invoice draft' }}</button>
            </form>
        </section>
    @endcan

    <section class="dashboard-card accounting-activity" aria-labelledby="purchase-register-heading">
        <header class="dashboard-card-heading"><div><h2 id="purchase-register-heading">Supplier invoice register</h2><p class="dashboard-chart-note">Outstanding and overdue are derived from posted purchase and payment records.</p></div></header>
        <div class="payable-filters">
            <label>Search supplier, invoice, or description<input type="search" wire:model.live.debounce.250ms="search"></label>
            <label>Supplier<select wire:model.live="supplierFilter"><option value="">All suppliers</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}</option>@endforeach</select></label>
            <label>From recognition date<input type="date" wire:model.live="fromDate"></label><label>To recognition date<input type="date" wire:model.live="toDate"></label>
            <label>Status<select wire:model.live="status"><option>All</option><option>Draft</option><option>Unpaid</option><option>Partially Paid</option><option>Paid</option><option>Overdue</option></select></label>
        </div>
        <div class="reports-table-wrap"><table class="reports-table"><thead><tr><th>Supplier</th><th>Invoice</th><th>Recognition</th><th>Due</th><th class="numeric">Gross</th><th class="numeric">Paid</th><th class="numeric">Outstanding</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            @forelse ($invoices as $invoice)
                <tr wire:key="purchase-invoice-{{ $invoice->id }}"><td>{{ $invoice->supplier_name_snapshot }}</td><td>{{ $invoice->invoice_number }} @if ($invoice->correction_of_id)<small>Corrected identity for {{ $invoice->correctionParent?->invoice_number }}</small>@elseif ($invoice->correctionChildren->isNotEmpty())<small>Corrected</small>@endif</td><td>{{ $invoice->recognition_date->format('Y-m-d') }}</td><td>{{ $invoice->due_date->format('Y-m-d') }}</td><td class="numeric">{{ number_format($invoice->activeCorrectedAmountCents() / 100, 2) }}</td><td class="numeric">{{ number_format($invoice->paidAmountCents() / 100, 2) }}</td><td class="numeric">{{ number_format($invoice->outstandingAmountCents() / 100, 2) }}</td><td>{{ $invoice->isOverdueOn($today) ? 'Overdue · ' : '' }}{{ $invoice->payableStatus() }}</td><td>
                    <button type="button" wire:click="showInvoice({{ $invoice->id }})">Detail / print</button>
                    @if ($invoice->status === 'draft')
                        @can('accounting.prepare-supplier-purchases')<button type="button" wire:click="editDraft({{ $invoice->id }})">Edit</button><button type="button" wire:click="deleteDraft({{ $invoice->id }})">Delete</button>@endcan
                        @can('accounting.post-supplier-purchases')<button type="button" wire:click="postInvoice({{ $invoice->id }})">Post received invoice</button>@endcan
                    @endif
                    @if ($invoice->status === 'posted' && $invoice->supplier_purchase_correction_id === null && $invoice->correctionChildren->isEmpty() && $canCorrectPurchases)
                        <button type="button" wire:click="startCorrection({{ $invoice->id }})">Correct purchase</button>
                    @endif
                </td></tr>
            @empty<tr><td colspan="9">No supplier invoices match these filters.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    @if ($selectedInvoice)
        <style>@media print { body * { visibility: hidden !important; } #supplier-purchase-print, #supplier-purchase-print * { visibility: visible !important; } #supplier-purchase-print { position: absolute; inset: 0; width: 100%; } #supplier-purchase-print button { display: none !important; } }</style>
        <section id="supplier-purchase-print" class="chart-account-card" aria-labelledby="purchase-detail-heading">
            <h2 id="purchase-detail-heading">Supplier invoice {{ $selectedInvoice->invoice_number }} · {{ ucfirst($selectedInvoice->status) }}</h2>
            <p>{{ $selectedInvoice->supplier_name_snapshot }} ({{ $selectedInvoice->supplier_code_snapshot }})</p><p>{{ $selectedInvoice->description }}</p>
            <dl><dt>Recognition date</dt><dd>{{ $selectedInvoice->recognition_date->format('Y-m-d') }}</dd><dt>Due date</dt><dd>{{ $selectedInvoice->due_date->format('Y-m-d') }}</dd><dt>Terms</dt><dd>{{ $selectedInvoice->terms ?: 'Not recorded' }}</dd><dt>Receipt confirmed</dt><dd>{{ $selectedInvoice->receipt_confirmed ? 'Yes' : 'No' }}</dd><dt>Original gross amount</dt><dd>PHP {{ number_format($selectedInvoice->gross_amount_cents / 100, 2) }}</dd><dt>Corrected active value</dt><dd>PHP {{ number_format($selectedInvoice->activeCorrectedAmountCents() / 100, 2) }}</dd><dt>Paid</dt><dd>PHP {{ number_format($selectedInvoice->paidAmountCents() / 100, 2) }}</dd><dt>Outstanding AP</dt><dd>PHP {{ number_format($selectedInvoice->outstandingAmountCents() / 100, 2) }}</dd><dt>Supplier refund receivable outstanding</dt><dd>PHP {{ number_format($selectedInvoice->supplierRefundDueCents() / 100, 2) }}</dd><dt>Payable status</dt><dd>{{ $selectedInvoice->isOverdueOn($today) ? 'Overdue · ' : '' }}{{ $selectedInvoice->payableStatus() }}</dd><dt>Accounting journal</dt><dd>{{ $selectedInvoice->journal?->reference ?? 'Not posted' }}</dd>@if ($selectedInvoice->correction_of_id)<dt>Corrected invoice</dt><dd>{{ $selectedInvoice->correctionParent?->invoice_number }} · {{ $selectedInvoice->correction_reason }}</dd>@endif</dl>
            <h3>Allocations and receipt history</h3><table class="reports-table"><thead><tr><th>Description</th><th>Allocation</th><th>Quantity</th><th class="numeric">Amount</th></tr></thead><tbody>@foreach ($selectedInvoice->lines as $line)<tr><td>{{ $line->description }} @if ($line->inventory) · {{ $line->inventory->name }} @elseif ($line->account) · {{ $line->account->code }} {{ $line->account->name }} @endif</td><td>{{ $line->inventory_id ? 'Inventory' : 'Asset / expense' }}</td><td>{{ $line->quantity ?? '—' }}</td><td class="numeric">{{ number_format($line->line_amount_cents / 100, 2) }}</td></tr>@endforeach</tbody></table>

            @if ($selectedCorrections->isNotEmpty())
                <h3>Financial correction and supplier refund history</h3>
                @foreach ($selectedCorrections as $correction)
                    <article>
                        <p>Correction {{ $correction->id }} · {{ $correction->accounting_date->format('Y-m-d') }} · {{ $correction->reason }}</p>
                        <p>Original PHP {{ number_format($correction->original_amount_cents / 100, 2) }} · Corrected PHP {{ number_format($correction->corrected_amount_cents / 100, 2) }} · Refund receivable change PHP {{ number_format($correction->refund_due_cents / 100, 2) }}</p>
                        <p>Journal {{ $correction->journal?->reference }} · corrected invoice {{ $correction->replacementInvoice?->invoice_number }}</p>
                        @foreach ($correction->refundReceipts as $refundReceipt)
                            <p>Refund received {{ $refundReceipt->receipt_date->format('Y-m-d') }} · {{ $refundReceipt->reference }} · {{ $refundReceipt->evidence_reference }} · PHP {{ number_format($refundReceipt->amount_cents / 100, 2) }} · Journal {{ $refundReceipt->journal?->reference }}</p>
                        @endforeach
                        @if ($canPostRefunds && $correction->id === $selectedCorrections->last()->id && $selectedInvoice->supplierRefundDueCents() > 0)
                            <form wire:submit="receiveSupplierRefund({{ $correction->id }})">
                                <label>Actual refund received (PHP)<input inputmode="decimal" wire:model="refundAmount" required></label>
                                <label>Cash/Bank account<select wire:model="refundMoneyAccountId" required><option value="">Select account</option>@foreach ($cashAccounts as $cashAccount)<option value="{{ $cashAccount->id }}">{{ $cashAccount->code }} — {{ $cashAccount->name }}</option>@endforeach</select></label>
                                <label>Receipt reference<input wire:model="refundReference" maxlength="100" required></label>
                                <label>Evidence reference<input wire:model="refundEvidenceReference" maxlength="255" required></label>
                                <label>Receipt date<input type="date" wire:model="refundDate" required></label>
                                <button type="submit">Post linked refund receipt</button>
                            </form>
                        @endif
                    </article>
                @endforeach
            @endif
            @if ($correctionLines && $selectedInvoice->id === $this->selectedId)
                <form wire:submit="postCorrection({{ $selectedInvoice->id }})">
                    <h3>Correct posted purchase</h3>
                    <label>Reason for correction<textarea wire:model="correctionReason" maxlength="4000" required></textarea></label>
                    @foreach ($correctionLines as $index => $correctionLine)
                        @php($sourceLine = $selectedInvoice->lines->firstWhere('id', $correctionLine['line_id']))
                        <fieldset wire:key="correction-line-{{ $sourceLine->id }}">
                            <legend>{{ $sourceLine->description }} — original PHP {{ number_format($sourceLine->line_amount_cents / 100, 2) }}</legend>
                            <label>Corrected line value (PHP)<input inputmode="decimal" wire:model="correctionLines.{{ $index }}.corrected_amount" required></label>
                            @if ($sourceLine->inventory_id)
                                <p>Physical quantity remains {{ $sourceLine->quantity }} unless a separate authorized physical correction is completed.</p>
                                <label>Reviewed remaining inventory value change (PHP)<input inputmode="decimal" wire:model="correctionLines.{{ $index }}.remaining_inventory"></label>
                                <label>Reviewed consumed cost change (PHP)<input inputmode="decimal" wire:model="correctionLines.{{ $index }}.consumed_cost"></label>
                                <label>Consumed cost account<select wire:model="correctionLines.{{ $index }}.consumed_accounting_account_id"><option value="">Select approved expense account</option>@foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</select></label>
                            @endif
                        </fieldset>
                    @endforeach
                    <button type="submit">Post linked financial correction</button>
                </form>
            @endif
            <h3>Payment allocation and correction history</h3><table class="reports-table"><thead><tr><th>Date</th><th>Payment reference</th><th>Evidence</th><th>Journal</th><th>Actor</th><th class="numeric">Allocation</th><th>Correction</th></tr></thead><tbody>
                @forelse ($selectedPaymentAllocations->filter(fn ($allocation) => $allocation->disbursement->status === 'posted')->sortBy(fn ($allocation) => [$allocation->disbursement->payment_date, $allocation->disbursement->id]) as $allocation)
                    @php($payment = $allocation->disbursement)
                    <tr><td>{{ $payment->payment_date->format('Y-m-d') }}</td><td>{{ $payment->reference }} · {{ $payment->method }}</td><td>{{ $payment->evidence_reference }}</td><td>{{ $payment->journal?->reference ?? 'Not posted' }}</td><td>{{ $payment->posted_by }}</td><td class="numeric">{{ $payment->reversal_of_id ? '−' : '' }}PHP {{ number_format($allocation->amount_cents / 100, 2) }}</td><td>@if ($payment->reversal_of_id) Reversal of {{ $payment->reversalOf?->reference }}: {{ $payment->correction_reason }} @elseif ($payment->reversals->isNotEmpty()) Reversed by {{ $payment->reversals->first()->reference }}: {{ $payment->reversals->first()->correction_reason }} @else — @endif</td></tr>
                @empty<tr><td colspan="7">No posted payment allocations.</td></tr>@endforelse
            </tbody></table>
            <h3>Valued receipt movements</h3><table class="reports-table"><thead><tr><th>Inventory item</th><th>Receipt date</th><th>Supplier reference</th><th>Quantity</th><th class="numeric">Receipt value</th><th class="numeric">Carrying value after</th></tr></thead><tbody>
                @forelse ($receiptMovements as $movement)
                    <tr><td>{{ $movement->inventory?->name }}</td><td>{{ $movement->effective_date->format('Y-m-d') }}</td><td>{{ $movement->reference }}</td><td>{{ $movement->quantity }}</td><td class="numeric">{{ number_format($movement->value_cents / 100, 2) }}</td><td class="numeric">{{ number_format($movement->carrying_value_after_cents / 100, 2) }}</td></tr>
                @empty<tr><td colspan="6">No valued receipt has been posted for this draft.</td>@endforelse
            </tbody></table>
            @if ($selectedInvoice->journal)<h3>Posted journal</h3><table class="reports-table"><thead><tr><th>Account</th><th class="numeric">Debit</th><th class="numeric">Credit</th></tr></thead><tbody>@foreach ($selectedInvoice->journal->lines as $line)<tr><td>{{ $line->account?->code }} — {{ $line->account?->name }}</td><td class="numeric">{{ number_format($line->debit_cents / 100, 2) }}</td><td class="numeric">{{ number_format($line->credit_cents / 100, 2) }}</td></tr>@endforeach</tbody></table>@endif
            <button type="button" onclick="window.print()">Print invoice / receipt allocations</button>
        </section>
    @endif
</section>
