<?php

namespace App\Livewire\TaxCompliance;

use App\Models\PosVatRecord;
use App\Services\RecordedVatSummary;
use Carbon\CarbonImmutable;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VatReturnPreparation extends Component
{
    public int $selectedYear;

    public int $selectedQuarter;

    public ?int $selectedRecordId = null;

    public function mount(): void
    {
        $now = now('Asia/Manila');
        $this->selectedYear = $now->year;
        $this->selectedQuarter = $now->quarter;
    }

    public function updatedSelectedYear(): void
    {
        if (! in_array($this->selectedYear, $this->availableYears(), true)) {
            $this->selectedYear = now('Asia/Manila')->year;
        }
        $this->selectedRecordId = null;
    }

    public function updatedSelectedQuarter(): void
    {
        if ($this->selectedQuarter < 1 || $this->selectedQuarter > 4) {
            $this->selectedQuarter = now('Asia/Manila')->quarter;
        }
        $this->selectedRecordId = null;
    }

    public function viewRecord(int $recordId): void
    {
        $this->authorizePermission();
        $this->selectedRecordId = $this->records()->whereKey($recordId)->value('id');
    }

    public function closeDetails(): void
    {
        $this->authorizePermission();
        $this->selectedRecordId = null;
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorizePermission();
        $summary = $this->summary();
        $generatedAt = CarbonImmutable::now('Asia/Manila');
        $year = $this->selectedYear;
        $quarter = $this->selectedQuarter;

        return response()->streamDownload(function () use ($summary, $generatedAt, $year, $quarter): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Company', 'Fabellion Construction and Development Corp.']);
            fputcsv($output, ['Reporting period', 'Q'.$quarter.' '.$year]);
            fputcsv($output, ['Taxable sales', $summary['taxable_sales']]);
            fputcsv($output, ['Recorded output VAT', $summary['output_vat']]);
            fputcsv($output, ['VAT-inclusive total', $summary['total_sales']]);
            fputcsv($output, ['Input-VAT capture status', 'not captured']);
            fputcsv($output, ['Deductions applied', $summary['deductions_applied']]);
            fputcsv($output, ['POS-only VAT payable estimate', $summary['vat_payable_estimate']]);
            fputcsv($output, ['Reporting timezone', 'Asia/Manila']);
            fputcsv($output, ['Generated at', $generatedAt->format('Y-m-d H:i:s P')]);
            fputcsv($output, ['Scope', 'Recorded POS sales only']);
            fputcsv($output, ['Status', 'Preparation aid only; not an official Form 2550Q, complete official return, or submitted filing']);
            fclose($output);
        }, sprintf('vat-preparation-%d-Q%d.csv', $year, $quarter), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $this->authorizePermission();
        $summary = $this->summary();
        $records = $this->records()->with('posTransaction.lines')
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get();
        $selectedRecord = $this->selectedRecordId
            ? $records->firstWhere('id', $this->selectedRecordId)
            : null;

        return view('livewire.tax-compliance.vat-return-preparation', [
            'years' => $this->availableYears(),
            'quarters' => [1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'],
            'periodLabel' => 'Q'.$this->selectedQuarter.' '.$this->selectedYear,
            'summary' => $summary,
            'records' => $records,
            'selectedRecord' => $selectedRecord,
            'generatedAt' => CarbonImmutable::now('Asia/Manila'),
        ])->layout('layouts.app', ['title' => 'VAT Return Preparation']);
    }

    private function summary(): array
    {
        return app(RecordedVatSummary::class)->forQuarter($this->selectedYear, $this->selectedQuarter);
    }

    private function records()
    {
        [$start, $end] = $this->quarterDateRange();

        return app(RecordedVatSummary::class)->recordsForDateRange($start, $end);
    }

    private function quarterDateRange(): array
    {
        $start = CarbonImmutable::createSafe($this->selectedYear, (($this->selectedQuarter - 1) * 3) + 1, 1, 0, 0, 0, 'Asia/Manila');

        return [$start->toDateString(), $start->addMonths(2)->endOfMonth()->toDateString()];
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
