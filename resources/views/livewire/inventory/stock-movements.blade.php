@can('inventory.movements.record')
    <p class="muted">Inventory item: <strong>{{ $movementItem->code }} · {{ $movementItem->name }}</strong> · Current on hand: <strong>{{ number_format((float) $movementItem->qty, 0) }}</strong> · Unit: <strong>{{ filled($movementItem->unit) ? $movementItem->unit : '—' }}</strong></p>
    <div class="panel">
        <h2>Receive stock</h2>
        <p class="muted">Enter the amount received. It will be added to the current on-hand quantity.</p>
        <form wire:submit="recordStockIn">
            <div class="fields">
                <div><label for="stockQuantity">Quantity received</label><input id="stockQuantity" type="number" min="1" step="1" wire:model="stockQuantity">@error('stockQuantity') <span class="error">{{ $message }}</span> @enderror</div>
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

    <div class="panel">
        <h2>Issue stock</h2>
        <p class="muted">Enter the amount issued. It will be deducted from the current on-hand quantity.</p>
        <form wire:submit="recordStockOut">
            <div class="fields">
                <div><label for="issueQuantity">Quantity issued</label><input id="issueQuantity" type="number" min="1" step="1" wire:model="issueQuantity">@error('issueQuantity') <span class="error">{{ $message }}</span> @enderror</div>
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
        <p class="muted">Enter the total you physically counted. The system will adjust the recorded quantity to match.</p>
        <form wire:submit="recordStockAdjustment">
            <div class="fields">
                <div><label for="countedQuantity">Counted on-hand quantity</label><input id="countedQuantity" type="number" min="0" step="1" wire:model="countedQuantity">@error('countedQuantity') <span class="error">{{ $message }}</span> @enderror</div>
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
