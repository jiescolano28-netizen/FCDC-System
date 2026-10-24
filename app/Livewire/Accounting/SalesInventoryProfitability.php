<?php

namespace App\Livewire\Accounting;

use App\Services\Accounting\SalesInventoryProfitabilityReport;
use Carbon\CarbonImmutable;
use Livewire\Component;

class SalesInventoryProfitability extends Component
{
    public string $fromDate = '';

    public string $toDate = '';

    public function mount(): void
    {
        $today = CarbonImmutable::now('Asia/Manila');
        $this->fromDate = $today->startOfMonth()->toDateString();
        $this->toDate = $today->toDateString();
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('accounting.view'), 403);

        $report = app(SalesInventoryProfitabilityReport::class)->build($this->fromDate, $this->toDate);

        return view('livewire.accounting.sales-inventory-profitability', [
            'report' => $report,
            'generatedAt' => CarbonImmutable::now('Asia/Manila')->format('F j, Y g:i:s A').' Asia/Manila',
        ])->layout('layouts.app', ['title' => 'Sales and Inventory Profitability Report']);
    }
}
