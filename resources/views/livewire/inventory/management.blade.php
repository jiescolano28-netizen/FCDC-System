<section class="inventory-page">
    <div class="columns" style="justify-content:space-between; margin-bottom:18px;">
        <div>
            <h1>Inventory management</h1>
            <p class="muted">Persisted stock records. Unit cost and customer selling price are separate values.</p>
        </div>
        <span>{{ $items->count() }} matching item(s)</span>
    </div>

    @if (auth()->user()->can('inventory.create') || auth()->user()->can('inventory.update'))
        <div class="panel">
            <h2>{{ $editingId ? 'Edit inventory item' : 'Add inventory item' }}</h2>
            <form wire:submit="save">
                <div class="fields">
                    <div><label for="name">Material name</label><input id="name" wire:model="name">@error('name') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="category">Category</label><input id="category" wire:model="category">@error('category') <span class="error">{{ $message }}</span> @enderror</div>
                    @if (! $editingId)
                        <div><label for="qty">Opening quantity</label><input id="qty" type="number" min="0" step="0.01" wire:model="qty">@error('qty') <span class="error">{{ $message }}</span> @enderror</div>
                    @endif
                    <div><label for="unit">Unit</label><input id="unit" wire:model="unit">@error('unit') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="unitCost">Unit cost{{ $valuedPostingEnabled ? ' · from valued stock schedule' : '' }}</label><input id="unitCost" type="number" min="0" step="0.01" wire:model="unitCost" @readonly($valuedPostingEnabled)>@error('unitCost') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="sellingPrice">Selling price (optional)</label><input id="sellingPrice" type="number" min="0" step="0.01" wire:model="sellingPrice">@error('sellingPrice') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="reorderLevel">Reorder level</label><input id="reorderLevel" type="number" min="0" step="0.01" wire:model="reorderLevel">@error('reorderLevel') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="description">Description (optional)</label><textarea id="description" wire:model="description"></textarea>@error('description') <span class="error">{{ $message }}</span> @enderror</div>
                    @if ($editingId)
                        <div><label for="status">Lifecycle status</label><select id="status" wire:model="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>@error('status') <span class="error">{{ $message }}</span> @enderror</div>
                    @endif
                    <div><label for="image">Product image (optional)</label><input id="image" type="file" accept="image/jpeg,image/png,image/webp" wire:model="image">@error('image') <span class="error">{{ $message }}</span> @enderror</div>
                </div>
                <div class="actions" style="margin-top:14px;">
                    @if ($editingId)
                        @can('inventory.update')
                            <button class="primary" type="submit">Save changes</button>
                            <button class="secondary" type="button" wire:click="resetForm">Cancel</button>
                        @endcan
                    @else
                        @can('inventory.create')
                            <button class="primary" type="submit">Add item</button>
                        @endcan
                    @endif
                </div>
            </form>
        </div>
    @endif

    @can('inventory.movements.record')
        <div class="panel">
            <h2>Receive stock</h2>
            <form wire:submit="recordStockIn">
                <div class="fields">
                    <div><label for="stockItemId">Inventory item</label><select id="stockItemId" wire:model="stockItemId"><option value="">Select active item</option>@foreach ($activeItems as $activeItem)<option value="{{ $activeItem->id }}">{{ $activeItem->code }} · {{ $activeItem->name }}</option>@endforeach</select>@error('stockItemId') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="stockQuantity">Quantity received</label><input id="stockQuantity" type="number" min="0.01" step="0.01" wire:model="stockQuantity">@error('stockQuantity') <span class="error">{{ $message }}</span> @enderror</div>
                    @if ($valuedPostingEnabled)
                        <div><label for="stockUnitValue">Approved cost per unit (PHP)</label><input id="stockUnitValue" type="number" min="0" step="0.01" wire:model="stockUnitValue">@error('stockUnitValue') <span class="error">{{ $message }}</span> @enderror</div>
                    @endif
                    <div><label for="stockReasonCategory">Reason category</label><select id="stockReasonCategory" wire:model="stockReasonCategory"><option value="">Select reason</option><option value="purchase_receipt">Purchase / receipt</option><option value="return">Return</option><option value="other">Other</option></select>@error('stockReasonCategory') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="stockEffectiveDate">Effective date</label><input id="stockEffectiveDate" type="date" wire:model="stockEffectiveDate">@error('stockEffectiveDate') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="stockReference">Source reference {{ $valuedPostingEnabled ? '(required)' : '(optional)' }}</label><input id="stockReference" wire:model="stockReference">@error('stockReference') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="stockNotes">Notes (optional)</label><textarea id="stockNotes" wire:model="stockNotes"></textarea>@error('stockNotes') <span class="error">{{ $message }}</span> @enderror</div>
                </div>
                <button class="primary" type="submit" style="margin-top:14px;">Post stock-in</button>
            </form>
        </div>
    @endcan

    @can('inventory.movements.record')
        <div class="panel">
            <h2>Issue stock</h2>
            <form wire:submit="recordStockOut">
                <div class="fields">
                    <div><label for="issueItemId">Inventory item</label><select id="issueItemId" wire:model="issueItemId"><option value="">Select active item</option>@foreach ($activeItems as $activeItem)<option value="{{ $activeItem->id }}">{{ $activeItem->code }} · {{ $activeItem->name }}</option>@endforeach</select>@error('issueItemId') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="issueQuantity">Quantity issued</label><input id="issueQuantity" type="number" min="0.01" step="0.01" wire:model="issueQuantity">@error('issueQuantity') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="issueReasonCategory">Reason category</label><select id="issueReasonCategory" wire:model="issueReasonCategory"><option value="">Select reason</option>@if (! $valuedPostingEnabled)<option value="purchase_receipt">Purchase / receipt</option><option value="sale">Sale</option><option value="return">Return</option>@endif<option value="project_use">Project use</option><option value="damage_loss">Damage / loss</option><option value="count_correction">Count correction</option><option value="other">Other</option></select>@error('issueReasonCategory') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="issueEffectiveDate">Effective date</label><input id="issueEffectiveDate" type="date" wire:model="issueEffectiveDate">@error('issueEffectiveDate') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="issueReference">Source reference {{ $valuedPostingEnabled ? '(required)' : '(optional)' }}</label><input id="issueReference" wire:model="issueReference">@error('issueReference') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="issueNotes">Notes (optional)</label><textarea id="issueNotes" wire:model="issueNotes"></textarea>@error('issueNotes') <span class="error">{{ $message }}</span> @enderror</div>
                </div>
                <button class="primary" type="submit" style="margin-top:14px;">Post stock-out</button>
            </form>
        </div>

        <div class="panel">
            <h2>Adjust to physical count</h2>
            <form wire:submit="recordStockAdjustment">
                <div class="fields">
                    <div><label for="adjustmentItemId">Inventory item</label><select id="adjustmentItemId" wire:model="adjustmentItemId"><option value="">Select active item</option>@foreach ($activeItems as $activeItem)<option value="{{ $activeItem->id }}">{{ $activeItem->code }} · {{ $activeItem->name }}</option>@endforeach</select>@error('adjustmentItemId') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="countedQuantity">Counted on-hand quantity</label><input id="countedQuantity" type="number" min="0" step="0.01" wire:model="countedQuantity">@error('countedQuantity') <span class="error">{{ $message }}</span> @enderror</div>
                    @if ($valuedPostingEnabled)
                        <div><label for="adjustmentUnitValue">Approved unit value for increases (PHP)</label><input id="adjustmentUnitValue" type="number" min="0" step="0.01" wire:model="adjustmentUnitValue">@error('adjustmentUnitValue') <span class="error">{{ $message }}</span> @enderror</div>
                    @endif
                    <div><label for="adjustmentReasonCategory">Reason category</label><select id="adjustmentReasonCategory" wire:model="adjustmentReasonCategory"><option value="">Select reason</option>@if (! $valuedPostingEnabled)<option value="purchase_receipt">Purchase / receipt</option><option value="sale">Sale</option><option value="return">Return</option>@endif<option value="project_use">Project use</option><option value="damage_loss">Damage / loss</option><option value="count_correction">Count correction</option><option value="other">Other</option></select>@error('adjustmentReasonCategory') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="adjustmentEffectiveDate">Effective date</label><input id="adjustmentEffectiveDate" type="date" wire:model="adjustmentEffectiveDate">@error('adjustmentEffectiveDate') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="adjustmentReference">Source reference {{ $valuedPostingEnabled ? '(required)' : '(optional)' }}</label><input id="adjustmentReference" wire:model="adjustmentReference">@error('adjustmentReference') <span class="error">{{ $message }}</span> @enderror</div>
                    <div><label for="adjustmentNotes">Notes (optional)</label><textarea id="adjustmentNotes" wire:model="adjustmentNotes"></textarea>@error('adjustmentNotes') <span class="error">{{ $message }}</span> @enderror</div>
                </div>
                <button class="primary" type="submit" style="margin-top:14px;">Post count adjustment</button>
            </form>
        </div>
    @endcan

    <div class="toolbar panel">
        <div style="flex:1; min-width:220px;">
            <label for="search">Search inventory</label>
            <input id="search" type="search" placeholder="Code, name or category" wire:model.live.debounce.300ms="search">
        </div>
        <div style="min-width:190px;">
            <label for="categoryFilter">Category</label>
            <select id="categoryFilter" wire:model.live="categoryFilter">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}">{{ $category }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="panel table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Code / material</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Unit cost</th>
                    <th>Selling price</th>
                    <th>Lifecycle</th>
                    <th>Stock status</th>
                    <th>Sale status</th>
                    <th>History</th>
                    @if (auth()->user()->can('inventory.update') || auth()->user()->can('inventory.delete'))
                        <th>Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr wire:key="inventory-{{ $item->id }}">
                        <td><strong>{{ $item->code }}</strong><br>{{ $item->name }}</td>
                        <td>{{ $item->category }}</td>
                        <td>{{ $item->qty }} {{ $item->unit }}</td>
                        <td>{{ number_format((float) $item->unit_cost, 2) }}</td>
                        <td>{{ $item->selling_price === null ? '—' : number_format((float) $item->selling_price, 2) }}</td>
                        <td><span class="status {{ $item->status === 'active' ? '' : 'empty' }}">{{ ucfirst($item->status) }}</span></td>
                        <td>
                            @if ((float) $item->qty <= 0)
                                <span class="status empty">Out of stock</span>
                            @elseif ((float) $item->qty <= (float) $item->reorder_level)
                                <span class="status warning">Reorder</span>
                            @else
                                <span class="status">In stock</span>
                            @endif
                        </td>
                        <td>
                            @if ($item->status !== 'active')
                                <span class="status empty">Inactive</span>
                            @elseif ($item->selling_price === null)
                                <span class="status warning">Not priced · unavailable</span>
                            @else
                                <span class="status">Sale price set</span>
                            @endif
                        </td>
                        <td><button class="secondary" type="button" wire:click="showHistory({{ $item->id }})">{{ $historyItemId === $item->id ? 'Hide history' : 'View history' }}</button></td>
                        @if (auth()->user()->can('inventory.update') || auth()->user()->can('inventory.delete'))
                            <td>
                                <div class="actions">
                                    @can('inventory.update')
                                        <button class="secondary" type="button" wire:click="edit({{ $item->id }})">Edit</button>
                                    @endcan
                                    @can('inventory.delete')
                                        @if ($item->status === 'active')
                                            <button class="danger" type="button" wire:click="deactivate({{ $item->id }})" wire:confirm="Deactivate {{ $item->name }}?">Deactivate</button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        @endif
                    </tr>
                    @if ($historyItemId === $item->id)
                        <tr wire:key="inventory-history-{{ $item->id }}">
                            <td colspan="{{ auth()->user()->can('inventory.update') || auth()->user()->can('inventory.delete') ? 10 : 9 }}">
                                <strong>Stock history · {{ $item->code }}</strong>
                                @forelse ($item->stockMovements as $movement)
                                    <div>
                                        {{ $movement->type === 'adjustment' ? 'Stock adjustment' : ucfirst(str_replace('_', ' ', $movement->type)) }} · {{ number_format((float) $movement->quantity, 2) }} {{ $item->unit }} · {{ ucfirst(str_replace('_', ' ', $movement->reason_category)) }} · effective {{ $movement->effective_date->format('Y-m-d') }} · posted {{ $movement->posted_at->format('Y-m-d H:i') }} · by {{ $movement->poster?->username ?? 'System migration' }}@if ($movement->reference) · ref {{ $movement->reference }}@endif @if ($movement->value_cents !== null) · value PHP {{ number_format(abs($movement->value_cents) / 100, 2) }} · carrying PHP {{ number_format($movement->carrying_value_after_cents / 100, 2) }} · journal {{ $movement->accountingJournal?->reference }}@endif @if ($movement->demolition_project_id) · project #{{ $movement->demolition_project_id }} · recovery #{{ $movement->recovered_material_id }}@endif @if ($movement->notes) · {{ $movement->notes }}@endif
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
                    <tr><td colspan="{{ auth()->user()->can('inventory.update') || auth()->user()->can('inventory.delete') ? 10 : 9 }}" class="muted">No inventory items match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

