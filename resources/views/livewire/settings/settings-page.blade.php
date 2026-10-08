<section class="settings-page" aria-labelledby="settings-heading">
    <header class="page-heading">
        <div>
            <h1 id="settings-heading">Settings</h1>
            <p class="page-subtitle">Company details shown on receipts and reports.</p>
        </div>
    </header>

    <p class="temporary-notice" role="status">Temporary demo values — these changes are available only in your signed-in session and are not persisted company settings.</p>

    <div class="settings-card">
        <div class="settings-grid">
            <div class="settings-field">
                <label for="company-name">Company name</label>
                <input id="company-name" type="text" wire:model.live.debounce.300ms="companyName" autocomplete="organization" @disabled(auth()->user()->cannot('settings.update'))>
                @error('companyName') <span class="settings-error">{{ $message }}</span> @enderror
            </div>

            <div class="settings-field">
                <label for="company-address">Address</label>
                <input id="company-address" type="text" wire:model.live.debounce.300ms="address" autocomplete="street-address" @disabled(auth()->user()->cannot('settings.update'))>
                @error('address') <span class="settings-error">{{ $message }}</span> @enderror
            </div>

            <div class="settings-field">
                <label for="company-phone">Phone</label>
                <input id="company-phone" type="tel" wire:model.live.debounce.300ms="phone" autocomplete="tel" @disabled(auth()->user()->cannot('settings.update'))>
                @error('phone') <span class="settings-error">{{ $message }}</span> @enderror
            </div>

            <div class="settings-field">
                <label for="tax-rate">Tax rate (%)</label>
                <input id="tax-rate" type="number" min="0" max="100" step="0.01" wire:model.live.debounce.300ms="taxRate" @disabled(auth()->user()->cannot('settings.update'))>
                @error('taxRate') <span class="settings-error">{{ $message }}</span> @enderror
            </div>

            <div class="settings-field">
                <label for="currency">Currency</label>
                <select id="currency" wire:model.live="currency" @disabled(auth()->user()->cannot('settings.update'))>
                    <option value="USD">USD</option>
                    <option value="CAD">CAD</option>
                    <option value="EUR">EUR</option>
                    <option value="PHP">PHP</option>
                </select>
                @error('currency') <span class="settings-error">{{ $message }}</span> @enderror
            </div>
        </div>
    </div>

    <section class="team-card" aria-labelledby="team-heading">
        <h2 id="team-heading">Team members</h2>
        <ul class="team-list">
            <li><span>J. Alvarez</span><span class="team-role">Site Manager</span></li>
            <li><span>M. Chen</span><span class="team-role">Warehouse Lead</span></li>
            <li><span>R. Santos</span><span class="team-role">POS Cashier</span></li>
        </ul>
    </section>
</section>
