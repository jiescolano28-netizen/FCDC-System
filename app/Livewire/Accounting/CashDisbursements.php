<?php

namespace App\Livewire\Accounting;

use Livewire\Component;

class CashDisbursements extends Component
{
    public string $search = '';

    public string $method = 'All';

    public function render()
    {
        return view('livewire.accounting.cash-disbursements', [
            'disbursements' => collect(),
        ])->layout('layouts.app', ['title' => 'Cash Disbursements']);
    }
}
