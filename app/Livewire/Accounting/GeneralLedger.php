<?php

namespace App\Livewire\Accounting;

use App\Support\ReferenceAccounts;
use Livewire\Component;

class GeneralLedger extends Component
{
    public string $accountCode = 'All';

    public string $fromDate = '';

    public string $toDate = '';

    public function render()
    {
        return view('livewire.accounting.general-ledger', [
            'accounts' => ReferenceAccounts::all(),
        ])->layout('layouts.app', ['title' => 'General Ledger']);
    }
}
