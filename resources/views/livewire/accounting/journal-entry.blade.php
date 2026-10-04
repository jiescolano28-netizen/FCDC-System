<section class="journal-entry-page" aria-labelledby="journal-entry-heading">
    <header class="page-heading journal-entry-heading">
        <div>
            <h1 id="journal-entry-heading">Journal Entry</h1>
            <p class="page-subtitle">Temporary demonstration entry only. Nothing is posted to accounting records.</p>
        </div>
    </header>

    <p class="temporary-notice" role="note">Demonstration only: sample account references and journal entries are not approved production accounting data. Saving adds a temporary session example; it does not create a posted journal, ledger entry, payable, disbursement, or report balance.</p>

    @if (session()->has('journal-entry-saved'))
        <p class="journal-entry-feedback" role="status">{{ session('journal-entry-saved') }}</p>
    @endif

    <form class="journal-entry-card" wire:submit="save">
        <h2>Demonstration journal details</h2>
        <div class="journal-entry-header-fields">
            <label>
                <span>Date</span>
                <input type="date" wire:model.live="date">
                @error('date') <small class="settings-error">{{ $message }}</small> @enderror
            </label>
            <label>
                <span>Reference</span>
                <input type="text" wire:model.live="reference" maxlength="100">
                @error('reference') <small class="settings-error">{{ $message }}</small> @enderror
            </label>
            <label>
                <span>Source</span>
                <input type="text" wire:model.live="source" maxlength="80">
                @error('source') <small class="settings-error">{{ $message }}</small> @enderror
            </label>
            <label class="journal-entry-description">
                <span>Description</span>
                <input type="text" wire:model.live="description" maxlength="255">
                @error('description') <small class="settings-error">{{ $message }}</small> @enderror
            </label>
        </div>

        <div class="journal-lines-heading">
            <div>
                <h3>Entry lines</h3>
                <p>Reference accounts are illustrative. Debit and credit values are demonstration amounts only.</p>
            </div>
            <button class="journal-secondary-button" type="button" wire:click="addLine" @disabled(count($lines) >= 10)>Add line</button>
        </div>

        <div class="journal-line-list">
            @foreach ($lines as $index => $line)
                <fieldset class="journal-line" wire:key="journal-line-{{ $index }}">
                    <legend>Line {{ $index + 1 }}</legend>
                    <label class="journal-line-account">
                        <span>Account</span>
                        <select wire:model.live="lines.{{ $index }}.accountCode">
                            <option value="">Select a sample account</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account['code'] }}">{{ $account['code'] }} · {{ $account['name'] }}</option>
                            @endforeach
                        </select>
                        @error("lines.{$index}.accountCode") <small class="settings-error">{{ $message }}</small> @enderror
                    </label>
                    <label class="journal-line-description">
                        <span>Line description</span>
                        <input type="text" wire:model.live="lines.{{ $index }}.description" maxlength="255">
                        @error("lines.{$index}.description") <small class="settings-error">{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Debit</span>
                        <input type="number" min="0" step="0.01" wire:model.live="lines.{{ $index }}.debit">
                        @error("lines.{$index}.debit") <small class="settings-error">{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Credit</span>
                        <input type="number" min="0" step="0.01" wire:model.live="lines.{{ $index }}.credit">
                        @error("lines.{$index}.credit") <small class="settings-error">{{ $message }}</small> @enderror
                    </label>
                    <button class="journal-remove-button" type="button" wire:click="removeLine({{ $index }})" @disabled(count($lines) <= 2)>Remove line</button>
                    @error("lines.{$index}") <small class="settings-error journal-line-error">{{ $message }}</small> @enderror
                </fieldset>
            @endforeach
        </div>
        @error('lines') <p class="settings-error journal-total-error">{{ $message }}</p> @enderror

        <div class="journal-entry-totals" aria-live="polite">
            <div><span>Total Debits</span><strong>{{ number_format($debitTotal, 2) }}</strong></div>
            <div><span>Total Credits</span><strong>{{ number_format($creditTotal, 2) }}</strong></div>
        </div>

        <footer class="journal-entry-actions">
            <button class="journal-secondary-button" type="button" wire:click="clear">Clear</button>
            <button class="journal-save-button" type="submit">Save demonstration entry</button>
        </footer>
    </form>

    <section class="journal-history-card" aria-labelledby="journal-history-heading">
        <header>
            <div>
                <h2 id="journal-history-heading">Demonstration history</h2>
                <p>Saved in this signed-in session only; history is not posted accounting activity.</p>
            </div>
        </header>
        @forelse ($history as $entry)
            <article class="journal-history-entry" wire:key="journal-history-{{ $entry['reference'] }}-{{ $loop->index }}">
                <div class="journal-history-summary">
                    <div>
                        <strong>{{ $entry['reference'] }}</strong>
                        <span>{{ $entry['date'] }} · {{ $entry['source'] }} · {{ $entry['description'] }}</span>
                    </div>
                    <span class="journal-demo-status">Demonstration · Not posted</span>
                </div>
                <div class="journal-history-lines">
                    @foreach ($entry['lines'] as $line)
                        @php($account = collect($accounts)->firstWhere('code', $line['accountCode']))
                        <div>
                            <span>{{ $line['accountCode'] }} · {{ $account['name'] ?? 'Sample account' }}@if ($line['description']) — {{ $line['description'] }}@endif</span>
                            <span>Debit {{ number_format((float) ($line['debit'] ?: 0), 2) }} · Credit {{ number_format((float) ($line['credit'] ?: 0), 2) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="journal-history-totals">
                    <span>Debits {{ number_format($entry['debitTotal'], 2) }}</span>
                    <span>Credits {{ number_format($entry['creditTotal'], 2) }}</span>
                </div>
            </article>
        @empty
            <p class="journal-history-empty">No demonstration entries saved in this session.</p>
        @endforelse
    </section>
</section>
