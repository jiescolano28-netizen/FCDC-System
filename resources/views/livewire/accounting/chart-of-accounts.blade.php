<section class="chart-of-accounts-page" aria-labelledby="chart-of-accounts-heading">
    <header class="page-heading chart-of-accounts-heading">
        <div>
            <h1 id="chart-of-accounts-heading">Chart of Accounts</h1>
            <p class="page-subtitle">Maintain approved production accounts and source-account mappings.</p>
        </div>
        @can('accounting.maintain-accounts')
            <button class="chart-account-action" type="button" wire:click="newAccount">Add Account</button>
        @endcan
    </header>

    @if (session()->has('account-message'))
        <p role="status">{{ session('account-message') }}</p>
    @endif

    @can('accounting.maintain-accounts')
        <form wire:submit="saveAccount" class="chart-account-card" aria-label="Account details">
            <h2>{{ $editingId ? 'Edit account' : 'New account' }}</h2>
            <div class="chart-account-filters">
                <label>Account code<input wire:model="accountCode" maxlength="30" required></label>
                @error('accountCode') <span role="alert">{{ $message }}</span> @enderror
                <label>Account name<input wire:model="accountName" maxlength="150" required></label>
                @error('accountName') <span role="alert">{{ $message }}</span> @enderror
                <label>Description<textarea wire:model="description" maxlength="2000"></textarea></label>
                <label>Account type
                    <select wire:model.live="type">
                        @foreach ($accountTypes as $accountTypeOption)
                            <option value="{{ $accountTypeOption }}">{{ $accountTypeOption }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Statement classification
                    <select wire:model="classification">
                        @foreach ($classifications->where('type', $type) as $classificationOption)
                            <option value="{{ $classificationOption['value'] }}">{{ $classificationOption['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                @error('classification') <span role="alert">{{ $message }}</span> @enderror
                <label>Normal balance
                    <select wire:model="normalBalance"><option value="debit">Debit</option><option value="credit">Credit</option></select>
                </label>
                <label><input type="checkbox" wire:model="isActive"> Active</label>
            </div>
            @error('type') <p role="alert">{{ $message }}</p> @enderror
            <button type="submit">Save account</button>
            @if ($editingId)<button type="button" wire:click="$set('editingId', null)">Cancel</button>@endif
        </form>
    @endcan

    <div class="chart-account-filters" aria-label="Filter accounts">
        <label><span>Search code, name, or description</span><input type="search" wire:model.live.debounce.250ms="search" placeholder="Search accounts"></label>
        <label><span>Account type</span>
            <select wire:model.live="accountType">
                <option value="All">All types</option>
                @foreach ($accountTypes as $accountTypeOption)<option value="{{ $accountTypeOption }}">{{ $accountTypeOption }}</option>@endforeach
            </select>
        </label>
        <label><span>Status</span><select wire:model.live="status"><option value="All">All statuses</option><option value="Active">Active</option><option value="Inactive">Inactive</option></select></label>
    </div>

    <section class="chart-account-card" aria-labelledby="accounts-heading">
        <header><div><h2 id="accounts-heading">Production accounts</h2><p>Only approved active accounts are eligible for posting.</p></div><span class="chart-account-count">Showing {{ $accounts->count() }} of {{ $accountCount }} accounts</span></header>
        <div class="chart-account-table-wrap"><table>
            <thead><tr><th>Code</th><th>Name</th><th>Description</th><th>Type</th><th>Classification</th><th>Normal balance</th><th>Status</th><th>Approval</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse ($accounts as $account)
                    <tr wire:key="account-{{ $account->id }}">
                        <td><strong>{{ $account->code }}</strong></td><td>{{ $account->name }}</td><td>{{ $account->description }}</td><td>{{ $account->type }}</td>
                        <td>{{ $classifications->firstWhere('value', $account->classification)['label'] }}</td><td>{{ ucfirst($account->normal_balance) }}</td>
                        <td>{{ $account->is_active ? 'Active' : 'Inactive' }}</td><td>{{ $account->approved_at ? 'Approved' : 'Pending approval' }}</td>
                        <td>
                            @can('accounting.maintain-accounts')
                                <button type="button" wire:click="editAccount({{ $account->id }})">Edit</button>
                                <button type="button" wire:click="toggleActive({{ $account->id }})">{{ $account->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                @if ($account->used_at === null)<button type="button" wire:click="deleteAccount({{ $account->id }})" wire:confirm="Delete this unused account?">Delete</button>@endif
                            @endcan
                            @can('accounting.approve-accounts')
                                @if ($account->is_active && ! $account->approved_at)<button type="button" wire:click="approveAccount({{ $account->id }})">Approve account</button>@endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="chart-account-empty">No accounts match the selected filters.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="chart-account-card" aria-labelledby="mappings-heading">
        <header><div><h2 id="mappings-heading">Source-account mappings</h2><p>Each changed mapping returns to pending approval before it can be used.</p></div></header>
        <div class="chart-account-table-wrap"><table>
            <thead><tr><th>Source</th><th>Mapped account</th><th>Approval</th><th>Actions</th></tr></thead>
            <tbody>
                @foreach ($mappingSources as $source => $label)
                    @php($mapping = $mappings->get($source))
                    <tr wire:key="mapping-{{ $source }}">
                        <td>{{ $label }}</td>
                        <td>
                            @if ($mapping?->account)
                                {{ $mapping->account->code }} — {{ $mapping->account->name }}
                                ({{ $mapping->account->type }}; {{ $classifications->firstWhere('value', $mapping->account->classification)['label'] }})
                            @else
                                Not mapped
                            @endif
                            @can('accounting.maintain-accounts')
                                <select wire:model="mappingAccounts.{{ $source }}" aria-label="{{ $label }} account">
                                    <option value="">Select account</option>
                                    @foreach ($eligibleAccounts as $eligibleAccount)
                                        <option value="{{ $eligibleAccount->id }}">{{ $eligibleAccount->code }} — {{ $eligibleAccount->name }} ({{ $eligibleAccount->type }}; {{ $classifications->firstWhere('value', $eligibleAccount->classification)['label'] }})</option>
                                    @endforeach
                                </select>
                                @error("mappingAccounts.$source") <span role="alert">{{ $message }}</span> @enderror
                                <button type="button" wire:click="saveMapping('{{ $source }}')">Save mapping</button>
                            @endcan
                        </td>
                        <td>{{ $mapping?->isApprovedForPosting() ? 'Approved' : 'Pending approval' }}</td>
                        <td>
                            @can('accounting.approve-accounts')
                                @if ($mapping && $mapping->account && ! $mapping->isApprovedForPosting())<button type="button" wire:click="approveMapping('{{ $source }}')">Approve mapping</button>@endif
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </section>
</section>
