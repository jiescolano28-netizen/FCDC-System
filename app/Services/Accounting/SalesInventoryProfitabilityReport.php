<?php

namespace App\Services\Accounting;

use App\Models\Inventory;
use App\Models\PosTransaction;
use App\Models\ReworkingCostRecord;
use Carbon\CarbonImmutable;

class SalesInventoryProfitabilityReport
{
    /**
     * Build the profitability report from persisted POS sales, inventory values,
     * and reworking cost records. All amounts are returned in centavos.
     *
     * @return array<string, mixed>
     */
    public function build(string $fromDate, string $toDate): array
    {
        $from = $this->parseDate($fromDate);
        $to = $this->parseDate($toDate);

        if (! $from || ! $to) {
            return $this->unavailable('Select a valid inclusive reporting period.');
        }

        if ($from->greaterThan($to)) {
            return $this->unavailable('The start date must be on or before the end date.');
        }

        $sales = PosTransaction::query()
            ->with(['lines' => fn ($query) => $query->orderBy('id')])
            ->where('status', 'completed')
            ->where('completed_at', '>=', $from->startOfDay()->utc())
            ->where('completed_at', '<', $to->addDay()->startOfDay()->utc())
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get();

        $saleRows = [];
        $itemRows = [];
        $salesRevenueCents = 0;
        $lineRevenueCents = 0;
        $outputVatCents = 0;
        $totalCollectedCents = 0;
        $cogsCents = 0;
        $fallbackCostLineCount = 0;

        foreach ($sales as $sale) {
            $saleRevenueCents = $this->cents($sale->subtotal);
            $saleVatCents = $this->cents($sale->vat_amount);
            $saleTotalCents = $this->cents($sale->total);
            $saleCogsCents = 0;
            $saleLineRevenueCents = 0;

            foreach ($sale->lines as $line) {
                $lineRevenue = $this->cents($line->line_subtotal);
                $lineCost = $line->line_cost_cents !== null
                    ? (int) $line->line_cost_cents
                    : (int) round((float) $line->unit_cost * (float) $line->quantity * 100);

                if ($line->line_cost_cents === null) {
                    $fallbackCostLineCount++;
                }

                $saleLineRevenueCents += $lineRevenue;
                $saleCogsCents += $lineCost;

                $key = implode('|', [$line->inventory_code, $line->item_name, $line->category]);
                $itemRow = $itemRows[$key] ?? [
                    'inventory_code' => $line->inventory_code,
                    'item_name' => $line->item_name,
                    'category' => $line->category,
                    'quantity' => 0.0,
                    'revenue_cents' => 0,
                    'cogs_cents' => 0,
                ];
                $itemRow['quantity'] += (float) $line->quantity;
                $itemRow['revenue_cents'] += $lineRevenue;
                $itemRow['cogs_cents'] += $lineCost;
                $itemRows[$key] = $itemRow;
            }

            $salesRevenueCents += $saleRevenueCents;
            $lineRevenueCents += $saleLineRevenueCents;
            $outputVatCents += $saleVatCents;
            $totalCollectedCents += $saleTotalCents;
            $cogsCents += $saleCogsCents;

            $saleRows[] = [
                'id' => $sale->id,
                'transaction_number' => $sale->transaction_number,
                'completed_at' => $sale->completed_at
                    ? $sale->completed_at->copy()->timezone('Asia/Manila')->format('Y-m-d g:i A')
                    : '',
                'payment_method' => $sale->payment_method,
                'revenue_cents' => $saleRevenueCents,
                'vat_cents' => $saleVatCents,
                'total_cents' => $saleTotalCents,
                'cogs_cents' => $saleCogsCents,
                'gross_profit_cents' => $saleRevenueCents - $saleCogsCents,
                'line_count' => $sale->lines->count(),
            ];
        }

        $reworkingRecords = ReworkingCostRecord::query()
            ->whereDate('record_date', '>=', $fromDate)
            ->whereDate('record_date', '<=', $toDate)
            ->orderBy('record_date')
            ->orderBy('id')
            ->get()
            ->map(fn (ReworkingCostRecord $record): array => [
                'id' => $record->id,
                'date' => $record->record_date->format('Y-m-d'),
                'project' => $record->project_name,
                'batch_number' => $record->batch_number,
                'description' => $record->description,
                'cost_cents' => $this->cents($record->total_reworking_cost),
                'input_vat_cents' => $this->cents($record->input_vat),
            ])
            ->values();

        $reworkingCostCents = (int) $reworkingRecords->sum('cost_cents');
        $reworkingInputVatCents = (int) $reworkingRecords->sum('input_vat_cents');
        $grossProfitCents = $salesRevenueCents - $cogsCents;
        $profitAfterReworkingCents = $grossProfitCents - $reworkingCostCents;

        $itemRows = collect($itemRows)
            ->map(function (array $row): array {
                $row['gross_profit_cents'] = $row['revenue_cents'] - $row['cogs_cents'];
                $row['gross_margin_percent'] = $row['revenue_cents'] > 0
                    ? round($row['gross_profit_cents'] / $row['revenue_cents'] * 100, 2)
                    : null;

                return $row;
            })
            ->sortByDesc('revenue_cents')
            ->values();

        return [
            'available' => true,
            'error' => null,
            'from' => $fromDate,
            'to' => $toDate,
            'sale_count' => count($saleRows),
            'sale_rows' => $saleRows,
            'item_rows' => $itemRows,
            'sales_revenue_cents' => $salesRevenueCents,
            'line_revenue_cents' => $lineRevenueCents,
            'line_revenue_difference_cents' => $salesRevenueCents - $lineRevenueCents,
            'output_vat_cents' => $outputVatCents,
            'total_collected_cents' => $totalCollectedCents,
            'cogs_cents' => $cogsCents,
            'gross_profit_cents' => $grossProfitCents,
            'gross_margin_percent' => $salesRevenueCents > 0
                ? round($grossProfitCents / $salesRevenueCents * 100, 2)
                : null,
            'reworking_records' => $reworkingRecords,
            'reworking_record_count' => $reworkingRecords->count(),
            'reworking_cost_cents' => $reworkingCostCents,
            'reworking_input_vat_cents' => $reworkingInputVatCents,
            'profit_after_reworking_cents' => $profitAfterReworkingCents,
            'profit_margin_percent' => $salesRevenueCents > 0
                ? round($profitAfterReworkingCents / $salesRevenueCents * 100, 2)
                : null,
            'current_inventory_cost_cents' => (int) Inventory::query()
                ->whereNotNull('carrying_value_cents')
                ->sum('carrying_value_cents'),
            'total_period_cost_cents' => $cogsCents + $reworkingCostCents,
            'fallback_cost_line_count' => $fallbackCostLineCount,
        ];
    }

    private function parseDate(string $date): ?CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');
        } catch (\Throwable) {
            return null;
        }

        return $parsed && $parsed->format('Y-m-d') === $date ? $parsed : null;
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(string $error): array
    {
        return [
            'available' => false,
            'error' => $error,
            'from' => '',
            'to' => '',
            'sale_rows' => [],
            'item_rows' => collect(),
            'reworking_records' => collect(),
        ];
    }
}
