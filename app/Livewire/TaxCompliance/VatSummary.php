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

    public function updatingPeriodType(string $value): void
    {
        $previous = $this->periodType;

        if ($value === 'quarterly' && $previous === 'monthly') {
            $this->selectedPeriod = max(1, min(4, intdiv($this->selectedPeriod - 1, 3) + 1));
        } elseif ($value === 'monthly' && $previous === 'quarterly') {
            $this->selectedPeriod = (($this->selectedPeriod - 1) * 3) + 1;
        } elseif ($value === 'quarterly' && $previous === 'yearly' && $this->selectedPeriod > 4) {
            $this->selectedPeriod = now('Asia/Manila')->quarter;
        }
    }

    public function updatedPeriodType(): void
    {
        if (! in_array($this->periodType, ['monthly', 'quarterly', 'yearly'], true)) {
            $this->periodType = 'monthly';
        }
    }

    public function updatedSelectedPeriod(): void
    {
        if ($this->periodType === 'yearly') {
            return;
        }

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

        $summary = match ($this->periodType) {
            'yearly' => app(RecordedVatSummary::class)->forYear($this->selectedYear),
            'quarterly' => app(RecordedVatSummary::class)->forQuarter($this->selectedYear, $this->selectedPeriod),
            default => app(RecordedVatSummary::class)->forMonth($this->selectedYear, $this->selectedPeriod),
        };

        $periods = match ($this->periodType) {
            'quarterly' => [1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'],
            'monthly' => array_combine(range(1, 12), array_map(
                fn (int $month) => CarbonImmutable::create($this->selectedYear, $month, 1, 0, 0, 0, 'Asia/Manila')->format('F'),
                range(1, 12),
            )),
            default => [],
        };

        $periodLabel = match ($this->periodType) {
            'yearly' => 'Year '.$this->selectedYear,
            'quarterly' => 'Q'.$this->selectedPeriod.' '.$this->selectedYear,
            default => CarbonImmutable::create($this->selectedYear, $this->selectedPeriod, 1, 0, 0, 0, 'Asia/Manila')->format('F Y'),
        };

        return view('livewire.tax-compliance.vat-summary', [
            'years' => $this->availableYears(),
            'periods' => $periods,
            'periodLabel' => $periodLabel,
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
        $inputVatBounds = app(RecordedVatSummary::class)->inputVatDateBounds();

        if ($inputVatBounds !== null) {
            $earliestYear = min($earliestYear, CarbonImmutable::parse($inputVatBounds[0], 'Asia/Manila')->year);
            $latestYear = max($latestYear, CarbonImmutable::parse($inputVatBounds[1], 'Asia/Manila')->year);
        }

        return array_reverse(range(
            min($earliestYear, $nowYear - 10, $nowYear),
            max($latestYear, $nowYear),
        ));
    }
}
