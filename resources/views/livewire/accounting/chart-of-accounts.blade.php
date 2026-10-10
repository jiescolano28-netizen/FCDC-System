<section class="mx-auto max-w-[1440px] space-y-6" aria-labelledby="chart-of-accounts-heading">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 id="chart-of-accounts-heading" class="text-2xl font-semibold tracking-tight text-slate-900">Chart of Accounts</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">Maintain approved production accounts and source-account mappings.</p>
        </div>
    </header>

    @if (session()->has('account-message'))
        <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">{{ session('account-message') }}</p>
    @endif

    @can('accounting.maintain-accounts')
        <section id="account-details" class="scroll-mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="account-form-heading">
            <div class="mb-5 flex flex-col gap-1 border-b border-slate-100 pb-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="account-form-heading" class="text-base font-semibold text-slate-900">{{ $editingId ? 'Edit account' : 'Add an account' }}</h2>
                    <p class="mt-1 text-sm text-slate-600">Set the account’s classification and normal balance.</p>
                </div>
                <span class="text-xs font-medium text-slate-500">Fields marked required must be completed.</span>
            </div>
            <form wire:submit="saveAccount" aria-label="Account details">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label for="account-code" class="mb-1.5 block text-sm font-medium text-slate-700">Account code <span class="text-red-700">*</span></label>
                        <input id="account-code" wire:model="accountCode" maxlength="30" required aria-invalid="@error('accountCode') true @else false @enderror" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-500 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20" autocomplete="off">
                        @error('accountCode') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="account-name" class="mb-1.5 block text-sm font-medium text-slate-700">Account name <span class="text-red-700">*</span></label>
                        <input id="account-name" wire:model="accountName" maxlength="150" required aria-invalid="@error('accountName') true @else false @enderror" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-500 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20" autocomplete="off">
                        @error('accountName') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="account-description" class="mb-1.5 block text-sm font-medium text-slate-700">Description <span class="font-normal text-slate-500">Optional</span></label>
                        <textarea id="account-description" wire:model="description" maxlength="2000" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-500 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20"></textarea>
                    </div>
                    <div>
                        <label for="account-type" class="mb-1.5 block text-sm font-medium text-slate-700">Account type</label>
                        <select id="account-type" wire:model.live="type" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20">
                            @foreach ($accountTypes as $accountTypeOption)
                                <option value="{{ $accountTypeOption }}">{{ $accountTypeOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="account-classification" class="mb-1.5 block text-sm font-medium text-slate-700">Statement classification</label>
                        <select id="account-classification" wire:model="classification" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20">
                            @foreach ($classifications->where('type', $type) as $classificationOption)
                                <option value="{{ $classificationOption['value'] }}">{{ $classificationOption['label'] }}</option>
                            @endforeach
                        </select>
                        @error('classification') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="normal-balance" class="mb-1.5 block text-sm font-medium text-slate-700">Normal balance</label>
                        <select id="normal-balance" wire:model="normalBalance" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20">
                            <option value="debit">Debit</option>
                            <option value="credit">Credit</option>
                        </select>
                    </div>
                    <label for="account-active" class="flex min-h-10 items-center gap-3 self-end rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700">
                        <input id="account-active" type="checkbox" wire:model="isActive" class="size-4 rounded border-slate-300 text-emerald-800 focus:ring-emerald-700">
                        Active
                    </label>
                </div>
                @error('type') <p class="mt-3 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveAccount" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700 disabled:cursor-wait disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveAccount">{{ $editingId ? 'Save changes' : 'Save account' }}</span>
                        <span wire:loading wire:target="saveAccount">Saving…</span>
                    </button>
                    @if ($editingId)
                        <button type="button" wire:click="$set('editingId', null)" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">Cancel editing</button>
                    @endif
                </div>
            </form>
        </section>
    @endcan

    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-label="Filter accounts">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_minmax(180px,0.32fr)_minmax(180px,0.32fr)]">
            <div>
                <label for="account-search" class="mb-1.5 block text-sm font-medium text-slate-700">Search accounts</label>
                <input id="account-search" type="search" wire:model.live.debounce.250ms="search" placeholder="Code, name, or description" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-500 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20">
            </div>
            <div>
                <label for="account-type-filter" class="mb-1.5 block text-sm font-medium text-slate-700">Account type</label>
                <select id="account-type-filter" wire:model.live="accountType" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20">
                    <option value="All">All types</option>
                    @foreach ($accountTypes as $accountTypeOption)<option value="{{ $accountTypeOption }}">{{ $accountTypeOption }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="account-status-filter" class="mb-1.5 block text-sm font-medium text-slate-700">Status</label>
                <select id="account-status-filter" wire:model.live="status" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20">
                    <option value="All">All statuses</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="accounts-heading">
        <header class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="accounts-heading" class="text-base font-semibold text-slate-900">Production accounts</h2>
                <p class="mt-1 text-sm text-slate-600">Only approved active accounts are eligible for posting.</p>
            </div>
            <span class="text-sm tabular-nums text-slate-600" aria-live="polite">Showing {{ $accounts->count() }} of {{ $accountCount }} accounts</span>
        </header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] border-collapse text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-3">Code</th><th scope="col" class="px-4 py-3">Name</th><th scope="col" class="px-4 py-3">Description</th><th scope="col" class="px-4 py-3">Type</th><th scope="col" class="px-4 py-3">Classification</th><th scope="col" class="px-4 py-3">Normal balance</th><th scope="col" class="px-4 py-3">Status</th><th scope="col" class="px-4 py-3">Approval</th><th scope="col" class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($accounts as $account)
                        <tr wire:key="account-{{ $account->id }}" class="transition-colors hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-4 py-3 font-semibold tabular-nums text-slate-900">{{ $account->code }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $account->name }}</td>
                            <td class="max-w-xs truncate px-4 py-3 text-slate-600" title="{{ $account->description }}">{{ $account->description ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $account->type }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $classifications->firstWhere('value', $account->classification)['label'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ ucfirst($account->normal_balance) }}</td>
                            <td class="whitespace-nowrap px-4 py-3"><span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-medium', 'bg-emerald-50 text-emerald-800' => $account->is_active, 'bg-slate-100 text-slate-700' => ! $account->is_active])>{{ $account->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3"><span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-medium', 'bg-emerald-50 text-emerald-800' => $account->approved_at, 'bg-amber-50 text-amber-900' => ! $account->approved_at])>{{ $account->approved_at ? 'Approved' : 'Pending approval' }}</span></td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    @can('accounting.maintain-accounts')
                                        <button type="button" wire:click="editAccount({{ $account->id }})" class="rounded-md px-2 py-1.5 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700">Edit</button>
                                        <button type="button" wire:click="toggleActive({{ $account->id }})" class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700">{{ $account->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                        @if ($account->used_at === null)<button type="button" wire:click="deleteAccount({{ $account->id }})" wire:confirm="Delete this unused account?" class="rounded-md px-2 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-red-700">Delete</button>@endif
                                    @endcan
                                    @can('accounting.approve-accounts')
                                        @if ($account->is_active && ! $account->approved_at)<button type="button" wire:click="approveAccount({{ $account->id }})" class="rounded-md bg-emerald-800 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700">Approve</button>@endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-6 py-12 text-center">
                            <p class="text-sm font-semibold text-slate-800">No accounts found</p>
                            <p class="mt-1 text-sm text-slate-600">Try changing the search or filters to see more accounts.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="mappings-heading">
        <header class="border-b border-slate-200 px-5 py-4">
            <h2 id="mappings-heading" class="text-base font-semibold text-slate-900">Source-account mappings</h2>
            <p class="mt-1 text-sm text-slate-600">Each changed mapping returns to pending approval before it can be used.</p>
        </header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] border-collapse text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <tr><th scope="col" class="px-4 py-3">Source</th><th scope="col" class="px-4 py-3">Mapped account</th><th scope="col" class="px-4 py-3">Approval</th><th scope="col" class="px-4 py-3">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($mappingSources as $source => $label)
                        @php($mapping = $mappings->get($source))
                        <tr wire:key="mapping-{{ $source }}" class="align-top hover:bg-slate-50/70">
                            <th scope="row" class="whitespace-nowrap px-4 py-4 font-medium text-slate-800">{{ $label }}</th>
                            <td class="px-4 py-4">
                                @if ($mapping?->account)
                                    <p class="font-medium text-slate-800">{{ $mapping->account->code }} — {{ $mapping->account->name }}</p>
                                    <p class="mt-1 text-xs text-slate-600">{{ $mapping->account->type }} · {{ $classifications->firstWhere('value', $mapping->account->classification)['label'] }}</p>
                                @else
                                    <p class="mb-2 text-sm text-slate-600">Not mapped</p>
                                @endif
                                @can('accounting.maintain-accounts')
                                    <div class="mt-2 flex flex-wrap items-start gap-2">
                                        <div>
                                            <label class="sr-only" for="mapping-{{ $source }}">Choose account for {{ $label }}</label>
                                            <select id="mapping-{{ $source }}" wire:model="mappingAccounts.{{ $source }}" class="min-h-9 max-w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-800 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20">
                                                <option value="">Select account</option>
                                                @foreach ($eligibleAccounts as $eligibleAccount)
                                                    <option value="{{ $eligibleAccount->id }}">{{ $eligibleAccount->code }} — {{ $eligibleAccount->name }} ({{ $eligibleAccount->type }}; {{ $classifications->firstWhere('value', $eligibleAccount->classification)['label'] }})</option>
                                                @endforeach
                                            </select>
                                            @error("mappingAccounts.$source") <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                                        </div>
                                        <button type="button" wire:click="saveMapping('{{ $source }}')" class="inline-flex min-h-9 items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700">Save mapping</button>
                                    </div>
                                @endcan
                            </td>
                            <td class="whitespace-nowrap px-4 py-4"><span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-medium', 'bg-emerald-50 text-emerald-800' => $mapping?->isApprovedForPosting(), 'bg-amber-50 text-amber-900' => ! $mapping?->isApprovedForPosting()])>{{ $mapping?->isApprovedForPosting() ? 'Approved' : 'Pending approval' }}</span></td>
                            <td class="whitespace-nowrap px-4 py-4">
                                @can('accounting.approve-accounts')
                                    @if ($mapping && $mapping->account && ! $mapping->isApprovedForPosting())<button type="button" wire:click="approveMapping('{{ $source }}')" class="rounded-md bg-emerald-800 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700">Approve mapping</button>@endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</section>
