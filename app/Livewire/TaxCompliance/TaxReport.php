<?php

namespace App\Livewire\TaxCompliance;

use App\Support\VatDemonstrationData;
use Livewire\Component;

class TaxReport extends Component
{
    public string $period = 'Q3 2026';

    public function render()
    {
        $records = collect(VatDemonstrationData::records())
            ->where('period', $this->period)
            ->values();

        return view('livewire.tax-compliance.tax-report', [
            'periods' => VatDemonstrationData::taxPeriods(),
            'records' => $records,
            'summary' => [
                'taxable' => $records->sum('taxable'),
                'vat' => $records->sum('vat'),
                'total' => $records->sum('total'),
            ],
            'generatedAt' => now()->format('F j, Y'),
        ])->layout('layouts.app', ['title' => 'Tax Report']);
    }
}
