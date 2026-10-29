<?php

namespace App\Livewire\Dashboard;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use App\Models\Inventory;
use App\Models\PosTransaction;
use App\Models\PosTransactionLine;
use App\Models\PosVatRecord;
use App\Services\Accounting\OpeningBooksService;
use App\Services\RecordedVatSummary;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsPage extends Component
{
    public string $activeReport = 'sales';

    public string $period = 'month';

    public string $fromDate = '';

    public string $toDate = '';

    private const REVENUE_CLASSIFICATIONS = ['sales', 'contra_revenue', 'other_income'];

    private const EXPENSE_CLASSIFICATIONS = ['cost_of_goods_sold', 'operating_expense', 'other_expense'];

    public function mount(): void
    {
        $today = CarbonImmutable::now('Asia/Manila');
        $this->fromDate = $today->startOfMonth()->toDateString();
        $this->toDate = $today->toDateString();
    }

    public function setPeriod(string $period): void
    {
        if (! in_array($period, ['today', 'week', 'month', 'year', 'custom'], true)) {
            return;
        }

        $this->period = $period;
        if ($period === 'custom') {
            return;
        }

        $today = CarbonImmutable::now('Asia/Manila');
        [$start, $end] = match ($period) {
            'today' => [$today->startOfDay(), $today->endOfDay()],
            'week' => [$today->startOfWeek(), $today->endOfDay()],
            'year' => [$today->startOfYear(), $today->endOfDay()],
            default => [$today->startOfMonth(), $today->endOfDay()],
        };
        $this->fromDate = $start->toDateString();
        $this->toDate = $end->toDateString();
    }

    public function updatedFromDate(): void
    {
        $this->period = 'custom';
    }

    public function updatedToDate(): void
    {
        $this->period = 'custom';
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorizeReport();
        $data = $this->reportData();
        $section = in_array($this->activeReport, ['sales', 'inventory', 'accounting', 'vat'], true) ? $this->activeReport : 'sales';
        $filename = sprintf('fcdc-%s-report-%s-to-%s.csv', $section, $data['from'], $data['to']);

        return response()->streamDownload(function () use ($section, $data): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['FCDC '.$this->sectionLabel($section).' report']);
            fputcsv($output, ['Date range', $data['from'].' through '.$data['to']]);
            fputcsv($output, ['Timezone', 'Asia/Manila']);

            match ($section) {
                'sales' => $this->writeSalesCsv($output, $data['from'], $data['to']),
                'inventory' => $this->writeInventoryCsv($output, $data['inventory']),
                'accounting' => $this->writeAccountingCsv($output, $data['accounting']),
                'vat' => $this->writeVatCsv($output, $data['vat']),
            };

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $this->authorizeReport();
        $data = $this->reportData();

        return view('livewire.dashboard.reports-page', $data)
            ->layout('layouts.app', ['title' => 'Reports']);
    }

    private function reportData(): array
    {
        $today = CarbonImmutable::now('Asia/Manila');
        $start = $this->parseDate($this->fromDate) ?? $today->startOfMonth();
        $end = ($this->parseDate($this->toDate) ?? $today)->endOfDay();
        if ($start->gt($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }
        $this->fromDate = $start->toDateString();
        $this->toDate = $end->toDateString();
        $from = $start->toDateString();
        $to = $end->toDateString();

        $sales = $this->salesReport($start, $end);
        $inventory = $this->inventoryReport();
        $accounting = $this->accountingReport($from, $to);
        $vat = app(RecordedVatSummary::class)->forDateRange($from, $to);
        $vatRecords = app(RecordedVatSummary::class)->recordsForDateRange($from, $to)
            ->with('posTransaction')->orderByDesc('completed_at')->orderByDesc('id')->get();
        $vatByClassification = $vatRecords->groupBy('tax_classification')->map(fn ($records, $classification) => [
            'label' => ucfirst(str_replace('_', ' ', (string) $classification)),
            'total' => round($records->sum(fn (PosVatRecord $record) => (float) $record->total), 2),
        ])->values()->all();
        $vat['classifications'] = $vatByClassification;
        $vat['records'] = $vatRecords;
        $vat['coverage_note'] = 'Output VAT is from saved completed POS VAT records. Input VAT includes only saved Reworking Cost records; other supplier/input VAT is not captured here. The payable amount is an estimate, not a filed return.';

        return [
            'activeReport' => $this->activeReport,
            'period' => $this->period,
            'fromDate' => $from,
            'toDate' => $to,
            'from' => $from,
            'to' => $to,
            'sales' => $sales,
            'inventory' => $inventory,
            'accounting' => $accounting,
            'vat' => $vat,
            'reportRangeLabel' => $start->format('M j, Y').' – '.$end->format('M j, Y'),
        ];
    }

    private function salesReport(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $transactions = PosTransaction::query()->where('status', 'completed')
            ->where('completed_at', '>=', $start->utc())->where('completed_at', '<', $end->addDay()->startOfDay()->utc());
        $ids = (clone $transactions)->select('id');
        $total = (float) (clone $transactions)->sum('total');
        $count = (clone $transactions)->count();
        $items = PosTransactionLine::query()->whereIn('pos_transaction_id', $ids)
            ->selectRaw('inventory_code, item_name, category, unit, SUM(quantity) as quantity, SUM(line_total) as total')
            ->groupBy('inventory_code', 'item_name', 'category', 'unit')->orderByDesc('total')->get();
        $daily = (clone $transactions)->get(['completed_at', 'total'])
            ->groupBy(fn (PosTransaction $transaction) => $transaction->completed_at->copy()->timezone('Asia/Manila')->toDateString())
            ->map(fn ($rows) => (float) $rows->sum('total'));
        $bucketByMonth = $start->diffInDays($end) > 62;
        $trend = [];
        $cursor = $start->startOfDay();
        while ($cursor <= $end->startOfDay()) {
            $key = $bucketByMonth ? $cursor->format('Y-m') : $cursor->toDateString();
            $trend[$key] ??= ['label' => $bucketByMonth ? $cursor->format('M Y') : $cursor->format('M j'), 'total' => 0];
            $trend[$key]['total'] += (float) $daily->get($cursor->toDateString(), 0);
            $cursor = $cursor->addDay();
        }

        return [
            'total' => $total,
            'count' => $count,
            'quantity' => (float) $items->sum('quantity'),
            'average' => $count > 0 ? $total / $count : 0,
            'bestItem' => $items->first(),
            'items' => $items,
            'transactions' => (clone $transactions)->with('lines')->latest('completed_at')->latest('id')->limit(100)->get(),
            'categories' => PosTransactionLine::query()->whereIn('pos_transaction_id', $ids)
                ->selectRaw('category as label, SUM(line_total) as total')->groupBy('category')->orderByDesc('total')->get(),
            'payments' => (clone $transactions)->selectRaw('payment_method as label, COUNT(*) as count, SUM(total) as total')
                ->groupBy('payment_method')->orderByDesc('total')->get(),
            'trend' => array_values($trend),
            'transactionLimit' => (clone $transactions)->count() > 100,
        ];
    }

    private function inventoryReport(): array
    {
        $query = Inventory::query();
        $items = (clone $query)->orderBy('name')->get();
        $categories = (clone $query)->selectRaw('category as label, SUM(COALESCE(carrying_value_cents / 100.0, qty * unit_cost)) as total')
            ->groupBy('category')->orderByDesc('total')->get();
        $value = (float) (clone $query)->selectRaw('COALESCE(SUM(COALESCE(carrying_value_cents / 100.0, qty * unit_cost)), 0) as total')->value('total');

        return [
            'count' => $items->count(),
            'value' => $value,
            'inStock' => $items->filter(fn (Inventory $item) => $item->stockStatus() === Inventory::STOCK_STATUS_IN_STOCK)->count(),
            'lowStock' => $items->filter(fn (Inventory $item) => $item->stockStatus() === Inventory::STOCK_STATUS_LOW_STOCK)->count(),
            'outOfStock' => $items->filter(fn (Inventory $item) => $item->stockStatus() === Inventory::STOCK_STATUS_OUT_OF_STOCK)->count(),
            'categories' => $categories,
            'quantities' => (clone $query)->selectRaw('category, unit, SUM(qty) as quantity')
                ->groupBy('category', 'unit')->orderBy('category')->orderBy('unit')->get(),
            'items' => $items,
            'note' => 'Inventory is a current snapshot. Historical inventory values are not reconstructed from stock movements in this report.',
        ];
    }

    private function accountingReport(string $from, string $to): array
    {
        $readiness = app(OpeningBooksService::class)->readiness();
        if (! ($readiness['production_activated'] ?? false)) {
            return $this->unavailableAccounting($readiness['production'] ?? 'Accounting production is not activated.');
        }
        $cutover = AccountingJournal::query()->where('book_key', 'FCDC')->where('source_type', 'opening')
            ->where('source_id', 'FCDC')->where('status', 'posted')->value('accounting_date');
        if (! $cutover) {
            return $this->unavailableAccounting('Accounting results are unavailable until approved opening balances establish coverage.');
        }
        $cutover = CarbonImmutable::parse($cutover, 'Asia/Manila')->toDateString();
        if ($from < $cutover) {
            return $this->unavailableAccounting('Selected period begins before the approved accounting cutover ('.$cutover.'). Adjust the date range to the covered period.');
        }

        $classifications = [...self::REVENUE_CLASSIFICATIONS, ...self::EXPENSE_CLASSIFICATIONS];
        $accounts = AccountingAccount::query()->whereIn('type', ['Revenue', 'Expense'])
            ->where(fn ($query) => $query->whereIn('classification', $classifications)->orWhere('is_active', true))->get();
        if ($accounts->contains(fn (AccountingAccount $account) => $account->is_active && (
            $account->approved_at === null || ! in_array($account->classification, $classifications, true)
        ))) {
            return $this->unavailableAccounting('Accounting totals are unavailable because active Revenue/Expense accounts need approved classifications.');
        }
        $accounts = $accounts->filter(fn (AccountingAccount $account) => $account->approved_at !== null && in_array($account->classification, $classifications, true));
        if ($accounts->isEmpty()) {
            return $this->unavailableAccounting('No approved Revenue/Expense accounts are configured.');
        }

        $closingIds = AccountingJournal::query()->where('book_key', 'FCDC')->where('source_type', 'fiscal_year_closing')->pluck('id')->all();
        $excludedIds = $this->closingChainIds($closingIds);
        $lines = AccountingJournalLine::query()->with(['account', 'journal'])
            ->whereIn('accounting_account_id', $accounts->pluck('id'))
            ->whereHas('journal', function ($query) use ($from, $to, $excludedIds): void {
                $query->where('book_key', 'FCDC')->where('status', 'posted')->whereBetween('accounting_date', [$from, $to])->where('source_type', '!=', 'opening');
                if ($excludedIds !== []) {
                    $query->whereNotIn('id', $excludedIds);
                }
            })->get();

        $revenueCents = 0;
        $expenseCents = 0;
        $byDate = [];
        foreach ($lines as $line) {
            $date = $line->journal->accounting_date->toDateString();
            $byDate[$date] ??= ['revenue' => 0, 'expenses' => 0];
            if ($line->account->type === 'Revenue') {
                $amount = (int) $line->credit_cents - (int) $line->debit_cents;
                $revenueCents += $amount;
                $byDate[$date]['revenue'] += $amount;
            } else {
                $amount = (int) $line->debit_cents - (int) $line->credit_cents;
                $expenseCents += $amount;
                $byDate[$date]['expenses'] += $amount;
            }
        }

        $trend = [];
        $start = CarbonImmutable::parse($from, 'Asia/Manila');
        $end = CarbonImmutable::parse($to, 'Asia/Manila');
        $monthly = $start->diffInDays($end) > 62;
        for ($date = $start; $date <= $end; $date = $date->addDay()) {
            $key = $monthly ? $date->format('Y-m') : $date->toDateString();
            $trend[$key] ??= ['label' => $monthly ? $date->format('M Y') : $date->format('M j'), 'revenue' => 0, 'expenses' => 0];
            $trend[$key]['revenue'] += ($byDate[$date->toDateString()]['revenue'] ?? 0) / 100;
            $trend[$key]['expenses'] += ($byDate[$date->toDateString()]['expenses'] ?? 0) / 100;
        }

        $trend = array_values($trend);

        return [
            'available' => true,
            'error' => null,
            'revenue' => $revenueCents / 100,
            'expenses' => $expenseCents / 100,
            'net' => ($revenueCents - $expenseCents) / 100,
            'trend' => $trend,
            'chart' => [
                'revenue' => array_map(fn (array $row) => ['label' => $row['label'], 'total' => $row['revenue']], $trend),
                'expenses' => array_map(fn (array $row) => ['label' => $row['label'], 'total' => $row['expenses']], $trend),
            ],
            'lines' => $lines,
        ];
    }

    private function unavailableAccounting(string $error): array
    {
        return ['available' => false, 'error' => $error, 'revenue' => null, 'expenses' => null, 'net' => null, 'trend' => [], 'lines' => collect()];
    }

    private function closingChainIds(array $closingIds): array
    {
        $excluded = $closingIds;
        $frontier = $closingIds;
        while ($frontier !== []) {
            $next = AccountingJournal::query()->where('book_key', 'FCDC')->whereIn('correction_of_id', $frontier)->pluck('id')->all();
            $frontier = array_values(array_diff($next, $excluded));
            $excluded = [...$excluded, ...$frontier];
        }

        return $excluded;
    }

    private function writeSalesCsv($output, string $from, string $to): void
    {
        fputcsv($output, ['Transaction', 'Completed at (Manila)', 'Customer', 'Payment method', 'Items', 'Total', 'Status']);
        PosTransaction::query()->where('status', 'completed')
            ->where('completed_at', '>=', CarbonImmutable::parse($from, 'Asia/Manila')->utc())
            ->where('completed_at', '<', CarbonImmutable::parse($to, 'Asia/Manila')->addDay()->utc())
            ->with('lines')->orderBy('id')->chunkById(500, function ($transactions) use ($output): void {
                foreach ($transactions as $transaction) {
                    fputcsv($output, [$transaction->transaction_number, $transaction->completed_at?->copy()->timezone('Asia/Manila')->format('Y-m-d H:i:s'), $transaction->customer_name, $transaction->payment_method, $transaction->lines->sum('quantity'), $transaction->total, $transaction->status]);
                }
            });
    }

    private function writeInventoryCsv($output, array $inventory): void
    {
        fputcsv($output, ['Inventory code', 'Material', 'Category', 'Quantity', 'Unit', 'Unit cost', 'Carrying value', 'Status']);
        foreach ($inventory['items'] as $item) {
            fputcsv($output, [$item->code, $item->name, $item->category, $item->qty, $item->unit, $item->unit_cost, $item->carrying_value_cents === null ? (float) $item->qty * (float) $item->unit_cost : $item->carrying_value_cents / 100, $item->stockStatusLabel()]);
        }
    }

    private function writeAccountingCsv($output, array $accounting): void
    {
        if (! $accounting['available']) {
            fputcsv($output, ['Accounting report unavailable', $accounting['error']]);

            return;
        }
        fputcsv($output, ['Accounting date', 'Journal reference', 'Account code', 'Account', 'Classification', 'Debit', 'Credit']);
        foreach ($accounting['lines'] as $line) {
            fputcsv($output, [$line->journal->accounting_date->toDateString(), $line->journal->reference, $line->account->code, $line->account->name, $line->account->classification, number_format($line->debit_cents / 100, 2, '.', ''), number_format($line->credit_cents / 100, 2, '.', '')]);
        }
    }

    private function writeVatCsv($output, array $vat): void
    {
        fputcsv($output, ['Transaction', 'Completed at (Manila)', 'Tax classification', 'Taxable sales', 'Zero-rated sales', 'Exempt sales', 'Non-VAT sales', 'Output VAT', 'Total']);
        foreach ($vat['records'] as $record) {
            fputcsv($output, [$record->posTransaction?->transaction_number, $record->completed_at?->copy()->timezone('Asia/Manila')->format('Y-m-d H:i:s'), $record->tax_classification, $record->taxable_sales, $record->zero_rated_sales, $record->exempt_sales, $record->non_vat_sales, $record->output_vat, $record->total]);
        }
        fputcsv($output, []);
        fputcsv($output, ['Input VAT (saved reworking-cost records only)', $vat['input_vat']]);
        fputcsv($output, ['Limitation', $vat['coverage_note']]);
    }

    private function sectionLabel(string $section): string
    {
        return ['sales' => 'Sales', 'inventory' => 'Inventory', 'accounting' => 'Accounting', 'vat' => 'VAT'][$section];
    }

    private function parseDate(string $date): ?CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');
        } catch (InvalidFormatException) {
            return null;
        }

        return $parsed !== false && $parsed->format('Y-m-d') === $date ? $parsed : null;
    }

    private function authorizeReport(): void
    {
        abort_unless(auth()->user()?->can('reports.view'), 403);
    }
}
