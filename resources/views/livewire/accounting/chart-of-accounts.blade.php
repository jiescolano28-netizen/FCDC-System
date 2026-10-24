@php
    $isAdministrator = auth()->user()?->hasRole(\App\Support\RolePermissionCatalog::ADMIN_ROLE) ?? false;
    $canMaintainAccounts = $isAdministrator && (auth()->user()?->can('accounting.maintain-accounts') ?? false);
    $canApproveAccounts = $isAdministrator && (auth()->user()?->can('accounting.approve-accounts') ?? false);
@endphp

<section
    class="mx-auto max-w-[1440px] space-y-6"
    aria-labelledby="chart-of-accounts-heading"
    x-data="{
        viewOpen: false,
        viewedAccount: null,
        openAccount(account) {
            this.viewedAccount = account;
            this.viewOpen = true;
        },
        closeAccount() {
            this.viewOpen = false;
            this.viewedAccount = null;
        }
    }"
    x-on:keydown.escape.window="closeAccount()"
>
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 id="chart-of-accounts-heading" class="text-2xl font-semibold tracking-tight text-slate-900">Chart of Accounts</h1>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-600">{{ $isVerifiedDemo ? 'Official chart and source-account mappings; demo balances remain illustrative.' : 'Maintain the official chart of accounts and source-account mappings.' }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span @class([
                'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold',
                'bg-emerald-100 text-emerald-900' => $isAdministrator,
                'bg-slate-100 text-slate-700' => ! $isAdministrator,
            ])>{{ $isAdministrator ? 'Administrator access' : 'View-only access' }}</span>
            @if ($canMaintainAccounts)
                <button
                    type="button"
                    wire:click="newAccount"
                    x-on:click="document.getElementById('account-details')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                    class="inline-flex min-h-9 items-center justify-center rounded-lg bg-emerald-800 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700"
                >Add account</button>
            @endif
        </div>
    </header>

    @if ($isVerifiedDemo)
        <aside class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950" role="note">
            Illustrative / Sample Data — Not Actual FCDC Financial Records
        </aside>
    @endif

    @unless ($isAdministrator)
        <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">
            View-only access. You can search accounts and open account details. Only the System Administrator can add, edit, delete, or manage accounts.
        </p>
    @endunless

    @if (session()->has('account-message'))
        <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">{{ session('account-message') }}</p>
    @endif

    @if ($canMaintainAccounts)
        <section id="account-details" class="scroll-mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="account-form-heading">
            <div class="mb-5 flex flex-col gap-1 border-b border-slate-100 pb-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="account-form-heading" class="text-base font-semibold text-slate-900">{{ $editingId ? 'Edit account' : 'Add an account' }}</h2>
                    <p class="mt-1 text-sm text-slate-600">Record the account title, type, description, and normal balance.</p>
                </div>
                <span class="text-xs font-medium text-slate-500">Fields marked required must be completed.</span>
            </div>
            <form wire:submit="saveAccount" aria-label="Account details">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label for="account-code" class="mb-1.5 block text-sm font-medium text-slate-700">Account code <span class="text-red-700">*</span></label>
                        <input id="account-code" wire:model.live.debounce.250ms="accountCode" maxlength="30" required aria-invalid="@error('accountCode') true @else false @enderror" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-500 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20" autocomplete="off">
                        @error('accountCode') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="account-name" class="mb-1.5 block text-sm font-medium text-slate-700">Account title <span class="text-red-700">*</span></label>
                        <input id="account-name" wire:model="accountName" maxlength="150" required aria-invalid="@error('accountName') true @else false @enderror" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-500 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20" autocomplete="off">
                        @error('accountName') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="account-type" class="mb-1.5 block text-sm font-medium text-slate-700">Account type</label>
                        <select id="account-type" wire:model.live="type" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20">
                            @foreach ($accountTypes as $accountTypeOption)
                                <option value="{{ $accountTypeOption }}">{{ $accountTypeOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2 xl:col-span-4">
                        <label for="account-description" class="mb-1.5 block text-sm font-medium text-slate-700">Description <span class="font-normal text-slate-500">Optional</span></label>
                        <textarea id="account-description" wire:model="description" maxlength="2000" rows="3" placeholder="Explain what this account records and when it is used." class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-500 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20"></textarea>
                        <p class="mt-1 text-xs text-slate-500">The description appears in the account list and in the View panel.</p>
                        @error('description') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
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
                            @if ($accountCode === '3040')<option value="variable">Credit when profit; Debit when loss</option>@endif
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
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-label="Filter accounts">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_minmax(180px,0.32fr)_minmax(180px,0.32fr)]">
            <div>
                <label for="account-search" class="mb-1.5 block text-sm font-medium text-slate-700">Search accounts</label>
                <input id="account-search" type="search" wire:model.live.debounce.250ms="search" placeholder="Code, title, or description" class="min-h-10 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-500 focus:border-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-700/20">
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
                <h2 id="accounts-heading" class="text-base font-semibold text-slate-900">{{ $isVerifiedDemo ? 'Official accounts (demo database)' : 'Official accounts' }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $isVerifiedDemo ? 'The chart is official; demo balances and activity are illustrative. Posting eligibility and treatment notes are shown separately.' : 'Only approved active accounts are eligible for posting. Descriptions are maintained per account.' }}</p>
            </div>
            <span class="text-sm tabular-nums text-slate-600" aria-live="polite">Showing {{ $accounts->count() }} of {{ $accountCount }} accounts</span>
        </header>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1180px] border-collapse text-left text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-3">Account code</th>
                        <th scope="col" class="px-4 py-3">Account title</th>
                        <th scope="col" class="px-4 py-3">Account type</th>
                        <th scope="col" class="px-4 py-3">Description</th>
                        <th scope="col" class="px-4 py-3">Classification</th>
                        <th scope="col" class="px-4 py-3">Normal balance</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="px-4 py-3">Approval</th>
                        <th scope="col" class="px-4 py-3">Posting eligibility</th>
                        <th scope="col" class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($accounts as $account)
                        <tr wire:key="account-{{ $account->id }}" class="transition-colors hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-4 py-3 font-semibold tabular-nums text-slate-900">{{ $account->code }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $account->name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $account->displayType() }}</td>
                            <td class="min-w-[260px] max-w-md whitespace-normal px-4 py-3 leading-6 text-slate-600" title="{{ $account->description }}">{{ $account->description ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $classifications->firstWhere('value', $account->classification)['label'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $account->normal_balance === 'variable' ? 'Credit when profit; Debit when loss' : ucfirst($account->normal_balance) }}</td>
                            <td class="whitespace-nowrap px-4 py-3"><span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-medium', 'bg-emerald-50 text-emerald-800' => $account->is_active, 'bg-slate-100 text-slate-700' => ! $account->is_active])>{{ $account->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3"><span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-medium', 'bg-emerald-50 text-emerald-800' => $account->approved_at, 'bg-amber-50 text-amber-900' => ! $account->approved_at])>{{ $account->approved_at ? 'Approved' : 'Pending approval' }}</span></td>
                            <td class="whitespace-nowrap px-4 py-3"><span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-medium', 'bg-emerald-50 text-emerald-800' => $account->isApprovedForPosting(), 'bg-amber-50 text-amber-900' => ! $account->isApprovedForPosting()])>{{ $account->isApprovedForPosting() ? 'Eligible' : 'Not eligible' }}</span></td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        data-account="{{ json_encode([
                                            'code' => $account->code,
                                            'title' => $account->name,
                                            'type' => $account->displayType(),
                                            'classification' => $classifications->firstWhere('value', $account->classification)['label'] ?? $account->classification,
                                            'normalBalance' => $account->normal_balance === 'variable' ? 'Credit when profit; Debit when loss' : ucfirst($account->normal_balance),
                                            'description' => $account->description ?: '',
                                            'status' => $account->is_active ? 'Active' : 'Inactive',
                                            'approval' => $account->approved_at ? 'Approved' : 'Pending approval',
                                            'posting' => $account->isApprovedForPosting() ? 'Eligible for posting' : 'Not eligible for posting',
                                        ]) }}"
                                        x-on:click="openAccount(JSON.parse($el.dataset.account))"
                                        class="rounded-md px-2 py-1.5 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700"
                                    >View</button>
                                    @if ($canMaintainAccounts)
                                        <button type="button" wire:click="editAccount({{ $account->id }})" class="rounded-md px-2 py-1.5 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-50 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700">Edit</button>
                                        <button type="button" wire:click="toggleActive({{ $account->id }})" class="rounded-md px-2 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700">{{ $account->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                        @if ($account->used_at === null)<button type="button" wire:click="deleteAccount({{ $account->id }})" wire:confirm="Delete this unused account?" class="rounded-md px-2 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-red-700">Delete</button>@endif
                                    @endif
                                    @if ($canApproveAccounts)
                                        @if ($account->is_active && ! $account->approved_at)<button type="button" wire:click="approveAccount({{ $account->id }})" class="rounded-md bg-emerald-800 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700">Approve</button>@endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-6 py-12 text-center">
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
            <p class="mt-1 text-sm text-slate-600">Each changed mapping returns to pending approval before it can be used. The Point of Sale posts through Cash, Bank, Card clearing, Sales, Output VAT, COGS and Inventory, so all seven must be approved before a sale can complete.</p>
        </header>
        @php($posBlockers = $posReadiness->reject(fn (array $entry) => $entry['ready']))
        @if ($posBlockers->isEmpty())
            <div class="border-b border-emerald-200 bg-emerald-50 px-5 py-4" role="status">
                <p class="text-sm font-semibold text-emerald-900">Point of Sale readiness: all required source-account mappings are approved.</p>
            </div>
        @else
            <div class="border-b border-amber-300 bg-amber-50 px-5 py-4" role="alert">
                <p class="text-sm font-semibold text-amber-950">Point of Sale readiness: {{ $posBlockers->count() }} of {{ $posReadiness->count() }} required mappings are not ready.</p>
                <p class="mt-1 text-sm text-amber-900">Complete a sale only after every item below shows an approved mapping. Each mapping needs an active, approved account whose classification matches the source.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-900">
                    @foreach ($posBlockers as $entry)
                        <li><span class="font-medium">{{ $entry['label'] }}</span> — {{ $entry['reason'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
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
                                    <p class="mt-1 text-xs text-slate-600">{{ $mapping->account->displayType() }} · {{ $classifications->firstWhere('value', $mapping->account->classification)['label'] }}</p>
                                @else
                                    <p class="mb-2 text-sm text-slate-600">Not mapped</p>
                                @endif
                                @if ($canMaintainAccounts)
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
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4"><span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-medium', 'bg-emerald-50 text-emerald-800' => $mapping?->isApprovedForPosting(), 'bg-amber-50 text-amber-900' => ! $mapping?->isApprovedForPosting()])>{{ $mapping?->isApprovedForPosting() ? 'Approved' : 'Pending approval' }}</span></td>
                            <td class="whitespace-nowrap px-4 py-4">
                                @if ($canApproveAccounts)
                                    @if ($mapping && $mapping->account && ! $mapping->isApprovedForPosting())<button type="button" wire:click="approveMapping('{{ $source }}')" class="rounded-md bg-emerald-800 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-emerald-700">Approve mapping</button>@endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Account details panel: available to every user with view access. --}}
    <div x-show="viewOpen" x-cloak class="fixed inset-0 z-[1000] grid place-items-center bg-slate-900/55 p-6 max-sm:items-end max-sm:p-0" role="presentation" x-on:click.self="closeAccount()">
        <section class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-2xl max-sm:max-h-[94vh] max-sm:rounded-b-none max-sm:p-4" role="dialog" aria-modal="true" aria-labelledby="account-view-heading">
            <header class="mb-5 flex items-start justify-between gap-4 border-b border-slate-100 pb-4">
                <div class="min-w-0">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-emerald-800">Account details</p>
                    <h2 id="account-view-heading" class="m-0 break-words text-base font-semibold text-slate-900" x-text="viewedAccount ? viewedAccount.code + ' — ' + viewedAccount.title : 'Account details'"></h2>
                </div>
                <button type="button" x-on:click="closeAccount()" class="grid h-[34px] w-[34px] shrink-0 place-items-center rounded-md border border-slate-200 bg-white text-2xl leading-none text-slate-700 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-emerald-700" aria-label="Close account details">&times;</button>
            </header>
            <template x-if="viewedAccount">
                <div>
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-900">Description</p>
                        <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700" x-text="viewedAccount.description || 'No description has been recorded for this account.'"></p>
                    </div>
                    <dl class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div><dt class="text-xs font-medium text-slate-500">Account code</dt><dd class="mt-1 text-sm font-semibold tabular-nums text-slate-900" x-text="viewedAccount.code"></dd></div>
                        <div><dt class="text-xs font-medium text-slate-500">Account title</dt><dd class="mt-1 text-sm font-medium text-slate-800" x-text="viewedAccount.title"></dd></div>
                        <div><dt class="text-xs font-medium text-slate-500">Account type</dt><dd class="mt-1 text-sm text-slate-700" x-text="viewedAccount.type"></dd></div>
                        <div><dt class="text-xs font-medium text-slate-500">Statement classification</dt><dd class="mt-1 text-sm text-slate-700" x-text="viewedAccount.classification"></dd></div>
                        <div><dt class="text-xs font-medium text-slate-500">Normal balance</dt><dd class="mt-1 text-sm text-slate-700" x-text="viewedAccount.normalBalance"></dd></div>
                        <div><dt class="text-xs font-medium text-slate-500">Status</dt><dd class="mt-1 text-sm text-slate-700" x-text="viewedAccount.status"></dd></div>
                        <div><dt class="text-xs font-medium text-slate-500">Approval</dt><dd class="mt-1 text-sm text-slate-700" x-text="viewedAccount.approval"></dd></div>
                        <div><dt class="text-xs font-medium text-slate-500">Posting eligibility</dt><dd class="mt-1 text-sm text-slate-700" x-text="viewedAccount.posting"></dd></div>
                    </dl>
                </div>
            </template>
            <footer class="mt-6 flex justify-end border-t border-slate-100 pt-4">
                <button type="button" x-on:click="closeAccount()" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">Close details</button>
            </footer>
        </section>
    </div>

</section>
