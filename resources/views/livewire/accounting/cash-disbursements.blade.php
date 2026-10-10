<section class="accounting-page cash-disbursements-page" aria-labelledby="cash-disbursements-heading">
    <header class="dashboard-heading">
        <div>
            <h1 id="cash-disbursements-heading">Cash Disbursements</h1>
            <p class="dashboard-subtitle">Record direct payments to acquired assets or expenses, or allocate payments to one supplier’s posted invoices.</p>
    </header>

    @if (session()->has('disbursement-message'))<p role="status">{{ session('disbursement-message') }}</p>@endif
    @if ($errors->any())<div role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="accounting-balance-grid" aria-label="Posted disbursements">
        <article class="dashboard-card">
            <p class="dashboard-stat-label">Posted payments</p>
            <p class="accounting-unavailable">PHP {{ number_format($postedTotalCents / 100, 2) }}</p>
            <p class="dashboard-stat-note">Posted source records only; includes AP settlements and excludes linked reversal rows.</p>
        </article>
    </section>

    @can('accounting.prepare-disbursements')
        <section class="chart-account-card" aria-labelledby="disbursement-form-heading">
            <h2 id="disbursement-form-heading">{{ $editingId ? 'Edit payment draft' : 'Prepare payment' }}</h2>
            <p>Drafts have no cash, payable or journal effect. Released checks post on release to the payee.</p>
            <form wire:submit="saveDraft">
                <label>Supplier settlement (leave blank for direct payment)<select wire:model.live="supplierId"><option value="">Direct disbursement</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}</option>@endforeach</select></label>
                @unless ($supplierId)<label>Payee<input wire:model="payee" maxlength="180" required></label>@endunless
                <label>Payment / release date<input type="date" wire:model="paymentDate" required></label>
                <label>Method<select wire:model="method"><option>Cash</option><option>Bank Transfer</option><option>Check</option>@foreach ($otherMethods as $otherMethod)<option value="{{ $otherMethod }}">{{ $otherMethod }}</option>@endforeach</select></label>
                <label>Actual Cash / Bank account<select wire:model="moneyAccountId" required><option value="">Select approved money account</option>@foreach ($moneyAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }} ({{ ucfirst($account->classification) }})</option>@endforeach</select></label>
                <label>Payment reference<input wire:model="reference" maxlength="100" required></label>
                @if ($method === 'Check')<label>Check number<input wire:model="checkNumber" maxlength="80" required></label>@endif
                <label>Description<textarea wire:model="description" maxlength="4000" required></textarea></label>
                <label>Supporting document or reference<input wire:model="evidenceReference" maxlength="255" required></label>
                <label>Payment amount (PHP)<input inputmode="decimal" wire:model="amount" required></label>
                @if (!$supplierId && collect($allocations)->contains(fn ($allocation) => ($allocation['allocation_type'] ?? 'account') === 'inventory'))
                    <label><input type="checkbox" wire:model="receiptConfirmed" required> I confirm all listed inventory quantities have been physically received.</label>
                @endif
                <h3>{{ $supplierId ? 'Invoice allocations' : 'Payment allocations' }}</h3>
                <p>{{ $supplierId ? 'Allocate the exact payment across posted invoices for this supplier; advances are not supported.' : 'Allocate the exact payment across inventory receipts and approved non-inventory accounts. Inventory receipt value uses the gross purchase cost.' }}</p>
                @foreach ($allocations as $index => $line)
                    <fieldset wire:key="disbursement-allocation-{{ $index }}">
                        <legend>Allocation {{ $index + 1 }}</legend>
                        @if ($supplierId)
                            <label>Posted invoice<select wire:model="allocations.{{ $index }}.invoice_id" required><option value="">Select invoice</option>@foreach ($eligibleInvoices as $invoice)<option value="{{ $invoice['key'] }}">{{ $invoice['kind'] }} · {{ $invoice['invoice_number'] }} · outstanding PHP {{ number_format($invoice['outstanding_cents'] / 100, 2) }}</option>@endforeach</select></label>
                        @else
                            <label>Allocation type<select wire:model.live="allocations.{{ $index }}.allocation_type"><option value="account">Non-inventory account</option>@if ($inventoryItems->isNotEmpty())<option value="inventory">Inventory purchase</option>@endif</select></label>
                            @if (($line['allocation_type'] ?? 'account') === 'inventory')
                                <label>Received inventory item<select wire:model="allocations.{{ $index }}.inventory_id" required><option value="">Select item</option>@foreach ($inventoryItems as $item)<option value="{{ $item->id }}">{{ $item->code }} — {{ $item->name }} ({{ $item->unit }})</option>@endforeach</select></label>
                                <label>Received quantity<input inputmode="decimal" wire:model="allocations.{{ $index }}.quantity" required></label>
                            @else
                                <label>Debit account<select wire:model="allocations.{{ $index }}.accounting_account_id" required><option value="">Select approved account</option>@foreach ($debitAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</select></label>
                            @endif
                        @endif
                        <label>Allocation description<input wire:model="allocations.{{ $index }}.description" maxlength="255" required></label>
                        <label>Gross allocation amount (PHP)<input inputmode="decimal" wire:model="allocations.{{ $index }}.amount" required></label>
                        @if (count($allocations) > 1)<button type="button" wire:click="removeAllocation({{ $index }})">Remove allocation</button>@endif
                    </fieldset>
                @endforeach
                <button type="button" wire:click="addAllocation">Add allocation</button>
                <button type="submit">{{ $editingId ? 'Save draft changes' : 'Save draft' }}</button>
            </form>
        </section>
    @endcan

    <section class="dashboard-card accounting-activity" aria-labelledby="cash-disbursement-records-heading">
        <header class="dashboard-card-heading"><div><h2 id="cash-disbursement-records-heading">Disbursements</h2><p class="dashboard-chart-note">Search and inspect saved drafts, posted payments, allocations, and linked corrections.</p></div></header>
        <div class="payable-filters" aria-label="Filter disbursement records">
            <label><span>Search reference or payee</span><input type="search" wire:model.live.debounce.250ms="search" placeholder="Search reference, payee or evidence"></label>
            <label><span>Method</span><select wire:model.live="methodFilter"><option value="All">All methods</option><option value="Cash">Cash</option><option value="Bank Transfer">Bank Transfer</option><option value="Check">Check</option>@foreach ($otherMethods as $otherMethod)<option value="{{ $otherMethod }}">{{ $otherMethod }}</option>@endforeach</select></label>
            <label><span>Status</span><select wire:model.live="status"><option>All</option><option>Draft</option><option>Posted</option></select></label>
            <label><span>From date</span><input type="date" wire:model.live="fromDate"></label>
            <label><span>To date</span><input type="date" wire:model.live="toDate"></label>
        </div>
        <div class="reports-table-wrap">
            <table class="reports-table">
                <thead><tr><th scope="col">Reference</th><th scope="col">Payee</th><th scope="col">Date</th><th scope="col">Method</th><th scope="col" class="numeric">Amount</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                    @forelse ($disbursements as $disbursement)
                        <tr wire:key="cash-disbursement-{{ $disbursement->id }}">
                            <td>{{ $disbursement->reference }}</td><td>{{ $disbursement->payee }}</td><td>{{ $disbursement->payment_date->format('Y-m-d') }}</td><td>{{ $disbursement->method }}</td>
                            <td class="numeric">PHP {{ number_format($disbursement->amount_cents / 100, 2) }}</td>
                            <td>{{ $disbursement->status === 'posted' ? 'Posted' : 'Draft' }}@if ($disbursement->reversal_of_id) · Reversal of {{ $disbursement->reversalOf?->reference }}@endif</td>
                            <td>
                                <button type="button" wire:click="showDisbursement({{ $disbursement->id }})">Detail</button>
                                @if ($disbursement->status === 'draft' && ! $disbursement->reversal_of_id)
                                    @can('accounting.prepare-disbursements')<button type="button" wire:click="editDraft({{ $disbursement->id }})">Edit draft</button><button type="button" wire:click="deleteDraft({{ $disbursement->id }})">Delete draft</button>@endcan
                                    @if ($disbursement->method === 'Check')<span>Prepared check — release to payee before posting.</span>@endif
                                    @can('accounting.post-disbursements')<button type="button" wire:click="postDisbursement({{ $disbursement->id }})">{{ $disbursement->method === 'Check' ? 'Post on release' : 'Post payment' }}</button>@endcan
                                @endif
                                @if ($disbursement->status === 'posted' && ! $disbursement->reversal_of_id && $disbursement->reversals->isEmpty() && ! $disbursement->lines->contains(fn ($line) => $line->inventory_id !== null))
                                    @can('accounting.post-disbursements')<button type="button" wire:click="set('selectedId', {{ $disbursement->id }})">Prepare reversal</button>@endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="reports-empty payable-empty" colspan="7">No disbursements match this search or state.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($selectedDisbursement)
        <section class="chart-account-card" aria-labelledby="disbursement-detail-heading">
            <h2 id="disbursement-detail-heading">Disbursement {{ $selectedDisbursement->reference }}</h2>
            <p>{{ $selectedDisbursement->description }}</p>
            <dl><dt>Payee / supplier</dt><dd>{{ $selectedDisbursement->supplier?->name ?? $selectedDisbursement->payee }}</dd><dt>Method / reference</dt><dd>{{ $selectedDisbursement->method }} · {{ $selectedDisbursement->reference }}</dd><dt>Check number</dt><dd>{{ $selectedDisbursement->check_number ?: 'Not applicable' }}</dd><dt>Money account</dt><dd>{{ $selectedDisbursement->moneyAccount->code }} — {{ $selectedDisbursement->moneyAccount->name }}</dd><dt>Evidence</dt><dd>{{ $selectedDisbursement->evidence_reference }}</dd><dt>Amount</dt><dd>PHP {{ number_format($selectedDisbursement->amount_cents / 100, 2) }}</dd><dt>Journal</dt><dd>{{ $selectedDisbursement->journal?->reference ?? 'Not posted' }}</dd><dt>Posted actor</dt><dd>{{ $selectedDisbursement->posted_by ?? 'Not posted' }}</dd></dl>
            <h3>{{ $selectedDisbursement->supplier_id ? 'Invoice allocations' : 'Purchase and payment allocations' }}</h3>
            <ul>
                @foreach ($selectedDisbursement->lines as $line)
                    <li>
                        @if ($line->invoice)
                            Purchase invoice {{ $line->invoice->invoice_number }}
                        @elseif ($line->openingInvoice)
                            Opening invoice {{ $line->openingInvoice->invoice_number }}
                        @elseif ($line->inventory_id)
                            Inventory {{ $line->inventory?->name }} · {{ $line->description }} · {{ $line->quantity }} {{ $line->inventory?->unit }} received · gross cost PHP {{ number_format($line->amount_cents / 100, 2) }} · valued receipt {{ $line->stockMovement?->reference ?? 'Not posted' }}
                        @else
                            {{ $line->account->code }} — {{ $line->account->name }}
                        @endif
                        · {{ $line->description }} · PHP {{ number_format($line->amount_cents / 100, 2) }}
                    </li>
                @endforeach
            </ul>
            @if ($selectedDisbursement->reversal_of_id)<p>Linked reversal of {{ $selectedDisbursement->reversalOf?->reference }}. Reason: {{ $selectedDisbursement->correction_reason }}</p>@endif
            @if ($selectedDisbursement->reversals->isNotEmpty())<p>Reversed by {{ $selectedDisbursement->reversals->first()->reference }}. Reason: {{ $selectedDisbursement->reversals->first()->correction_reason }}</p>@endif
            @if ($directCorrections->isEmpty() && $selectedDisbursement->status === 'posted' && ! $selectedDisbursement->supplier_id && ! $selectedDisbursement->reversal_of_id && $selectedDisbursement->reversals->isEmpty() && $canCorrectPurchases)
                <button type="button" wire:click="startDirectPurchaseCorrection({{ $selectedDisbursement->id }})">Correct directly paid purchase</button>
            @endif
            @if ($directCorrectionLines && $selectedDisbursement->id === $this->selectedId)
                <form wire:submit="postDirectPurchaseCorrection({{ $selectedDisbursement->id }})">
                    <h3>Correct directly paid purchase</h3>
                    <label>Supplier<select wire:model="directCorrectionSupplierId" required><option value="">Select supplier</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}</option>@endforeach</select></label>
                    <label>Reason<textarea wire:model="directCorrectionReason" maxlength="4000" required></textarea></label>
                    @foreach ($directCorrectionLines as $index => $correctionLine)
                        @php($sourceLine = $selectedDisbursement->lines->firstWhere('id', $correctionLine['line_id']))
                        <fieldset wire:key="direct-correction-line-{{ $sourceLine->id }}">
                            <legend>{{ $sourceLine->description }} — original PHP {{ number_format($sourceLine->amount_cents / 100, 2) }}</legend>
                            <label>Corrected line value (PHP)<input inputmode="decimal" wire:model="directCorrectionLines.{{ $index }}.corrected_amount" required></label>
                            @if ($sourceLine->inventory_id)
                                <p>Physical quantity remains {{ $sourceLine->quantity }}; value corrections do not reverse stock.</p>
                                <label>Reviewed remaining inventory reduction (PHP)<input inputmode="decimal" wire:model="directCorrectionLines.{{ $index }}.remaining_inventory"></label>
                                <label>Reviewed consumed cost reduction (PHP)<input inputmode="decimal" wire:model="directCorrectionLines.{{ $index }}.consumed_cost"></label>
                                <label>Consumed cost account<select wire:model="directCorrectionLines.{{ $index }}.consumed_accounting_account_id"><option value="">Select approved expense account</option>@foreach ($correctionExpenseAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</select></label>
                            @endif
                        </fieldset>
                    @endforeach
                    <button type="submit">Post direct purchase correction</button>
                </form>
            @endif
            @foreach ($directCorrections as $correction)
                <article>
                    <h3>Direct purchase correction {{ $correction->id }}</h3>
                    <p>{{ $correction->reason }} · Supplier {{ $correction->supplier?->name }} · Original PHP {{ number_format($correction->original_amount_cents / 100, 2) }} · Corrected PHP {{ number_format($correction->corrected_amount_cents / 100, 2) }} · Refund due PHP {{ number_format(max(0, $correction->refund_due_cents - $correction->refundReceipts->sum('amount_cents')) / 100, 2) }}</p>
                    <p>Journal {{ $correction->journal?->reference }}</p>
                    @foreach ($correction->refundReceipts as $refundReceipt)
                        <p>Refund received {{ $refundReceipt->receipt_date->format('Y-m-d') }} · {{ $refundReceipt->reference }} · PHP {{ number_format($refundReceipt->amount_cents / 100, 2) }} · Journal {{ $refundReceipt->journal?->reference }}</p>
                    @endforeach
                    @if ($canPostRefunds && $correction->refund_due_cents > $correction->refundReceipts->sum('amount_cents'))
                        <form wire:submit="receiveSupplierRefund({{ $correction->id }})">
                            <label>Actual refund received (PHP)<input inputmode="decimal" wire:model="refundAmount" required></label>
                            <label>Cash/Bank account<select wire:model="refundMoneyAccountId" required><option value="">Select account</option>@foreach ($moneyAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</select></label>
                            <label>Receipt reference<input wire:model="refundReference" maxlength="100" required></label>
                            <label>Evidence reference<input wire:model="refundEvidenceReference" maxlength="255" required></label>
                            <label>Receipt date<input type="date" wire:model="refundDate" required></label>
                            <button type="submit">Post linked refund receipt</button>
                        </form>
                    @endif
                </article>
            @endforeach
            @if ($selectedDisbursement->status === 'posted' && ! $selectedDisbursement->reversal_of_id && $selectedDisbursement->reversals->isEmpty() && ! $selectedDisbursement->lines->contains(fn ($line) => $line->inventory_id !== null))
                @can('accounting.post-disbursements')
                    <form wire:submit="reverseDisbursement({{ $selectedDisbursement->id }})"><label>Reason for returned / voided payment<textarea wire:model="reversalReason" maxlength="4000" required></textarea></label><button type="submit">Post linked reversal</button></form>
                @endcan
            @endif
        </section>
    @endif
</section>
