<?php

namespace App\Livewire\Accounting;

use Livewire\Component;

class NotesToFinancialStatements extends Component
{
    public function render()
    {
        abort_unless(auth()->user()?->can('accounting.view'), 403);

        return view('livewire.accounting.notes-to-financial-statements')
            ->layout('layouts.app', ['title' => 'Notes to Financial Statements / Disclosure']);
    }
}
