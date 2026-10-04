<?php

namespace App\Livewire\TaxCompliance;

use App\Support\VatDemonstrationData;
use Livewire\Component;

class VatSummary extends Component
{
    public string $periodType = 'monthly';

    public string $selectedPeriod = 'August 2026';

    public function updatedPeriodType(): void
    {
        $periods = VatDemonstrationData::summaries()[$this->periodType] ?? [];

        if (! array_key_exists($this->selectedPeriod, $periods)) {
            $this->selectedPeriod = (string) array_key_first($periods);
        }
    }

    public function render()
    {
        $summaries = VatDemonstrationData::summaries();
        $periods = $summaries[$this->periodType] ?? [];

        return view('livewire.tax-compliance.vat-summary', [
            'periods' => array_keys($periods),
            'summary' => $periods[$this->selectedPeriod] ?? null,
        ])->layout('layouts.app', ['title' => 'VAT Summary']);
    }
}
