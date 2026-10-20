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

    public function forDateRange(string $startDate, string $endDate): array
    {
        $records = $this->recordsForDateRange($startDate, $endDate)
            ->get(['taxable_sales', 'output_vat', 'total']);
        $inputVatRecords = $this->inputVatRecordsForDateRange($startDate, $endDate);
        $inputVat = $this->inputVatSummary($inputVatRecords);

        $taxableSales = 0;
        $outputVat = 0;
        $totalSales = 0;

        foreach ($records as $record) {
            $taxableSales += (int) round((float) $record->taxable_sales * 100);
            $outputVat += (int) round((float) $record->output_vat * 100);
            $totalSales += (int) round((float) $record->total * 100);
        }

        $inputVatCents = (int) round((float) $inputVat['input_vat'] * 100);
        $deductionsApplied = $inputVatCents;

        return [
            'record_count' => $records->count(),
            'taxable_sales' => $this->formatCents($taxableSales),
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

    private function formatCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
