<?php

namespace App\Livewire\TaxCompliance;

use App\Support\VatDemonstrationData;
use Livewire\Component;

class VatRecords extends Component
{
    public string $search = '';

    public string $period = 'All';

    public string $transactionDate = '';

    public ?array $selectedRecord = null;

    public function viewRecord(string $reference): void
    {
        $this->selectedRecord = collect(VatDemonstrationData::records())
            ->firstWhere('reference', $reference);
    }

    public function closeDetails(): void
    {
        $this->selectedRecord = null;
    }

    public function render()
    {
        $records = collect(VatDemonstrationData::records());
        $filteredRecords = $records->filter(function (array $record): bool {
            $search = mb_strtolower(trim($this->search));

            return ($search === ''
                    || str_contains(mb_strtolower($record['reference']), $search)
                    || str_contains(mb_strtolower($record['period']), $search))
                && ($this->period === 'All' || $record['period'] === $this->period)
                && ($this->transactionDate === '' || $record['date'] === $this->transactionDate);
        });

        return view('livewire.tax-compliance.vat-records', [
            'records' => $filteredRecords,
            'recordCount' => $records->count(),
            'taxPeriods' => VatDemonstrationData::taxPeriods(),
        ])->layout('layouts.app', ['title' => 'VAT Records']);
    }
}
