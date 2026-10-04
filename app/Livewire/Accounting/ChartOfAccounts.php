<?php

namespace App\Livewire\Accounting;

use App\Support\ReferenceAccounts;
use Livewire\Component;

class ChartOfAccounts extends Component
{
    public string $search = '';

    public string $accountType = 'All';

    public string $status = 'All';

    public function render()
    {
        $accounts = collect(ReferenceAccounts::all());
        $search = mb_strtolower(trim($this->search));

        $filteredAccounts = $accounts->filter(fn (array $account): bool =>
            ($search === '' || str_contains(mb_strtolower($account['code'].' '.$account['name']), $search))
            && ($this->accountType === 'All' || $account['type'] === $this->accountType)
            && ($this->status === 'All' || $account['status'] === $this->status)
        );

        return view('livewire.accounting.chart-of-accounts', [
            'accounts' => $filteredAccounts,
            'accountCount' => $accounts->count(),
        ])->layout('layouts.app', ['title' => 'Chart of Accounts']);
    }
}
