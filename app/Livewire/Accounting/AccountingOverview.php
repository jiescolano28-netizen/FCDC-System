<?php

namespace App\Livewire\Accounting;

use Livewire\Component;

class AccountingOverview extends Component
{
    public function render()
    {
        return view('livewire.accounting.accounting-overview')
            ->layout('layouts.app', ['title' => 'Accounting Overview']);
    }
}
