<?php

namespace App\Livewire\Accounting;

use Livewire\Component;

class AccountsPayable extends Component
{
    public string $search = '';

    public string $status = 'All';

    public function render()
    {
        return view('livewire.accounting.accounts-payable', [
            'invoices' => collect(),
        ])->layout('layouts.app', ['title' => 'Accounts Payable']);
    }
}
