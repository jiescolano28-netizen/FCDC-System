<?php

namespace App\Livewire\TaxCompliance;

use App\Models\PosVatRecord;
use App\Services\RecordedVatSummary;
use Carbon\CarbonImmutable;
use Livewire\Component;

class VatSummary extends Component
{
    public string $periodType = 'monthly';

    public int $selectedYear;

    public int $selectedPeriod;

    public function mount(): void
    {
        $now = now('Asia/Manila');
        $this->selectedYear = $now->year;
        $this->selectedPeriod = $now->month;
    }

    public function updatedPeriodType(): void
    {
        if (! in_array($this->periodType, ['monthly', 'quarterly'], true)) {
            $this->periodType = 'monthly';
        }

        $this->selectedPeriod = $this->periodType === 'quarterly'
            ? intdiv($this->selectedPeriod - 1, 3) + 1
            : (($this->selectedPeriod - 1) * 3) + 1;
    }

    public function updatedSelectedPeriod(): void
    {
        $maxPeriod = $this->periodType === 'monthly' ? 12 : 4;
        if ($this->selectedPeriod < 1 || $this->selectedPeriod > $maxPeriod) {
            $this->selectedPeriod = $this->periodType === 'monthly'
                ? now('Asia/Manila')->month
                : now('Asia/Manila')->quarter;
        }
    }

    public function updatedSelectedYear(): void
    {
        if (! in_array($this->selectedYear, $this->availableYears(), true)) {
            $this->selectedYear = now('Asia/Manila')->year;
        }
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('tax.view'), 403);

        $summary = $this->periodType === 'quarterly'
            ? app(RecordedVatSummary::class)->forQuarter($this->selectedYear, $this->selectedPeriod)
            : app(RecordedVatSummary::class)->forMonth($this->selectedYear, $this->selectedPeriod);

        return view('livewire.tax-compliance.vat-summary', [
            'years' => $this->availableYears(),
            'periods' => $this->periodType === 'quarterly'
                ? [1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4']
                : array_combine(range(1, 12), array_map(
                    fn (int $month) => CarbonImmutable::create($this->selectedYear, $month, 1, 0, 0, 0, 'Asia/Manila')->format('F'),
                    range(1, 12),
                )),
            'periodLabel' => $this->periodType === 'quarterly'
                ? 'Q'.$this->selectedPeriod.' '.$this->selectedYear
                : CarbonImmutable::create($this->selectedYear, $this->selectedPeriod, 1, 0, 0, 0, 'Asia/Manila')->format('F Y'),
            'summary' => $summary,
        ])->layout('layouts.app', ['title' => 'VAT Summary']);
    }

    private function availableYears(): array
    {
        $nowYear = now('Asia/Manila')->year;
        $bounds = PosVatRecord::query()
            ->selectRaw('MIN(completed_at) as earliest, MAX(completed_at) as latest')
            ->first();

        $earliestYear = $bounds->earliest
            ? CarbonImmutable::parse($bounds->earliest, 'UTC')->setTimezone('Asia/Manila')->year
            : $nowYear;
        $latestYear = $bounds->latest
            ? CarbonImmutable::parse($bounds->latest, 'UTC')->setTimezone('Asia/Manila')->year
            : $nowYear;

        return array_reverse(range(
            min($earliestYear, $nowYear - 10, $nowYear),
            max($latestYear, $nowYear),
        ));
    }
}
