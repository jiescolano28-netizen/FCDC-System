<?php

namespace App\Services;

use App\Models\PosVatRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

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

        $taxableSales = 0;
        $outputVat = 0;
        $totalSales = 0;

        foreach ($records as $record) {
            $taxableSales += (int) round((float) $record->taxable_sales * 100);
            $outputVat += (int) round((float) $record->output_vat * 100);
            $totalSales += (int) round((float) $record->total * 100);
        }

        $deductionsApplied = 0;

        return [
            'record_count' => $records->count(),
            'taxable_sales' => $this->formatCents($taxableSales),
            'output_vat' => $this->formatCents($outputVat),
            'total_sales' => $this->formatCents($totalSales),
            'input_vat_status' => 'not captured',
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

    private function formatCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
