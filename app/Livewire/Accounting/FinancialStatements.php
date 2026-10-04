<?php

namespace App\Livewire\Accounting;

use Livewire\Component;

class FinancialStatements extends Component
{
    public string $statementType = 'income-statement';

    public string $fromDate = '';

    public string $toDate = '';

    public function render()
    {
        $statementTitle = match ($this->statementType) {
            'balance-sheet' => 'Balance Sheet',
            default => 'Income Statement',
        };

        return view('livewire.accounting.financial-statements', [
            'statementTitle' => $statementTitle,
        ])->layout('layouts.app', ['title' => 'Financial Statements']);
    }
}
