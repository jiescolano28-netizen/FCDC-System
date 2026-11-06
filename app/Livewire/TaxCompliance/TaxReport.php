<?php

namespace App\Livewire\TaxCompliance;

use App\Models\PosVatRecord;
use App\Models\OperatingCostBatchRecord;
use App\Models\OperatingCostRecord;
use App\Services\RecordedVatSummary;
use App\Support\TaxClassification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
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
        $summary = $this->summaryForRange($startDate, $endDate);
        $monthlyBreakdown = $this->breakdownWithOperatingInputVat(
            app(RecordedVatSummary::class)->monthlyBreakdown($startDate, $endDate),
        );
        $quarterlyBreakdown = $this->breakdownWithOperatingInputVat(
            app(RecordedVatSummary::class)->quarterlyBreakdown($startDate, $endDate),
        );
        $generatedAt = CarbonImmutable::now('Asia/Manila');
        $fileName = 'vat-report-'.$startDate.'-to-'.$endDate.'.csv';

        return response()->streamDownload(function () use ($startDate, $endDate, $summary, $monthlyBreakdown, $quarterlyBreakdown, $generatedAt): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Company', 'Fabellion Construction and Development Corp.']);
            fputcsv($output, ['Reporting period', $this->periodLabel($startDate, $endDate)]);
            fputcsv($output, ['Date range', $startDate.' through '.$endDate]);
            fputcsv($output, ['Reporting timezone', 'Asia/Manila']);
            fputcsv($output, ['Generated at', $generatedAt->format('Y-m-d H:i:s P')]);
            fputcsv($output, ['Coverage', 'Output VAT: recorded POS sales; input VAT: Reworking Cost and Operating Cost records']);
            fputcsv($output, ['Taxable sales', $summary['taxable_sales']]);
            fputcsv($output, ['Zero-rated sales', $summary['zero_rated_sales']]);
            fputcsv($output, ['VAT-exempt sales', $summary['exempt_sales']]);
            fputcsv($output, ['Non-VAT / outside scope sales', $summary['non_vat_sales']]);
            fputcsv($output, ['Output VAT', $summary['output_vat']]);
            fputcsv($output, ['VAT-inclusive total sales', $summary['total_sales']]);
            fputcsv($output, ['Reworking cost total (reworking cost records)', $summary['input_vat_reworking_cost']]);
            fputcsv($output, ['Input VAT (reworking cost records)', $summary['input_vat_reworking']]);
            fputcsv($output, ['Input VAT (operating cost records)', $summary['input_vat_operating_cost']]);
            fputcsv($output, ['Total VAT Input', $summary['input_vat']]);
            fputcsv($output, ['Net VAT payable estimate', $summary['vat_payable_estimate']]);
            fputcsv($output, []);
            $this->writeBreakdownCsv($output, 'Monthly sales summary', $monthlyBreakdown);
            fputcsv($output, []);
            $this->writeBreakdownCsv($output, 'Quarterly sales summary', $quarterlyBreakdown);
            fputcsv($output, []);
            fputcsv($output, [
                'Transaction number',
                'Completion date/time (Asia/Manila)',
                'Customer',
                'Tax classification',
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
                            '%s (%s; %.2f %s; unit ₱%.2f; line taxable ₱%.2f; VAT ₱%.2f; total ₱%.2f)',
                            $line->item_name,
                            TaxClassification::label($line->tax_classification),
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
                            TaxClassification::label($record->tax_classification),
                            $items,
                            $record->taxable_sales,
                            $record->output_vat,
                            $record->total,
                        ]);
                    }
                });

            $inputVatRecords = $this->inputVatRecordsForRange($startDate, $endDate);

            if ($inputVatRecords->isNotEmpty()) {
                fputcsv($output, []);
                fputcsv($output, ['Input VAT records from reworking and operating cost records']);
                fputcsv($output, [
                    'Source',
                    'Reference',
                    'Project / category',
                    'Record date',
                    'Description',
                    'Recorded cost',
                    'Input VAT',
                ]);

                foreach ($inputVatRecords as $record) {
                    fputcsv($output, [
                        $record['source_type'],
                        $record['batch_number'],
                        $record['project'],
                        $record['date'],
                        $record['description'],
                        $record['total_recorded_cost'],
                        $record['input_vat'],
                    ]);
                }

                fputcsv($output, ['Total VAT Input', $summary['input_vat']]);
            }

            fclose($output);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $this->authorizePermission();
        [$startDate, $endDate] = $this->selectedDateRange();
        $summary = $this->summaryForRange($startDate, $endDate);
        $monthlyBreakdown = $this->breakdownWithOperatingInputVat(
            app(RecordedVatSummary::class)->monthlyBreakdown($startDate, $endDate),
        );
        $quarterlyBreakdown = $this->breakdownWithOperatingInputVat(
            app(RecordedVatSummary::class)->quarterlyBreakdown($startDate, $endDate),
        );
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
        $inputVatRecords = $this->inputVatRecordsForRange($startDate, $endDate);
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
            'monthlyBreakdown' => $monthlyBreakdown,
            'quarterlyBreakdown' => $quarterlyBreakdown,
            'records' => $records,
            'printRecords' => $printRecords,
            'selectedRecord' => $selectedRecord,
            'inputVatRecords' => $inputVatRecords,
        ])->layout('layouts.app', ['title' => 'Tax Report']);
    }

    /**
     * @param  resource  $output
     * @param  array<int, array{label: string, start_date: string, end_date: string, summary: array}>  $rows
     */
    private function writeBreakdownCsv($output, string $heading, array $rows): void
    {
        fputcsv($output, [$heading]);
        fputcsv($output, [
            'Period',
            'Date range',
            'Transactions',
            'VATable sales',
            'Zero-rated sales',
            'VAT-exempt sales',
            'Non-VAT / outside scope',
            'Output VAT',
            'Input VAT',
            'Estimated VAT payable',
            'VAT-inclusive total',
        ]);

        foreach ($rows as $row) {
            $summary = $row['summary'];
            fputcsv($output, [
                $row['label'],
                $row['start_date'].' through '.$row['end_date'],
                $summary['record_count'],
                $summary['taxable_sales'],
                $summary['zero_rated_sales'],
                $summary['exempt_sales'],
                $summary['non_vat_sales'],
                $summary['output_vat'],
                $summary['input_vat'],
                $summary['vat_payable_estimate'],
                $summary['total_sales'],
            ]);
        }
    }

    private function reportQuery(): Builder
    {
        [$startDate, $endDate] = $this->selectedDateRange();

        return app(RecordedVatSummary::class)->recordsForDateRange($startDate, $endDate);
    }

    private function inputVatRecordsForRange(string $startDate, string $endDate): Collection
    {
        $reworkingRecords = app(RecordedVatSummary::class)
            ->inputVatRecordsForDateRange($startDate, $endDate)
            ->map(fn (array $record): array => [
                ...$record,
                'source_type' => 'Reworking Cost',
                'total_recorded_cost' => $record['total_reworking_cost'],
            ]);
        $operatingRecords = collect();

        if (Schema::hasTable('operating_cost_batch_records')) {
            $operatingRecords = $operatingRecords->concat(
                OperatingCostBatchRecord::query()
                    ->whereBetween('record_date', [$startDate, $endDate])
                    ->orderBy('record_date')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (OperatingCostBatchRecord $record): array => [
                        'source_key' => 'operating-batch:'.$record->id,
                        'source_type' => 'Operating Cost Batch',
                        'batch_number' => $record->batch_number,
                        'project' => $record->project_name,
                        'date' => $record->record_date->format('Y-m-d'),
                        'description' => $record->description,
                        'total_recorded_cost' => (string) $record->total_operating_cost,
                        'input_vat' => (string) $record->input_vat,
                    ]),
            );
        }

        if (Schema::hasTable('operating_cost_records') && Schema::hasColumn('operating_cost_records', 'input_vat')) {
            $operatingRecords = $operatingRecords->concat(
                OperatingCostRecord::query()
                    ->whereBetween('expense_date', [$startDate, $endDate])
                    ->orderBy('expense_date')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (OperatingCostRecord $record): array => [
                        'source_key' => 'operating-expense:'.$record->id,
                        'source_type' => 'Operating Cost Expense',
                        'batch_number' => $record->reference_number,
                        'project' => $record->expense_category,
                        'date' => $record->expense_date->format('Y-m-d'),
                        'description' => $record->description,
                        'total_recorded_cost' => (string) $record->amount,
                        'input_vat' => (string) $record->input_vat,
                    ]),
            );
        }

        return $reworkingRecords
            ->concat($operatingRecords)
            ->sortBy(fn (array $record): string => $record['date'].'-'.$record['source_key'])
            ->values();
    }

    private function summaryForRange(string $startDate, string $endDate): array
    {
        $summary = app(RecordedVatSummary::class)->forDateRange($startDate, $endDate);
        $operatingRecords = $this->inputVatRecordsForRange($startDate, $endDate)
            ->reject(fn (array $record): bool => $record['source_type'] === 'Reworking Cost');
        $operatingCents = 0;
        $operatingCostCents = 0;
        $batchCents = 0;
        $expenseCents = 0;

        foreach ($operatingRecords as $record) {
            $vatCents = (int) round((float) $record['input_vat'] * 100);
            $costCents = (int) round((float) $record['total_recorded_cost'] * 100);
            $operatingCents += $vatCents;
            $operatingCostCents += $costCents;

            if ($record['source_type'] === 'Operating Cost Batch') {
                $batchCents += $vatCents;
            } else {
                $expenseCents += $vatCents;
            }
        }

        $reworkingInputVatCents = (int) round((float) $summary['input_vat'] * 100);
        $totalInputVatCents = $reworkingInputVatCents + $operatingCents;
        $summary['input_vat_reworking'] = number_format($reworkingInputVatCents / 100, 2, '.', '');
        $summary['input_vat_operating_cost'] = number_format($operatingCents / 100, 2, '.', '');
        $summary['input_vat_operating_batch'] = number_format($batchCents / 100, 2, '.', '');
        $summary['input_vat_operating_expenses'] = number_format($expenseCents / 100, 2, '.', '');
        $summary['input_vat_recorded_cost'] = number_format(
            ((int) round((float) $summary['input_vat_reworking_cost'] * 100) + $operatingCostCents) / 100,
            2,
            '.',
            '',
        );
        $summary['input_vat_record_count'] += $operatingRecords->count();
        $summary['input_vat_batch_count'] += $operatingRecords
            ->where('source_type', 'Operating Cost Batch')
            ->pluck('batch_number')
            ->unique()
            ->count();
        $summary['input_vat'] = number_format($totalInputVatCents / 100, 2, '.', '');
        $summary['input_vat_status'] = $summary['input_vat_record_count'] > 0 ? 'captured' : 'not captured';
        $summary['deductions_applied'] = number_format($totalInputVatCents / 100, 2, '.', '');
        $summary['vat_payable_estimate'] = number_format(
            ((int) round((float) $summary['output_vat'] * 100) - $totalInputVatCents) / 100,
            2,
            '.',
            '',
        );

        return $summary;
    }

    private function breakdownWithOperatingInputVat(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['summary'] = $this->summaryForRange($row['start_date'], $row['end_date']);
        }
        unset($row);

        return $rows;
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
        $inputVatBounds = app(RecordedVatSummary::class)->inputVatDateBounds();

        if ($inputVatBounds !== null) {
            $earliestYear = min($earliestYear, CarbonImmutable::parse($inputVatBounds[0], 'Asia/Manila')->year);
            $latestYear = max($latestYear, CarbonImmutable::parse($inputVatBounds[1], 'Asia/Manila')->year);
        }

        $operatingDates = collect();
        if (Schema::hasTable('operating_cost_batch_records')) {
            $batchBounds = OperatingCostBatchRecord::query()
                ->selectRaw('MIN(record_date) as earliest, MAX(record_date) as latest')
                ->first();
            if ($batchBounds->earliest) {
                $operatingDates->push($batchBounds->earliest, $batchBounds->latest);
            }
        }
        if (Schema::hasTable('operating_cost_records') && Schema::hasColumn('operating_cost_records', 'input_vat')) {
            $expenseBounds = OperatingCostRecord::query()
                ->selectRaw('MIN(expense_date) as earliest, MAX(expense_date) as latest')
                ->first();
            if ($expenseBounds->earliest) {
                $operatingDates->push($expenseBounds->earliest, $expenseBounds->latest);
            }
        }
        if ($operatingDates->isNotEmpty()) {
            $earliestYear = min($earliestYear, CarbonImmutable::parse($operatingDates->min(), 'Asia/Manila')->year);
            $latestYear = max($latestYear, CarbonImmutable::parse($operatingDates->max(), 'Asia/Manila')->year);
        }

        return array_reverse(range(min($earliestYear, $nowYear - 10), max($latestYear, $nowYear)));
    }

    private function authorizePermission(): void
    {
        abort_unless(auth()->user()?->can('tax.view'), 403);
    }
}
