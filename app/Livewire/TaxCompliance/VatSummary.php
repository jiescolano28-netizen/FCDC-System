<?php

namespace App\Livewire\TaxCompliance;

use App\Models\PosVatRecord;
use App\Models\OperatingCostBatchRecord;
use App\Models\OperatingCostRecord;
use App\Services\RecordedVatSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
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

        [$periodStart, $periodEnd] = $this->selectedDateRange();
        $operatingCostVatRecords = Schema::hasTable('operating_cost_batch_records')
            ? OperatingCostBatchRecord::query()
                ->whereBetween('record_date', [$periodStart, $periodEnd])
                ->get(['batch_number', 'input_vat'])
            : collect();
        $operatingExpenseVatRecords = Schema::hasTable('operating_cost_records')
            && Schema::hasColumn('operating_cost_records', 'input_vat')
            ? OperatingCostRecord::query()
                ->whereBetween('expense_date', [$periodStart, $periodEnd])
                ->get(['input_vat'])
            : collect();
        $batchInputVatCents = (int) $operatingCostVatRecords->sum(
            fn (OperatingCostBatchRecord $record): int => (int) round((float) $record->input_vat * 100),
        );
        $expenseInputVatCents = (int) $operatingExpenseVatRecords->sum(
            fn (OperatingCostRecord $record): int => (int) round((float) $record->input_vat * 100),
        );
        $operatingCostInputVatCents = $batchInputVatCents + $expenseInputVatCents;
        $reworkingInputVatCents = (int) round((float) $summary['input_vat'] * 100);
        $combinedInputVatCents = $reworkingInputVatCents + $operatingCostInputVatCents;
        $summary['input_vat_reworking'] = number_format($reworkingInputVatCents / 100, 2, '.', '');
        $summary['input_vat_operating_cost'] = number_format($operatingCostInputVatCents / 100, 2, '.', '');
        $summary['input_vat_operating_batch'] = number_format($batchInputVatCents / 100, 2, '.', '');
        $summary['input_vat_operating_expenses'] = number_format($expenseInputVatCents / 100, 2, '.', '');
        $summary['operating_cost_input_vat_record_count'] = $operatingCostVatRecords->count() + $operatingExpenseVatRecords->count();
        $summary['input_vat'] = number_format($combinedInputVatCents / 100, 2, '.', '');
        $summary['input_vat_record_count'] += $operatingCostVatRecords->count() + $operatingExpenseVatRecords->count();
        $summary['input_vat_batch_count'] += $operatingCostVatRecords->pluck('batch_number')->unique()->count();
        $summary['input_vat_status'] = $summary['input_vat_record_count'] > 0 ? 'captured' : 'not captured';
        $summary['deductions_applied'] = number_format($combinedInputVatCents / 100, 2, '.', '');
        $summary['vat_payable_estimate'] = number_format(
            ((int) round((float) $summary['output_vat'] * 100) - $combinedInputVatCents) / 100,
            2,
            '.',
        );

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

        if (Schema::hasTable('operating_cost_batch_records')) {
            $operatingCostBounds = OperatingCostBatchRecord::query()
                ->selectRaw('MIN(record_date) as earliest, MAX(record_date) as latest')
                ->first();

            if ($operatingCostBounds->earliest) {
                $earliestYear = min($earliestYear, CarbonImmutable::parse($operatingCostBounds->earliest, 'Asia/Manila')->year);
                $latestYear = max($latestYear, CarbonImmutable::parse($operatingCostBounds->latest, 'Asia/Manila')->year);
            }
        }

        if (Schema::hasTable('operating_cost_records')) {
            $expenseBounds = OperatingCostRecord::query()
                ->selectRaw('MIN(expense_date) as earliest, MAX(expense_date) as latest')
                ->first();

            if ($expenseBounds->earliest) {
                $earliestYear = min($earliestYear, CarbonImmutable::parse($expenseBounds->earliest, 'Asia/Manila')->year);
                $latestYear = max($latestYear, CarbonImmutable::parse($expenseBounds->latest, 'Asia/Manila')->year);
            }
        }

        return array_reverse(range(
            min($earliestYear, $nowYear - 10, $nowYear),
            max($latestYear, $nowYear),
        ));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function selectedDateRange(): array
    {
        if ($this->periodType === 'yearly') {
            $start = CarbonImmutable::createSafe($this->selectedYear, 1, 1, 0, 0, 0, 'Asia/Manila');

            return [$start->toDateString(), $start->endOfYear()->toDateString()];
        }

        if ($this->periodType === 'quarterly') {
            $start = CarbonImmutable::createSafe($this->selectedYear, (($this->selectedPeriod - 1) * 3) + 1, 1, 0, 0, 0, 'Asia/Manila');

            return [$start->toDateString(), $start->addMonths(2)->endOfMonth()->toDateString()];
        }

        $start = CarbonImmutable::createSafe($this->selectedYear, $this->selectedPeriod, 1, 0, 0, 0, 'Asia/Manila');

        return [$start->toDateString(), $start->endOfMonth()->toDateString()];
    }
}
