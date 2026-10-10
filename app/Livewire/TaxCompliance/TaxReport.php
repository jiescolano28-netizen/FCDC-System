<?php

namespace App\Livewire\TaxCompliance;

use App\Models\PosVatRecord;
use App\Services\RecordedVatSummary;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TaxReport extends Component
{
    use WithPagination;

    public string $periodType = 'monthly';

    public int $selectedYear;

    public int $selectedPeriod;

    public string $startDate;

    public string $endDate;

    public ?int $selectedRecordId = null;

    public function mount(): void
    {
        $today = now('Asia/Manila');
        $this->selectedYear = $today->year;
        $this->selectedPeriod = $today->month;
        $this->startDate = $today->copy()->startOfMonth()->toDateString();
        $this->endDate = $today->copy()->endOfMonth()->toDateString();
    }

    public function updatedPeriodType(): void
    {
        if (! in_array($this->periodType, ['monthly', 'quarterly', 'custom'], true)) {
            $this->periodType = 'monthly';
        }

        if ($this->periodType === 'quarterly') {
            $this->selectedPeriod = intdiv($this->selectedPeriod - 1, 3) + 1;
        } elseif ($this->periodType === 'monthly') {
            $this->selectedPeriod = (($this->selectedPeriod - 1) * 3) + 1;
        }

        $this->resetPage();
    }

    public function updatedSelectedPeriod(): void
    {
        $maxPeriod = $this->periodType === 'quarterly' ? 4 : 12;
        if ($this->selectedPeriod < 1 || $this->selectedPeriod > $maxPeriod) {
            $this->selectedPeriod = $this->periodType === 'quarterly'
                ? now('Asia/Manila')->quarter
                : now('Asia/Manila')->month;
        }

        $this->resetPage();
    }

    public function updatedSelectedYear(): void
    {
        if (! in_array($this->selectedYear, $this->availableYears(), true)) {
            $this->selectedYear = now('Asia/Manila')->year;
        }

        $this->resetPage();
    }

    public function updatedStartDate(): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->startDate) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->endDate) === 1
            && $this->startDate > $this->endDate) {
            $this->endDate = $this->startDate;
        }

        $this->resetPage();
    }

    public function updatedEndDate(): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->startDate) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->endDate) === 1
            && $this->endDate < $this->startDate) {
            $this->startDate = $this->endDate;
        }

        $this->resetPage();
    }

    public function viewRecord(int $recordId): void
    {
        $this->authorizePermission();
        $this->selectedRecordId = $this->reportQuery()
            ->whereKey($recordId)
            ->value('id');
    }

    public function closeDetails(): void
    {
        $this->authorizePermission();
        $this->selectedRecordId = null;
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorizePermission();
        [$startDate, $endDate] = $this->selectedDateRange();
        $summary = app(RecordedVatSummary::class)->forDateRange($startDate, $endDate);
        $generatedAt = CarbonImmutable::now('Asia/Manila');
        $fileName = 'vat-report-'.$startDate.'-to-'.$endDate.'.csv';

        return response()->streamDownload(function () use ($startDate, $endDate, $summary, $generatedAt): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Company', 'Fabellion Construction and Development Corp.']);
            fputcsv($output, ['Reporting period', $this->periodLabel($startDate, $endDate)]);
            fputcsv($output, ['Date range', $startDate.' through '.$endDate]);
            fputcsv($output, ['Reporting timezone', 'Asia/Manila']);
            fputcsv($output, ['Generated at', $generatedAt->format('Y-m-d H:i:s P')]);
            fputcsv($output, ['Coverage', 'Recorded POS VAT only']);
            fputcsv($output, ['Taxable sales', $summary['taxable_sales']]);
            fputcsv($output, ['Output VAT', $summary['output_vat']]);
            fputcsv($output, ['VAT-inclusive total sales', $summary['total_sales']]);
            fputcsv($output, []);
            fputcsv($output, [
                'Transaction number',
                'Completion date/time (Asia/Manila)',
                'Customer',
                'Saved item lines',
                'Taxable sales',
                'Output VAT',
                'VAT-inclusive total',
            ]);

            $this->reportQuery()
                ->with('posTransaction.lines')
                ->orderBy('completed_at')
                ->orderBy('id')
                ->chunk(500, function ($records) use ($output): void {
                    foreach ($records as $record) {
                        $sale = $record->posTransaction;
                        $items = $sale->lines->map(fn ($line) => sprintf(
                            '%s (%.2f %s; unit ₱%.2f; line taxable ₱%.2f; VAT ₱%.2f; total ₱%.2f)',
                            $line->item_name,
                            (float) $line->quantity,
                            $line->unit,
                            (float) $line->selling_price,
                            (float) $line->line_subtotal,
                            (float) $line->vat_amount,
                            (float) $line->line_total,
                        ))->implode('; ');

                        fputcsv($output, [
                            $sale->transaction_number,
                            $record->completed_at->copy()->timezone('Asia/Manila')->format('Y-m-d H:i:s'),
                            $sale->customer_name ?: 'Walk-in customer',
                            $items,
                            $record->taxable_sales,
                            $record->output_vat,
                            $record->total,
                        ]);
                    }
                });

            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $this->authorizePermission();
        [$startDate, $endDate] = $this->selectedDateRange();
        $summary = app(RecordedVatSummary::class)->forDateRange($startDate, $endDate);
        $records = $this->reportQuery()
            ->with('posTransaction')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate(20);
        $printRecords = $this->reportQuery()
            ->with('posTransaction.lines')
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get();

        $selectedRecord = $this->selectedRecordId
            ? $this->reportQuery()->with('posTransaction.lines')->find($this->selectedRecordId)
            : null;
        $generatedAt = CarbonImmutable::now('Asia/Manila');

        return view('livewire.tax-compliance.tax-report', [
            'years' => $this->availableYears(),
            'periods' => $this->periodType === 'quarterly'
                ? [1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4']
                : array_combine(range(1, 12), array_map(
                    fn (int $month) => CarbonImmutable::create($this->selectedYear, $month, 1, 0, 0, 0, 'Asia/Manila')->format('F'),
                    range(1, 12),
                )),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'periodLabel' => $this->periodLabel($startDate, $endDate),
            'generatedAt' => $generatedAt,
            'summary' => $summary,
            'records' => $records,
            'printRecords' => $printRecords,
            'selectedRecord' => $selectedRecord,
        ])->layout('layouts.app', ['title' => 'Tax Report']);
    }

    private function reportQuery(): Builder
    {
        [$startDate, $endDate] = $this->selectedDateRange();

        return app(RecordedVatSummary::class)->recordsForDateRange($startDate, $endDate);
    }

    private function selectedDateRange(): array
    {
        if ($this->periodType === 'custom') {
            $startDate = $this->validDate($this->startDate);
            $endDate = $this->validDate($this->endDate);
        } elseif ($this->periodType === 'quarterly') {
            abort_unless($this->selectedPeriod >= 1 && $this->selectedPeriod <= 4, 404);
            $start = CarbonImmutable::createSafe($this->selectedYear, (($this->selectedPeriod - 1) * 3) + 1, 1, 0, 0, 0, 'Asia/Manila');
            $startDate = $start->toDateString();
            $endDate = $start->addMonths(2)->endOfMonth()->toDateString();
        } else {
            abort_unless($this->selectedPeriod >= 1 && $this->selectedPeriod <= 12, 404);
            $start = CarbonImmutable::createSafe($this->selectedYear, $this->selectedPeriod, 1, 0, 0, 0, 'Asia/Manila');
            $startDate = $start->toDateString();
            $endDate = $start->endOfMonth()->toDateString();
        }

        abort_unless($endDate >= $startDate, 404);

        return [$startDate, $endDate];
    }

    private function validDate(string $date): string
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1, 404);

        try {
            [$year, $month, $day] = array_map('intval', explode('-', $date));

            return CarbonImmutable::createSafe($year, $month, $day, 0, 0, 0, 'Asia/Manila')
                ->toDateString();
        } catch (Throwable) {
            abort(404);
        }
    }

    private function periodLabel(string $startDate, string $endDate): string
    {
        if ($this->periodType === 'custom') {
            return $startDate.' through '.$endDate;
        }

        return $this->periodType === 'quarterly'
            ? 'Q'.$this->selectedPeriod.' '.$this->selectedYear
            : CarbonImmutable::parse($startDate, 'Asia/Manila')->format('F Y');
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

        return array_reverse(range(min($earliestYear, $nowYear - 10), max($latestYear, $nowYear)));
    }

    private function authorizePermission(): void
    {
        abort_unless(auth()->user()?->can('tax.view'), 403);
    }
}
