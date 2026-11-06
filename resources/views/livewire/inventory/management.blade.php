<section class="inventory-page">
    <div class="columns" style="justify-content:space-between; margin-bottom:18px;">
        <div>
            <h1>Inventory management</h1>
            <p class="muted">Persisted stock records. Quantity is the on-hand stock count; Unit is the material label (e.g., pc, bag, kg). Inventory value is calculated from on-hand quantity × unit cost.</p>
        </div>
        <div class="actions">
            <span>{{ $items->count() }} matching item(s)</span>
            <a class="primary-link" href="{{ route('inventory.batch-categories') }}">Batch Categories</a>
            @can('inventory.create')
                <a class="primary-link" href="{{ route('inventory.management.create') }}">Add Inventory Item</a>
            @endcan
        </div>
    </div>

    <div class="toolbar panel inventory-toolbar">
        <div class="inventory-search">
            <label for="search">Search inventory</label>
            <input id="search" type="search" placeholder="Code, name, category, size or specification" wire:model.live.debounce.300ms="search">
        </div>
        <button
            class="secondary inventory-filter-toggle"
            type="button"
            wire:click="toggleAdvancedFilters"
            aria-expanded="{{ $advancedFiltersOpen ? 'true' : 'false' }}"
            aria-controls="inventory-advanced-filters"
        >
            Advanced filters
            @if ($categoryFilter !== '' || $batchFilter !== '')
                <span class="inventory-filter-count">{{ (int) ($categoryFilter !== '') + (int) ($batchFilter !== '') }} active</span>
            @endif
        </button>
        @if ($advancedFiltersOpen)
            <div class="inventory-advanced-filters" id="inventory-advanced-filters">
                <div>
                    <label for="categoryFilter">Category</label>
                    <select id="categoryFilter" wire:model.live="categoryFilter">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="batchFilter">Batch</label>
                    <select id="batchFilter" wire:model.live="batchFilter">
                        <option value="">All batches</option>
                        @foreach ($batchCategories as $batchCategory)
                            <option value="{{ $batchCategory->id }}">{{ $batchCategory->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif
        @if ($isAdministrator)
            <label class="inventory-deleted-toggle">
                <input type="checkbox" wire:model.live="showDeleted">
                Show deleted items
            </label>
        @endif
    </div>

    <div class="panel table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Reference Code</th>
                    <th>Category</th>
                    <th>Size / Specification</th>
                    <th>Batch</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Unit Cost Price</th>
                    <th>Selling Price</th>
                    <th>Inventory Value</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr wire:key="inventory-{{ $item->id }}">
                        <td>
                            @if ($item->trashed())
                                <strong>{{ $item->code }}</strong><br>{{ $item->name }}
                                <br><span class="status empty">Deleted</span>
                            @else
                                <a href="{{ route('inventory.management.show', $item->id) }}"><strong>{{ $item->code }}</strong></a><br>{{ $item->name }}
                                @if ($item->status !== 'active')
                                    <br><span class="status empty">Inactive</span>
                                @endif
                            @endif
                        </td>
                        <td>{{ $item->category }}</td>
                        <td>{{ filled($item->size_specification) ? $item->size_specification : '—' }}</td>
                        <td>{{ $item->batchCategory?->name ?? '—' }}</td>
                        <td><strong>{{ number_format((float) $item->qty, 0) }}</strong></td>
                        <td>{{ filled($item->unit) ? $item->unit : '—' }}</td>
                        <td>{{ number_format((float) $item->unit_cost, 2) }}</td>
                        <td>
                            @if ($item->selling_price === null)
                                <span class="muted">Not priced</span>
                            @else
                                {{ number_format((float) $item->selling_price, 2) }}
                            @endif
                        </td>
                        <td><strong>{{ number_format((float) $item->qty * (float) $item->unit_cost, 2) }}</strong></td>
                        <td>
                            <div class="actions inventory-row-actions">
                                @if ($item->trashed())
                                    <button class="secondary" type="button" aria-label="{{ $historyItemId === $item->id ? 'Hide history' : 'View history' }} for {{ $item->name }}" title="{{ $historyItemId === $item->id ? 'Hide history' : 'View history' }}" wire:click="showHistory({{ $item->id }})"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 2.64-6.36L3 8m0-5v5h5m4-1v5l3 2"/></svg></button>
                                    @if ($isAdministrator)
                                        <button class="secondary" type="button" aria-label="Restore {{ $item->name }}" title="Restore" wire:click="restore({{ $item->id }})" wire:confirm="Restore {{ $item->name }} to the inventory list?"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 2.64-6.36L3 8m0-5v5h5m4-1v5l3 2"/></svg></button>
                                    @endif
                                @else
                                    <button class="secondary" type="button" aria-label="{{ $historyItemId === $item->id ? 'Hide history' : 'View history' }} for {{ $item->name }}" title="{{ $historyItemId === $item->id ? 'Hide history' : 'View history' }}" wire:click="showHistory({{ $item->id }})"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 2.64-6.36L3 8m0-5v5h5m4-1v5l3 2"/></svg></button>
                                    @can('inventory.update')
                                        <a class="secondary" href="{{ route('inventory.management.edit', $item->id) }}" aria-label="Edit {{ $item->name }}" title="Edit"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m4 16.5-.8 4.3 4.3-.8L20 7.5 16.5 4 4 16.5Zm10.8-10.8 3.5 3.5"/></svg></a>
                                    @endcan
                                    @if ($isAdministrator)
                                        @if ($item->status === 'active')
                                            <button class="danger" type="button" aria-label="Deactivate {{ $item->name }}" title="Deactivate" wire:click="deactivate({{ $item->id }})" wire:confirm="Deactivate {{ $item->name }}? You can reactivate it later."><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg></button>
                                        @else
                                            <button class="secondary" type="button" aria-label="Reactivate {{ $item->name }}" title="Reactivate" wire:click="reactivate({{ $item->id }})" wire:confirm="Reactivate {{ $item->name }}?"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg></button>
                                        @endif
                                        <button class="danger" type="button" aria-label="Delete {{ $item->name }}" title="Delete" wire:click="delete({{ $item->id }})" wire:confirm="Delete {{ $item->name }}? It will be removed from the inventory list; its stock history is kept and it can be restored."><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 7h16m-10 4v7m4-7v7M6 7l1 14h10l1-14M9 7V4h6v3"/></svg></button>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if ($historyItemId === $item->id)
                        <tr wire:key="inventory-history-{{ $item->id }}">
                            <td colspan="10">
                                <strong>Stock history · {{ $item->code }}</strong>
                                @forelse ($item->stockMovements as $movement)
                                    <div>
                                        {{ $movement->type === 'adjustment' ? 'Stock adjustment' : ucfirst(str_replace('_', ' ', $movement->type)) }} · {{ number_format((float) $movement->quantity, 0) }} · Unit: {{ filled($item->unit) ? $item->unit : '—' }} · {{ ucfirst(str_replace('_', ' ', $movement->reason_category)) }} · effective {{ $movement->effective_date->format('Y-m-d') }} · posted {{ \App\Support\PhilippineTime::format($movement->posted_at, 'Y-m-d H:i') }} PHT · by {{ $movement->poster?->username ?? 'System migration' }}@if ($movement->reference) · ref {{ $movement->reference }}@endif @if ($movement->value_cents !== null) · value PHP {{ number_format(abs($movement->value_cents) / 100, 2) }} · carrying PHP {{ number_format($movement->carrying_value_after_cents / 100, 2) }} · journal {{ $movement->accountingJournal?->reference }}@endif @if ($movement->demolition_project_id) · project #{{ $movement->demolition_project_id }} · recovery #{{ $movement->recovered_material_id }}@endif @if ($movement->notes) · {{ $movement->notes }}@endif
                                        @if ($movement->correction_of_movement_id) · corrects movement #{{ $movement->correction_of_movement_id }} @endif
                                        @if ($movement->correction_reason) · reason: {{ $movement->correction_reason }} @endif
                                        @if ($movement->reversal)
                                            · Reversed by movement #{{ $movement->reversal->id }}
                                        @elseif ($movement->type !== 'reversal')
                                            @can('inventory.movements.record')
                                                @if (! $movement->recovered_material_id)
                                                    <button class="danger" type="button" wire:click="reverseStockMovement({{ $movement->id }})" wire:confirm="Reverse this movement? The original stays in history. Post the corrected stock transaction separately.">Reverse movement</button>
                                                @endif
                                            @endcan
                                        @endif
                                        @if ($movement->type !== 'valuation_correction' && $movement->correction_of_movement_id === null && $movement->quantity > 0 && $movement->value_cents > 0)
                                            @can('inventory.movements.record')
                                                @can('inventory.valuation.approve')
                                                    <button class="secondary" type="button" wire:click="beginValuationCorrection({{ $movement->id }})">Correct value</button>
                                                @endcan
                                            @endcan
                                        @endif
                                        @if ($correctionMovementId === $movement->id)
                                            <form wire:submit="saveValuationCorrection" class="panel" style="margin-top:10px;">
                                                <strong>Forward-only value correction for movement #{{ $movement->id }}</strong>
                                                <p class="muted">Quantity remains unchanged. Enter signed remaining and consumed cost changes; both must move in the same direction.</p>
                                                <label>Correction reason<input wire:model="correctionReason"></label>
                                                @error('correctionReason') <span class="error">{{ $message }}</span> @enderror
                                                <label>Remaining Inventory value change (PHP)<input type="number" step="0.01" wire:model="remainingValueDelta"></label>
                                                @error('remainingValueDelta') <span class="error">{{ $message }}</span> @enderror
                                                <label>Consumed value change (PHP)<input type="number" step="0.01" wire:model="consumedValueDelta"></label>
                                                @error('consumedValueDelta') <span class="error">{{ $message }}</span> @enderror
                                                <label>Consumed cost account<select wire:model="consumedExpenseAccountId"><option value="">Select when consumed value changes</option>@foreach ($approvedExpenseAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>@endforeach</select></label>
                                                @error('consumedExpenseAccountId') <span class="error">{{ $message }}</span> @enderror
                                                @error('movement') <span class="error">{{ $message }}</span> @enderror
                                                @error('valuation') <span class="error">{{ $message }}</span> @enderror
                                                @error('accounting') <span class="error">{{ $message }}</span> @enderror
                                                @error('remainingValue') <span class="error">{{ $message }}</span> @enderror
                                                <div class="actions"><button class="primary" type="submit">Post value correction</button><button class="secondary" type="button" wire:click="cancelValuationCorrection">Cancel</button></div>
                                            </form>
                                        @endif
                                @empty
                                    <p class="muted">No stock movements recorded.</p>
                                @endforelse
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="10" class="muted">No inventory items match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

