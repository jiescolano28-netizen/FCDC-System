<?php

namespace App\Livewire\Accounting;

use App\Models\AccountingAccount;
use App\Models\AccountingPostingMapping;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ChartOfAccounts extends Component
{
    private const TYPES = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];

    private const CLASSIFICATIONS = [
        'cash' => ['Cash', 'Asset'],
        'bank' => ['Bank', 'Asset'],
        'accounts_receivable' => ['Accounts receivable', 'Asset'],
        'card_clearing' => ['Card settlement receivable / clearing', 'Asset'],
        'inventory' => ['Inventory', 'Asset'],
        'equipment' => ['Equipment', 'Asset'],
        'other_current_asset' => ['Other current asset', 'Asset'],
        'other_noncurrent_asset' => ['Other non-current asset', 'Asset'],
        'accounts_payable' => ['Accounts payable', 'Liability'],
        'output_vat' => ['Output VAT', 'Liability'],
        'input_vat' => ['Input VAT', 'Asset'],
        'other_current_liability' => ['Other current liability', 'Liability'],
        'other_noncurrent_liability' => ['Other non-current liability', 'Liability'],
        'capital' => ['Capital', 'Equity'],
        'retained_earnings' => ['Retained / accumulated earnings', 'Equity'],
        'other_equity' => ['Other equity', 'Equity'],
        'sales' => ['Sales', 'Revenue'],
        'other_income' => ['Other income', 'Revenue'],
        'cost_of_goods_sold' => ['Cost of goods sold (Expense)', 'Expense'],
        'operating_expense' => ['Operating expense', 'Expense'],
        'other_expense' => ['Other expense', 'Expense'],
    ];

    private const MAPPING_SOURCES = [
        'cash' => 'Cash',
        'bank' => 'Bank',
        'card_clearing' => 'Card clearing',
        'sales' => 'Sales',
        'output_vat' => 'Output VAT',
        'cogs' => 'COGS',
        'inventory' => 'Inventory',
        'accounts_payable' => 'Accounts Payable',
        'disbursement_method:Other' => 'Other disbursement method',
        'recovery_offset' => 'Recovery counterpart',
        'adjustment' => 'Adjustment counterpart',
    ];

    public function mount(): void
    {
        $this->mappingAccounts = AccountingPostingMapping::pluck('accounting_account_id', 'source')
            ->map(fn ($id) => $id === null ? '' : (string) $id)
            ->all();
    }

    public function newAccount(): void
    {
        $this->authorizePermission('accounting.maintain-accounts');
        $this->resetAccountForm();
    }

    public string $search = '';

    public string $accountType = 'All';

    public string $status = 'All';

    public ?int $editingId = null;

    public string $accountCode = '';

    public string $accountName = '';

    public string $description = '';

    public string $type = 'Asset';

    public string $classification = 'cash';

    public string $normalBalance = 'debit';

    public bool $isActive = true;

    public array $mappingAccounts = [];

    public function updatedType(string $type): void
    {
        if ((self::CLASSIFICATIONS[$this->classification][1] ?? null) === $type) {
            return;
        }

        foreach (self::CLASSIFICATIONS as $classification => [, $classificationType]) {
            if ($classificationType === $type) {
                $this->classification = $classification;

                return;
            }
        }
    }

    public function editAccount(int $id): void
    {
        $this->authorizePermission('accounting.maintain-accounts');
        $account = AccountingAccount::findOrFail($id);
        $this->editingId = $account->id;
        $this->accountCode = $account->code;
        $this->accountName = $account->name;
        $this->description = $account->description ?? '';
        $this->type = $account->type;
        $this->classification = $account->classification;
        $this->normalBalance = $account->normal_balance;
        $this->isActive = $account->is_active;
        $this->resetValidation();
    }

    public function saveAccount(): void
    {
        $this->authorizePermission('accounting.maintain-accounts');
        $validated = $this->validate([
            'accountCode' => ['required', 'string', 'max:30', Rule::unique('accounting_accounts', 'code')->ignore($this->editingId)],
            'accountName' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(self::TYPES)],
            'classification' => ['required', Rule::in(array_keys(self::CLASSIFICATIONS))],
            'normalBalance' => ['required', Rule::in(['debit', 'credit'])],
            'isActive' => ['required', 'boolean'],
        ]);

        if (self::CLASSIFICATIONS[$validated['classification']][1] !== $validated['type']) {
            $this->addError('classification', 'Choose a statement classification matching the account type.');

            return;
        }

        $account = $this->editingId ? AccountingAccount::findOrFail($this->editingId) : new AccountingAccount;
        if ($account->exists && $account->used_at !== null) {
            foreach ([
                'accountCode' => 'code',
                'type' => 'type',
                'classification' => 'classification',
            ] as $field => $column) {
                if ($validated[$field] !== $account->{$column}) {
                    $this->addError($field, 'Used account codes, types, and classifications are historically immutable.');
                }
            }
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }
        }

        $dirty = $account->exists && $account->only([
            'code', 'name', 'description', 'type', 'classification', 'normal_balance', 'is_active',
        ]) !== [
            'code' => $validated['accountCode'],
            'name' => $validated['accountName'],
            'description' => $validated['description'] ?: null,
            'type' => $validated['type'],
            'classification' => $validated['classification'],
            'normal_balance' => $validated['normalBalance'],
            'is_active' => $validated['isActive'],
        ];

        $account->fill([
            'code' => $validated['accountCode'],
            'name' => $validated['accountName'],
            'description' => $validated['description'] ?: null,
            'type' => $validated['type'],
            'classification' => $validated['classification'],
            'normal_balance' => $validated['normalBalance'],
            'is_active' => $validated['isActive'],
        ]);
        if (! $account->exists || $dirty) {
            $account->approved_at = null;
            $account->approved_by = null;
        }
        $account->save();
        $this->resetAccountForm();
        session()->flash('account-message', 'Account saved. Approval is required before posting.');
    }

    public function approveAccount(int $id): void
    {
        $this->authorizePermission('accounting.approve-accounts');
        $account = AccountingAccount::findOrFail($id);
        abort_unless($account->is_active, 422, 'Inactive accounts cannot be approved for posting.');
        $account->forceFill(['approved_at' => now(), 'approved_by' => auth()->id()])->save();
    }

    public function toggleActive(int $id): void
    {
        $this->authorizePermission('accounting.maintain-accounts');
        $account = AccountingAccount::findOrFail($id);
        $account->is_active = ! $account->is_active;
        if ($account->is_active) {
            $account->approved_at = null;
            $account->approved_by = null;
        }
        $account->save();
    }

    public function deleteAccount(int $id): void
    {
        $this->authorizePermission('accounting.maintain-accounts');
        $account = AccountingAccount::findOrFail($id);
        abort_if($account->used_at !== null, 403, 'Used accounts cannot be deleted.');
        $account->delete();
    }

    public function saveMapping(string $source): void
    {
        $this->authorizePermission('accounting.maintain-accounts');
        abort_unless(isset(self::MAPPING_SOURCES[$source]), 404);
        $accountRule = Rule::exists('accounting_accounts', 'id')->where('is_active', true);
        if ($source === 'disbursement_method:Other') {
            $accountRule->whereIn('classification', ['cash', 'bank']);
        } elseif (isset(AccountingPostingMapping::REQUIRED_CLASSIFICATIONS[$source])) {
            $accountRule->where('classification', AccountingPostingMapping::REQUIRED_CLASSIFICATIONS[$source]);
        }
        $validated = $this->validate([
            "mappingAccounts.$source" => ['required', 'integer', $accountRule],
        ]);
        $accountId = (int) $validated['mappingAccounts'][$source];
        $mapping = AccountingPostingMapping::firstOrNew(['source' => $source]);
        if ((int) $mapping->accounting_account_id !== $accountId) {
            $mapping->accounting_account_id = $accountId;
            $mapping->approved_at = null;
            $mapping->approved_by = null;
        }
        $mapping->save();
    }

    public function approveMapping(string $source): void
    {
        $this->authorizePermission('accounting.approve-accounts');
        abort_unless(isset(self::MAPPING_SOURCES[$source]), 404);
        $mapping = AccountingPostingMapping::where('source', $source)->with('account')->firstOrFail();
        abort_unless($mapping->account?->isApprovedForPosting(), 422, 'Approve an active account before approving its mapping.');
        $mapping->forceFill(['approved_at' => now(), 'approved_by' => auth()->id()])->save();
    }

    public function render()
    {
        $search = mb_strtolower(trim($this->search));
        $accounts = AccountingAccount::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereRaw('LOWER(code) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', ["%{$search}%"])))
            ->when($this->accountType !== 'All', fn ($query) => $query->where('type', $this->accountType))
            ->when($this->status !== 'All', fn ($query) => $query->where('is_active', $this->status === 'Active'))
            ->orderBy('code')
            ->get();

        return view('livewire.accounting.chart-of-accounts', [
            'accounts' => $accounts,
            'accountCount' => AccountingAccount::count(),
            'accountTypes' => self::TYPES,
            'classifications' => collect(self::CLASSIFICATIONS)->map(fn ($entry, $key) => ['value' => $key, 'label' => $entry[0], 'type' => $entry[1]]),
            'mappingSources' => self::MAPPING_SOURCES,
            'mappings' => AccountingPostingMapping::with('account')->get()->keyBy('source'),
            'eligibleAccounts' => AccountingAccount::where('is_active', true)->orderBy('code')->get(),
        ])->layout('layouts.app', ['title' => 'Chart of Accounts']);
    }

    private function resetAccountForm(): void
    {
        $this->reset(['editingId', 'accountCode', 'accountName', 'description']);
        $this->type = 'Asset';
        $this->classification = 'cash';
        $this->normalBalance = 'debit';
        $this->isActive = true;
        $this->resetValidation();
    }

    private function authorizePermission(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
