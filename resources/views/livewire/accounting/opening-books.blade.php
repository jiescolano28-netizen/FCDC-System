<section class="opening-books-page cutover-wizard" aria-labelledby="opening-books-heading">
    <header class="page-heading cutover-heading">
        <div>
            <span class="cutover-eyebrow">FCDC · Accounting setup</span>
            <h1 id="opening-books-heading">Opening Balance / Cutover</h1>
            <p class="page-subtitle">Review existing FCDC records, prepare verified opening balances, and post the cutover once.</p>
        </div>
    </header>

    @if (session()->has('opening-message'))
        <p role="status">{{ session('opening-message') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert"><strong>Needs attention before continuing:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    @if ($journal?->status !== 'posted')
        <p class="cutover-incomplete-warning" role="status">Opening balances or cutover are incomplete. General Ledger and Trial Balance remain available for posted transactions, with this warning until cutover and required checks are complete.</p>
    @elseif (! $readiness['production_activated'])
        <p class="cutover-incomplete-warning" role="status">Opening balances are posted. Production activation is still pending required accounting checks.</p>
    @endif

    <nav class="cutover-stepper" aria-label="Opening balance workflow">
        @foreach ([1 => 'Scan Records', 2 => 'Calculate Balances', 3 => 'Validate & Review', 4 => 'Approve & Post'] as $number => $label)
            <button type="button" wire:click="goToStep({{ $number }})" @class(['is-active' => $currentStep === $number, 'is-complete' => $currentStep > $number]) aria-current="{{ $currentStep === $number ? 'step' : 'false' }}" aria-label="Step {{ $number }}: {{ $label }}">
                <span class="step-number">{{ $number }}</span><span class="step-label">{{ $label }}</span>
            </button>
        @endforeach
    </nav>

    @if ($currentStep === 1)
        <section class="cutover-panel" aria-labelledby="scan-records-heading">
            <h2 id="scan-records-heading">Step 1. Scan Existing Records</h2>
            <p>The scan reads existing FCDC records only. It does not create journals, stock movements, invoices, or balances.</p>
            <div class="cutover-scan-grid">
                <article><span class="scan-card-icon" aria-hidden="true">▦</span><span class="scan-card-title">Chart of Accounts</span><strong>{{ number_format($scan['accounts']) }} accounts</strong><small>{{ number_format($scan['approved_accounts']) }} active and approved for posting</small><span class="scan-card-status">Records scanned</span></article>
                <article><span class="scan-card-icon" aria-hidden="true">▤</span><span class="scan-card-title">Accounting Transactions</span><strong>{{ number_format($scan['posted_journals']) }} posted journals</strong><small>Draft and pending entries are not counted as posted balances</small><span class="scan-card-status">Posted only</span></article>
                <article><span class="scan-card-icon" aria-hidden="true">⬡</span><span class="scan-card-title">Inventory Records</span><strong>{{ number_format($scan['inventory_items']) }} items</strong><small>Quantities are checked against the stock movement history</small><span class="scan-card-status">Records scanned</span></article>
                <article><span class="scan-card-icon" aria-hidden="true">♧</span><span class="scan-card-title">Suppliers / Accounts Payable</span><strong>{{ number_format($scan['suppliers']) }} suppliers</strong><small>{{ number_format($scan['supplier_invoices']) }} posted supplier invoices found</small><span class="scan-card-status">Records scanned</span></article>
            </div>
            <div class="cutover-info"><strong>Scan complete.</strong> Counts above come from saved records. Nothing has been posted or changed.</div>
            <details class="cutover-scan-status"><summary>Current accounting readiness</summary><ul>@foreach ($readiness as $name => $state) @if (is_string($state))<li><span>{{ ucfirst(str_replace('_', ' ', $name)) }}</span><strong>{{ $state }}</strong></li>@endif @endforeach</ul></details>
            <button type="button" wire:click="nextStep">Continue to Calculate Balances →</button>
        </section>
    @elseif ($currentStep === 2)
        <section class="cutover-panel" aria-labelledby="calculate-balances-heading">
            <h2 id="calculate-balances-heading">Step 2. Calculate Balances</h2>
            <p>Posted activity before the cutover is calculated as a read-only Trial Balance preview. It is not copied into the opening journal, which prevents posting the same transactions twice. Enter or import only verified opening balances that are not already represented by those posted transactions.</p>

            <div class="cutover-date-control">
                <label>Cutover date (Asia/Manila)<input type="date" wire:model.live="cutoverDate" required></label>
                <span>Preview includes posted FCDC journal activity before this date.</span>
            </div>

            <div class="cutover-preview-grid">
                <section class="cutover-subpanel">
                    <h3>Existing posted activity preview</h3>
                    @if ($previewLines->isEmpty())
                        <p>No posted account activity was found before the selected cutover date. No balances have been inferred.</p>
                    @else
                        <div class="cutover-table-wrap"><table>
                            <thead><tr><th>Account</th><th>Debit (PHP)</th><th>Credit (PHP)</th></tr></thead>
                            <tbody>
                                @foreach ($previewLines as $line)
                                    <tr><td>{{ $line['account']->code }} — {{ $line['account']->name }}</td><td>{{ $line['debit_cents'] ? number_format($line['debit_cents'] / 100, 2) : '—' }}</td><td>{{ $line['credit_cents'] ? number_format($line['credit_cents'] / 100, 2) : '—' }}</td></tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr><th>Net posted balance preview</th><th>{{ number_format($previewDebitCents / 100, 2) }}</th><th>{{ number_format($previewCreditCents / 100, 2) }}</th></tr></tfoot>
                        </table></div>
                    @endif
                </section>

                <section class="cutover-subpanel">
                    <h3>Opening balance draft</h3>
                    <p>Use only supporting records verified by your accountant. Totals update as you enter amounts.</p>
                    @if ($journal?->status === 'posted')
                        <p role="status">The opening journal is already posted and immutable.</p>
                    @else
                        @can('accounting.maintain-opening-books')
                            <details>
                                <summary>Import account balances from CSV</summary>
                                <p>Use columns <code>account_code,debit,credit</code>. Import fills the draft only.</p>
                                <p><a href="{{ asset('templates/opening-balances.csv') }}" download>Download CSV template</a></p>
                                <label>Account balances CSV<input type="file" wire:model="openingCsv" accept=".csv,text/csv"></label>
                                @error('openingCsv') <p role="alert">{{ $message }}</p> @enderror
                                <button type="button" wire:click="importOpeningCsv" wire:loading.attr="disabled" wire:target="importOpeningCsv,openingCsv">Import to draft</button>
                            </details>
                            <form wire:submit="saveOpening">
                                @foreach ($lines as $index => $line)
                                    <div class="cutover-entry-row" wire:key="opening-line-{{ $index }}">
                                        <label>Approved account
                                            <select wire:model="lines.{{ $index }}.accountId" required>
                                                <option value="">Select account</option>
                                                @foreach ($accounts as $account)
                                                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label>Debit PHP<input inputmode="decimal" wire:model.live.debounce.300ms="lines.{{ $index }}.debit"></label>
                                        <label>Credit PHP<input inputmode="decimal" wire:model.live.debounce.300ms="lines.{{ $index }}.credit"></label>
                                    </div>
                                @endforeach
                                <p class="cutover-live-totals">Debits: ₱{{ $debitTotalCents === null ? '—' : number_format($debitTotalCents / 100, 2) }} · Credits: ₱{{ $creditTotalCents === null ? '—' : number_format($creditTotalCents / 100, 2) }} · <strong>{{ $openingBalanced ? 'Balanced' : 'Needs review' }}</strong></p>
                                <button type="button" wire:click="addOpeningLine">Add account</button>
                                <button type="submit">Save opening balance draft</button>
                            </form>
                        @endcan
                        @if ($journal?->status === 'draft')
                            <p>Draft saved. Prepared by {{ $journal->preparer?->username ?? 'staff member' }}. You can leave and return; saved balances remain here until approved or corrected.</p>
                        @endif
                    @endif
                </section>
            </div>
            <div class="cutover-actions"><button type="button" class="button-secondary" wire:click="previousStep">← Back</button><button type="button" wire:click="nextStep">Continue to Validate and Review →</button></div>
        </section>
    @elseif ($currentStep === 3)
        <section class="cutover-panel" aria-labelledby="validate-review-heading">
            <h2 id="validate-review-heading">Step 3. Validate and Review</h2>
            <p>Resolve each validation before approval. Saving a schedule keeps it as a draft and does not post accounting entries.</p>

            <div class="cutover-validation-grid">
                <section class="cutover-subpanel">
                    <h3>Inventory valuation</h3>
                    <p>{{ $readiness['inventory'] }}</p>
                    @if ($inventoryValuation?->status === 'approved' && ! $editingInventoryValuation)
                        <p>Saved schedule approved for {{ $inventoryValuation->cutover_date->format('F j, Y') }}. Evidence: {{ $inventoryValuation->evidence_reference }}.</p>
                        <div class="cutover-table-wrap"><table><thead><tr><th>Item</th><th>Quantity</th><th>Value (PHP)</th></tr></thead><tbody>
                            @foreach ($inventoryValuation->lines as $line)<tr><td>{{ $line->inventory->code }} — {{ $line->inventory->name }}</td><td>{{ $line->quantity }}</td><td>{{ number_format($line->carrying_value_cents / 100, 2) }}</td></tr>@endforeach
                        </tbody></table></div>
                        @if ($journal?->status !== 'posted') @can('accounting.maintain-opening-books')<button type="button" class="button-secondary" wire:click="reviseInventoryValuation">Revise inventory draft</button>@endcan @endif
                    @else
                        <form wire:submit="saveInventoryValuation">
                            <label>Cutover count/value evidence<input wire:model="inventoryEvidence" maxlength="255" required></label>
                            @can('accounting.maintain-opening-books')
                                <details><summary>Import inventory from CSV</summary><p>Include every current item exactly once. Columns: <code>item_code,quantity,value</code>.</p><p><a href="{{ asset('templates/opening-inventory.csv') }}" download>Download inventory template</a></p><label>Inventory CSV<input type="file" wire:model="inventoryCsv" accept=".csv,text/csv"></label>@error('inventoryCsv')<p role="alert">{{ $message }}</p>@enderror<button type="button" wire:click="importInventoryCsv">Import to draft</button></details>
                            @endcan
                            @foreach ($inventoryLines as $index => $line)
                                @php($item = $inventoryItems->firstWhere('id', (int) $line['inventoryId']))
                                <div class="cutover-entry-row" wire:key="opening-inventory-line-{{ $index }}"><strong>{{ $item?->code }} — {{ $item?->name }} ({{ $item?->unit }})</strong><label>Quantity<input inputmode="decimal" wire:model="inventoryLines.{{ $index }}.quantity" required></label><label>Carrying value PHP<input inputmode="decimal" wire:model="inventoryLines.{{ $index }}.value" required></label></div>
                            @endforeach
                            @can('accounting.maintain-opening-books')<button type="submit">Save inventory schedule</button>@endcan
                        </form>
                        @if ($inventoryValuation?->status === 'draft')<p>Inventory schedule saved as draft; it will be validated with the opening journal.</p>@endif
                    @endif
                </section>

                <section class="cutover-subpanel" id="supplier-schedule">
                    <h3>Supplier / Accounts Payable balances</h3>
                    <p>{{ $readiness['supplier'] }}</p>
                    <p>Opening supplier invoice totals must match the Accounts Payable credit balance exactly.</p>
                    @can('accounting.maintain-suppliers')
                        <a class="button-link" href="{{ route('accounting.accounts-payable') }}">Open Accounts Payable</a>
                        @can('accounting.maintain-opening-books')
                            <details><summary>Import supplier invoices from CSV</summary><p>Suppliers must already exist. Invalid imports are cancelled as a whole. Imported invoices remain drafts.</p><p><a href="{{ asset('templates/opening-suppliers.csv') }}" download>Download supplier template</a></p><label>Supplier invoice CSV<input type="file" wire:model="supplierCsv" accept=".csv,text/csv"></label>@error('supplierCsv')<p role="alert">{{ $message }}</p>@enderror<button type="button" wire:click="importSupplierCsv" wire:loading.attr="disabled" wire:target="importSupplierCsv,supplierCsv">Import invoices as drafts</button></details>
                        @endcan
                    @endcan
                </section>

                <section class="cutover-subpanel">
                    <h3>Midyear YTD evidence</h3>
                    <p>{{ $readiness['ytd'] }}</p>
                    @if ($ytdSummary?->status === 'approved')
                        <p>Approved through {{ $ytdSummary->through_date->format('F j, Y') }}. Evidence: {{ $ytdSummary->evidence_reference }}.</p>
                    @else
                        <form wire:submit="saveYtdSummary">
                            <label>Summary through (day before cutover)<input type="date" wire:model="ytdThroughDate"></label>
                            <label>Supporting evidence reference<input wire:model="ytdEvidence" maxlength="255"></label>
                            @foreach ($ytdLines as $index => $line)
                                <div class="cutover-entry-row" wire:key="ytd-line-{{ $index }}"><label>Approved Revenue or Expense account<select wire:model="ytdLines.{{ $index }}.accountId"><option value="">Select account</option>@foreach ($accounts->whereIn('type', ['Revenue', 'Expense']) as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</select></label><label>YTD amount PHP<input inputmode="decimal" wire:model="ytdLines.{{ $index }}.amount"></label></div>
                            @endforeach
                            @can('accounting.maintain-opening-books')<button type="button" class="button-secondary" wire:click="addYtdLine">Add YTD account</button><button type="submit">Save YTD draft</button>@endcan
                        </form>
                    @endif
                </section>
            </div>

            <section class="cutover-subpanel cutover-checklist">
                <h3>Validation checklist</h3>
                <ul>
                    <li><span>Debit / credit totals</span><strong @class(['validation-result', 'is-good' => $openingBalanced, 'is-error' => ! $openingBalanced])>{{ $openingBalanced ? 'Balanced · ₱'.number_format($debitTotalCents / 100, 2).' each' : 'Not balanced — correct the opening lines in Step 2' }}</strong></li>
                    <li><span>Supplier / AP schedule</span><strong @class(['validation-result', 'is-good' => $preflight['ap_matches'] === true, 'is-error' => $preflight['ap_matches'] === false, 'is-pending' => $preflight['ap_matches'] === null])>@if ($preflight['ap_matches'] === null) Save the opening journal draft first @elseif ($preflight['ap_matches']) Matched · ₱{{ number_format($preflight['ap_book_cents'] / 100, 2) }} @else Mismatch · book ₱{{ number_format($preflight['ap_book_cents'] / 100, 2) }}, supplier schedule ₱{{ number_format($preflight['ap_schedule_cents'] / 100, 2) }} — correct AP opening or invoice drafts @endif</strong></li>
                    <li><span>Inventory valuation</span><strong @class(['validation-result', 'is-good' => $preflight['inventory_matches'] === true, 'is-error' => $preflight['inventory_matches'] === false, 'is-pending' => $preflight['inventory_matches'] === null])>@if ($preflight['inventory_matches'] === null) {{ $readiness['inventory'] }} @elseif ($preflight['inventory_matches']) Matched · ₱{{ number_format($preflight['inventory_schedule_cents'] / 100, 2) }} @else Mismatch · book ₱{{ number_format($preflight['inventory_book_cents'] / 100, 2) }}, item schedule ₱{{ number_format($preflight['inventory_schedule_cents'] / 100, 2) }} — correct the item values or Inventory opening line @endif</strong></li>
                    <li><span>Duplicate posting check</span><strong @class(['validation-result', 'is-good' => $duplicatePostingClear, 'is-error' => ! $duplicatePostingClear])>{{ $duplicatePostingClear ? 'No duplicate opening journal' : 'Duplicate opening journals found — contact an accounting administrator' }}</strong></li>
                </ul>
            </section>

            @if ($interveningSources->isNotEmpty())
                <section class="cutover-subpanel"><h3>Existing post-cutover activity</h3><p>These records are not recreated; include an eligible missing accounting link only once.</p><div class="cutover-table-wrap"><table><thead><tr><th>Date</th><th>Source</th><th>Reference</th><th>Status</th><th>Action</th></tr></thead><tbody>
                    @foreach ($interveningSources as $source)<tr><td>{{ $source['date'] }}</td><td>{{ $source['description'] }}</td><td>{{ $source['reference'] }}</td><td>{{ $source['reason'] ?? $source['status'] }}</td><td>@if ($source['status'] === 'missing' && in_array($source['type'], ['pos_sale', 'stock_movement', 'supplier_purchase', 'cash_disbursement'], true)) @can('accounting.reconcile-sources') @can('accounting.post-reconciled-sources')<button type="button" wire:click="includeInterveningSource('{{ $source['type'] }}', {{ $source['id'] }})">Include existing source</button>@endcan @endcan @else — @endif</td></tr>@endforeach
                </tbody></table></div></section>
            @endif
            <div class="cutover-actions"><button type="button" class="button-secondary" wire:click="previousStep">← Back</button><button type="button" wire:click="nextStep">Continue to Approval →</button></div>
        </section>
    @else
        <section class="cutover-panel" aria-labelledby="approve-post-heading">
            <h2 id="approve-post-heading">Step 4. Approve and Post</h2>
            <p>Final review of the saved opening balance draft and supporting schedules. Approval rechecks all required rules and posts atomically.</p>
            @if ($journal?->status === 'posted')
                <div class="cutover-success"><h3>Opening balances posted</h3><p>Approved cutover: {{ $journal->accounting_date->format('F j, Y') }} · {{ $journal->reference }} · approved by {{ $journal->approver?->username ?? 'Unavailable' }}</p><p>Debit total: ₱{{ number_format($journal->lines->sum('debit_cents') / 100, 2) }} · Credit total: ₱{{ number_format($journal->lines->sum('credit_cents') / 100, 2) }}</p><p>Supplier schedule: {{ $readiness['supplier'] }} · Inventory schedule: {{ $readiness['inventory'] }} · YTD: {{ $readiness['ytd'] }}</p><p>Production: {{ $readiness['production'] }}</p><p>Posted journals are immutable. Corrections require the authorized correction or reversal workflow.</p></div>
                @if ($inventoryValuation?->status === 'approved')
                    <section class="cutover-subpanel"><h3>Posted opening inventory schedule</h3><div class="cutover-table-wrap"><table><thead><tr><th>Item</th><th>Quantity</th><th>Unit cost (PHP)</th><th>Value (PHP)</th></tr></thead><tbody>@foreach ($inventoryValuation->lines as $line)<tr><td>{{ $line->inventory->code }} — {{ $line->inventory->name }}</td><td>{{ $line->quantity }}</td><td>{{ $line->quantity > 0 ? number_format(($line->carrying_value_cents / 100) / (float) $line->quantity, 4) : '0.0000' }}</td><td>{{ number_format($line->carrying_value_cents / 100, 2) }}</td></tr>@endforeach</tbody></table></div></section>
                @endif
                @if ($ytdSummary?->status === 'approved')<p>Approved through {{ $ytdSummary->through_date->format('F j, Y') }} — pre-cutover YTD summary.</p>@endif
            @else
                <div class="cutover-approval-grid">
                    <section class="cutover-subpanel"><h3>Opening Balance Summary</h3><dl><dt>Cutover date</dt><dd>{{ $cutoverDate }}</dd><dt>Accounts in draft</dt><dd>{{ count($lines) }}</dd><dt>Total debit</dt><dd>₱{{ $debitTotalCents === null ? '—' : number_format($debitTotalCents / 100, 2) }}</dd><dt>Total credit</dt><dd>₱{{ $creditTotalCents === null ? '—' : number_format($creditTotalCents / 100, 2) }}</dd><dt>Inventory items</dt><dd>{{ $scan['inventory_items'] }}</dd><dt>Suppliers found</dt><dd>{{ $scan['suppliers'] }}</dd><dt>Draft status</dt><dd>{{ $journal?->status ?? 'Not saved' }}</dd></dl></section>
                    <section class="cutover-subpanel"><h3>Authorized review</h3><p>Only a user with opening approval permission can post this cutover. Saving progress does not post balances.</p>
                        @if ($journal?->status === 'draft')<p>Prepared by {{ $journal->preparer?->username ?? 'staff member' }}. Draft saved; no balances have been posted.</p>@else<p>Save opening balances in Step 2 before requesting approval.</p>@endif
                        @can('accounting.approve-opening-books')
                            @if ($journal?->status === 'draft')<button type="button" wire:click="approveCutover" wire:loading.attr="disabled">✓ Approve and Post</button>@else<button type="button" disabled>Save a complete opening draft first</button>@endif
                        @endcan
                    </section>
                </div>
            @endif

            @if ($journal?->status === 'posted' && ! $readiness['production_activated'])
                <section class="cutover-subpanel"><h3>Production activation</h3><p>{{ $readiness['production'] }}</p><p>Activation remains a separate, higher-permission action after all operational readiness checks pass.</p>@can('accounting.activate-books') @if ($readiness['production_ready'])<button type="button" wire:click="activateProduction">Activate production accounting</button>@endif @endcan</section>
            @endif
            <div class="cutover-actions"><button type="button" class="button-secondary" wire:click="previousStep">← Back</button></div>
        </section>
    @endif
</section>
