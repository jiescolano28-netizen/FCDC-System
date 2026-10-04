<?php

namespace App\Livewire\TaxCompliance;

use App\Support\VatDemonstrationData;
use Livewire\Component;

class VatReturnPreparation extends Component
{
    public string $selectedPeriod = 'Q3 2026';

    public string $companyName = 'Fabellon Construction and Development Corporation';

    public function updatedSelectedPeriod(): void
    {
        if (! in_array($this->selectedPeriod, VatDemonstrationData::taxPeriods(), true)) {
            $this->selectedPeriod = 'Q3 2026';
        }
    }

    public function render()
    {
        return view('livewire.tax-compliance.vat-return-preparation', [
            'summary' => VatDemonstrationData::summaries()['quarterly'][$this->selectedPeriod] ?? null,
            'periods' => array_keys(VatDemonstrationData::summaries()['quarterly']),
        ])->layout('layouts.app', ['title' => 'VAT Return Preparation']);
    }
}
