<?php

namespace App\Services;

use App\Models\PosVatRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RecordedVatSummary
{
    public function forMonth(int $year, int $month): array
    {
        $start = CarbonImmutable::createSafe($year, $month, 1, 0, 0, 0, 'Asia/Manila');

        return $this->forDateRange($start->toDateString(), $start->endOfMonth()->toDateString());
    }

    public function forQuarter(int $year, int $quarter): array
    {
        abort_unless($quarter >= 1 && $quarter <= 4, 404);

        $start = CarbonImmutable::createSafe($year, (($quarter - 1) * 3) + 1, 1, 0, 0, 0, 'Asia/Manila');

        return $this->forDateRange($start->toDateString(), $start->addMonths(2)->endOfMonth()->toDateString());
    }

    public function forYear(int $year): array
    {
        $start = CarbonImmutable::createSafe($year, 1, 1, 0, 0, 0, 'Asia/Manila');

        return $this->forDateRange($start->toDateString(), $start->endOfYear()->toDateString());
    }

    public function forDateRange(string $startDate, string $endDate): array
    {
        $records = $this->recordsForDateRange($startDate, $endDate)
            ->get(['taxable_sales', 'zero_rated_sales', 'exempt_sales', 'non_vat_sales', 'output_vat', 'total']);

        return $this->summarizeRecords(
            $records,
            $this->inputVatRecordsForDateRange($startDate, $endDate),
        );
    }

    /**
     * Monthly rows for every month that overlaps the selected date range.
     *
     * @return array<int, array{key: string, label: string, start_date: string, end_date: string, summary: array}>
     */
    public function monthlyBreakdown(string $startDate, string $endDate): array
    {
        return $this->periodBreakdown($startDate, $endDate, true);
    }

    /**
     * Quarterly rows for every quarter that overlaps the selected date range.
     *
     * @return array<int, array{key: string, label: string, start_date: string, end_date: string, summary: array}>
     */
    public function quarterlyBreakdown(string $startDate, string $endDate): array
    {
        return $this->periodBreakdown($startDate, $endDate, false);
    }

    public function recordsForDateRange(string $startDate, string $endDate): Builder
    {
        $start = CarbonImmutable::parse($startDate, 'Asia/Manila')->startOfDay();
        $end = CarbonImmutable::parse($endDate, 'Asia/Manila')->startOfDay();
        abort_unless($end->greaterThanOrEqualTo($start), 404);

        return PosVatRecord::query()
            ->whereHas('posTransaction', fn ($query) => $query
                ->where('status', 'completed')
                ->where('completed_at', '>=', $start->utc())
                ->where('completed_at', '<', $end->addDay()->utc()));
    }

    public function inputVatRecordsForDateRange(string $startDate, string $endDate, string $search = ''): Collection
    {
        return app(RecordedInputVat::class)->recordsForDateRange($startDate, $endDate, $search);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public function inputVatDateBounds(): ?array
    {
        return app(RecordedInputVat::class)->dateBounds();
    }

    private function inputVatSummary(Collection $records): array
    {
        return app(RecordedInputVat::class)->summarize($records);
    }

    /**
     * @return array<int, array{key: string, label: string, start_date: string, end_date: string, summary: array}>
     */
    private function periodBreakdown(string $startDate, string $endDate, bool $monthly): array
    {
        $start = CarbonImmutable::parse($startDate, 'Asia/Manila')->startOfDay();
        $end = CarbonImmutable::parse($endDate, 'Asia/Manila')->startOfDay();
        abort_unless($end->greaterThanOrEqualTo($start), 404);

        $records = $this->recordsForDateRange($startDate, $endDate)
            ->get(['taxable_sales', 'zero_rated_sales', 'exempt_sales', 'non_vat_sales', 'output_vat', 'total', 'completed_at']);
        $inputVatRecords = $this->inputVatRecordsForDateRange($startDate, $endDate);

        $periodKey = function ($date) use ($monthly): string {
            return $monthly
                ? $date->format('Y-m')
                : $date->format('Y').'-Q'.$date->quarter;
        };

        $recordsByPeriod = $records->groupBy(
            fn ($record): string => $periodKey($record->completed_at->copy()->timezone('Asia/Manila')),
        );
        $inputVatByPeriod = $inputVatRecords->groupBy(
            fn (array $record): string => $periodKey(CarbonImmutable::parse($record['date'], 'Asia/Manila')),
        );

        $periods = [];
        $cursor = $monthly ? $start->startOfMonth() : $start->startOfQuarter();

        while ($cursor->lessThanOrEqualTo($end)) {
            $periodEnd = $monthly ? $cursor->endOfMonth() : $cursor->endOfQuarter();
            $key = $periodKey($cursor);

            $periods[] = [
                'key' => $key,
                'label' => $monthly
                    ? $cursor->format('F Y')
                    : 'Q'.$cursor->quarter.' '.$cursor->format('Y'),
                'start_date' => ($cursor->lessThan($start) ? $start : $cursor)->toDateString(),
                'end_date' => ($periodEnd->greaterThan($end) ? $end : $periodEnd)->toDateString(),
                'summary' => $this->summarizeRecords(
                    $recordsByPeriod->get($key, collect()),
                    $inputVatByPeriod->get($key, collect()),
                ),
            ];

            $cursor = $periodEnd->addDay()->startOfDay();
        }

        return $periods;
    }

    private function summarizeRecords(Collection $records, Collection $inputVatRecords): array
    {
        $inputVat = $this->inputVatSummary($inputVatRecords);

        $taxableSales = 0;
        $zeroRatedSales = 0;
        $exemptSales = 0;
        $nonVatSales = 0;
        $outputVat = 0;
        $totalSales = 0;

        foreach ($records as $record) {
            $taxableSales += (int) round((float) $record->taxable_sales * 100);
            $zeroRatedSales += (int) round((float) $record->zero_rated_sales * 100);
            $exemptSales += (int) round((float) $record->exempt_sales * 100);
            $nonVatSales += (int) round((float) $record->non_vat_sales * 100);
            $outputVat += (int) round((float) $record->output_vat * 100);
            $totalSales += (int) round((float) $record->total * 100);
        }

        $inputVatCents = (int) round((float) $inputVat['input_vat'] * 100);
        $deductionsApplied = $inputVatCents;

        return [
            'record_count' => $records->count(),
            'taxable_sales' => $this->formatCents($taxableSales),
            'zero_rated_sales' => $this->formatCents($zeroRatedSales),
            'exempt_sales' => $this->formatCents($exemptSales),
            'non_vat_sales' => $this->formatCents($nonVatSales),
            'output_vat' => $this->formatCents($outputVat),
            'total_sales' => $this->formatCents($totalSales),
            'input_vat' => $this->formatCents($inputVatCents),
            'input_vat_record_count' => $inputVat['record_count'],
            'input_vat_batch_count' => $inputVat['batch_count'],
            'input_vat_reworking_cost' => $inputVat['total_reworking_cost'],
            'input_vat_status' => $inputVat['record_count'] > 0 ? 'captured' : 'not captured',
            'deductions_applied' => $this->formatCents($deductionsApplied),
            'vat_payable_estimate' => $this->formatCents($outputVat - $deductionsApplied),
        ];
    }

    private function formatCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
