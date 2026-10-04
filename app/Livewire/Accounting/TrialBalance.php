<?php

namespace App\Livewire\Accounting;

use Livewire\Component;

class TrialBalance extends Component
{
    public string $fromDate = '';

    public string $toDate = '';

    public function render()
    {
        return view('livewire.accounting.trial-balance')
            ->layout('layouts.app', ['title' => 'Trial Balance']);
    }
}
