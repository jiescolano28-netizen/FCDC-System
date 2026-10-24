<?php

namespace App\Livewire\TaxCompliance;

use App\Models\PosVatRecord;
use App\Models\VatReturnAdjustment;
use App\Services\BirVatReturnComputation;
use App\Services\RecordedVatSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class VatReturnPreparation extends Component
{
    public int $selectedYear;

    public int $selectedQuarter;

    public ?int $selectedRecordId = null;

    public string $statusMessage = '';

    public string $outputVatUncollectedReceivables = '0.00';

    public string $outputVatRecoveredReceivables = '0.00';

    public string $inputTaxCarriedOver = '0.00';

    public string $deferredCapitalGoodsInputTaxPrior = '0.00';

    public string $transitionalInputTax = '0.00';

    public string $presumptiveInputTax = '0.00';

    public string $otherInputTax = '0.00';

    public string $otherInputTaxDescription = '';

    public string $deferredCapitalGoodsInputTaxCurrent = '0.00';

    public string $inputTaxVatExemptSales = '0.00';

    public string $vatRefundTccClaimed = '0.00';

    public string $inputVatUnpaidPayables = '0.00';

    public string $otherInputTaxDeductions = '0.00';

    public string $otherInputTaxDeductionsDescription = '';

    public string $inputVatSettledUnpaidPayables = '0.00';

    public string $notes = '';

    /**
     * Database field to Livewire property mapping for saved adjustments.
     *
     * @var array<string, string>
     */
    private const ADJUSTMENT_PROPERTIES = [
        'output_vat_uncollected_receivables' => 'outputVatUncollectedReceivables',
        'output_vat_recovered_receivables' => 'outputVatRecoveredReceivables',
        'input_tax_carried_over' => 'inputTaxCarriedOver',
        'deferred_capital_goods_input_tax_prior' => 'deferredCapitalGoodsInputTaxPrior',
        'transitional_input_tax' => 'transitionalInputTax',
        'presumptive_input_tax' => 'presumptiveInputTax',
        'other_input_tax' => 'otherInputTax',
        'other_input_tax_description' => 'otherInputTaxDescription',
        'deferred_capital_goods_input_tax_current' => 'deferredCapitalGoodsInputTaxCurrent',
        'input_tax_vat_exempt_sales' => 'inputTaxVatExemptSales',
        'vat_refund_tcc_claimed' => 'vatRefundTccClaimed',
        'input_vat_unpaid_payables' => 'inputVatUnpaidPayables',
        'other_input_tax_deductions' => 'otherInputTaxDeductions',
        'other_input_tax_deductions_description' => 'otherInputTaxDeductionsDescription',
        'input_vat_settled_unpaid_payables' => 'inputVatSettledUnpaidPayables',
        'notes' => 'notes',
    ];

    public function mount(): void
    {
        $now = now('Asia/Manila');
        $this->selectedYear = $now->year;
        $this->selectedQuarter = $now->quarter;
        $this->loadAdjustments();
    }

    public function updated(string $property, mixed $value = null): void
    {
        if (in_array($property, array_values(self::ADJUSTMENT_PROPERTIES), true)) {
            $this->statusMessage = '';
        }
    }

    public function updatedSelectedYear(): void
    {
        if (! in_array($this->selectedYear, $this->availableYears(), true)) {
            $this->selectedYear = now('Asia/Manila')->year;
        }
        $this->selectedRecordId = null;
        $this->statusMessage = '';
        $this->loadAdjustments();
    }

    public function updatedSelectedQuarter(): void
    {
        if ($this->selectedQuarter < 1 || $this->selectedQuarter > 4) {
            $this->selectedQuarter = now('Asia/Manila')->quarter;
        }
        $this->selectedRecordId = null;
        $this->statusMessage = '';
        $this->loadAdjustments();
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

    public function saveAdjustments(): void
    {
        $this->authorizePermission();
        $this->resetValidation();
        $this->statusMessage = '';

        foreach (BirVatReturnComputation::MANUAL_AMOUNT_FIELDS as $field) {
            $property = self::ADJUSTMENT_PROPERTIES[$field];

            if (trim((string) $this->{$property}) === '') {
                $this->{$property} = '0.00';
            }
        }

        $rules = [];

        foreach (BirVatReturnComputation::MANUAL_AMOUNT_FIELDS as $field) {
            $rules[self::ADJUSTMENT_PROPERTIES[$field]] = ['nullable', 'numeric', 'min:0', 'decimal:0,2'];
        }

        $rules['otherInputTaxDescription'] = ['nullable', 'string', 'max:255'];
        $rules['otherInputTaxDeductionsDescription'] = ['nullable', 'string', 'max:255'];
        $rules['notes'] = ['nullable', 'string', 'max:2000'];

        $validated = $this->validate($rules);

        $values = [];

        foreach (BirVatReturnComputation::MANUAL_AMOUNT_FIELDS as $field) {
            $property = self::ADJUSTMENT_PROPERTIES[$field];
            $values[$field] = number_format((float) ($validated[$property] ?? 0), 2, '.', '');
        }

        $values['other_input_tax_description'] = trim((string) ($validated['otherInputTaxDescription'] ?? '')) ?: null;
        $values['other_input_tax_deductions_description'] = trim((string) ($validated['otherInputTaxDeductionsDescription'] ?? '')) ?: null;
        $values['notes'] = trim((string) ($validated['notes'] ?? '')) ?: null;
        $values['updated_by'] = auth()->id();

        VatReturnAdjustment::query()->updateOrCreate(
            [
                'year' => $this->selectedYear,
                'quarter' => $this->selectedQuarter,
            ],
            $values,
        );

        $this->loadAdjustments();
        $this->statusMessage = 'Manual adjustments saved for '.$this->periodLabel().'.';
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorizePermission();
        $summary = $this->summary();
        $items = $this->items($summary);
        $inputVatRecords = $this->inputVatRecords();
        $generatedAt = CarbonImmutable::now('Asia/Manila');
        $year = $this->selectedYear;
        $quarter = $this->selectedQuarter;

        return response()->streamDownload(function () use ($summary, $items, $inputVatRecords, $generatedAt, $year, $quarter): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Company', 'Fabellion Construction and Development Corp.']);
            fputcsv($output, ['Worksheet', 'BIR Form 2550Q quarterly VAT return preparation']);
            fputcsv($output, ['Reporting period', 'Q'.$quarter.' '.$year]);
            fputcsv($output, ['Reporting timezone', 'Asia/Manila']);
            fputcsv($output, ['Generated at', $generatedAt->format('Y-m-d H:i:s P')]);
            fputcsv($output, ['Scope', 'Recorded POS sales only for output VAT, plus saved Reworking Cost Record input VAT and manual 2550Q adjustments']);
            fputcsv($output, ['Status', 'Preparation only; this worksheet does not submit the return to the BIR and is not an official Form 2550Q, complete official return, or submitted filing']);
            fputcsv($output, []);
            fputcsv($output, ['Part II - Sales and output VAT']);
            fputcsv($output, ['Item 31A - Taxable sales (VATable)', $items['item_31a_taxable_sales']]);
            fputcsv($output, ['Item 31B - Output VAT', $items['item_31b_output_vat']]);
            fputcsv($output, ['Item 32 - Zero-rated sales', $items['item_32a_zero_rated_sales']]);
            fputcsv($output, ['Item 33 - VAT-exempt sales', $items['item_33a_exempt_sales']]);
            fputcsv($output, ['Item 34A - Total sales', $items['item_34a_total_sales']]);
            fputcsv($output, ['Item 34B - Total sales and output tax due', $items['item_34b_output_tax']]);
            fputcsv($output, ['Item 35B - Less: Output VAT on uncollected receivables', $items['item_35b_output_vat_uncollected_receivables']]);
            fputcsv($output, ['Item 36B - Add: Output VAT on recovered uncollected receivables previously deducted', $items['item_36b_output_vat_recovered_receivables']]);
            fputcsv($output, ['Item 37 - Total adjusted output tax due', $items['item_37b_total_adjusted_output_tax']]);
            fputcsv($output, ['Taxable sales', $summary['taxable_sales']]);
            fputcsv($output, ['Zero-rated sales', $summary['zero_rated_sales']]);
            fputcsv($output, ['VAT-exempt sales', $summary['exempt_sales']]);
            fputcsv($output, ['Non-VAT / outside scope sales', $summary['non_vat_sales']]);
            fputcsv($output, ['Recorded output VAT', $summary['output_vat']]);
            fputcsv($output, ['VAT-inclusive total', $summary['total_sales']]);
            fputcsv($output, []);
            fputcsv($output, ['Part III - Input VAT']);
            fputcsv($output, ['Input-VAT capture status', $summary['input_vat_status']]);
            fputcsv($output, ['Item 38B - Input tax carried over from previous quarter', $items['item_38b_input_tax_carried_over']]);
            fputcsv($output, ['Item 39B - Input tax deferred on capital goods exceeding P1 Million from previous quarter', $items['item_39b_deferred_capital_goods_input_tax_prior']]);
            fputcsv($output, ['Item 40B - Transitional input tax', $items['item_40b_transitional_input_tax']]);
            fputcsv($output, ['Item 41B - Presumptive input tax', $items['item_41b_presumptive_input_tax']]);
            fputcsv($output, ['Item 42B - Other input tax', $items['item_42b_other_input_tax']]);
            fputcsv($output, ['Item 43B - Total prior/other input tax', $items['item_43b_total_prior_input_tax']]);
            fputcsv($output, ['Reworking cost total (reworking cost records)', $summary['input_vat_reworking_cost']]);
            fputcsv($output, ['Input VAT on reworking cost records', $summary['input_vat']]);
            fputcsv($output, ['Item 50A - Total current purchases', $items['item_50a_total_current_purchases']]);
            fputcsv($output, ['Item 50B - Total current input tax', $items['item_50b_total_current_input_tax']]);
            fputcsv($output, ['Item 51 - Total available input tax', $items['item_51b_total_available_input_tax']]);
            fputcsv($output, ['Item 52B - Input tax on capital goods exceeding P1 Million deferred for succeeding period', $items['item_52b_deferred_capital_goods_input_tax_current']]);
            fputcsv($output, ['Item 53B - Input tax attributable to VAT-exempt sales', $items['item_53b_input_tax_vat_exempt_sales']]);
            fputcsv($output, ['Item 54B - VAT refund/TCC claimed', $items['item_54b_vat_refund_tcc_claimed']]);
            fputcsv($output, ['Item 55B - Input VAT on unpaid payables', $items['item_55b_input_vat_unpaid_payables']]);
            fputcsv($output, ['Item 56B - Other deductions from input tax', $items['item_56b_other_input_tax_deductions']]);
            fputcsv($output, ['Item 57B - Total deductions from input tax', $items['item_57b_total_input_tax_deductions']]);
            fputcsv($output, ['Item 58B - Add: Input VAT on settled unpaid payables previously deducted', $items['item_58b_input_vat_settled_unpaid_payables']]);
            fputcsv($output, ['Item 59B - Adjusted deductions from input tax', $items['item_59b_adjusted_input_tax_deductions']]);
            fputcsv($output, ['Item 60 - Total allowable input tax', $items['item_60b_total_allowable_input_tax']]);
            fputcsv($output, ['Deductions applied', $items['item_59b_adjusted_input_tax_deductions']]);
            fputcsv($output, []);
            fputcsv($output, ['Part IV - Net VAT payable']);
            fputcsv($output, [
                $items['item_61_is_excess_input_tax']
                    ? 'Item 61 - Excess input tax'
                    : 'Item 61 - Net VAT payable',
                $items['item_61_net_vat_payable'],
            ]);
            fputcsv($output, ['POS-only VAT payable estimate', $items['item_61_net_vat_payable']]);
            fputcsv($output, []);

            if ($inputVatRecords->isNotEmpty()) {
                fputcsv($output, ['Input VAT records from reworking cost records']);
                fputcsv($output, ['Batch number', 'Project', 'Record date (Asia/Manila)', 'Description', 'Reworking cost', 'Input VAT']);

                foreach ($inputVatRecords as $record) {
                    fputcsv($output, [
                        $record['batch_number'],
                        $record['project'],
                        $record['date'],
                        $record['description'],
                        $record['total_reworking_cost'],
                        $record['input_vat'],
                    ]);
                }

                fputcsv($output, ['Input VAT total', $summary['input_vat']]);
            }

            fclose($output);
        }, sprintf('vat-preparation-%d-Q%d.csv', $year, $quarter), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $this->authorizePermission();
        $summary = $this->summary();
        $items = $this->items($summary);
        $records = $this->records()->with('posTransaction.lines')
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get();
        $selectedRecord = $this->selectedRecordId
            ? $records->firstWhere('id', $this->selectedRecordId)
            : null;
        $inputVatRecords = $this->inputVatRecords();
        $adjustmentRecord = $this->adjustmentRecord();

        return view('livewire.tax-compliance.vat-return-preparation', [
            'years' => $this->availableYears(),
            'quarters' => [1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'],
            'periodLabel' => $this->periodLabel(),
            'summary' => $summary,
            'items' => $items,
            'records' => $records,
            'selectedRecord' => $selectedRecord,
            'inputVatRecords' => $inputVatRecords,
            'adjustmentsSavedAt' => $adjustmentRecord?->updated_at?->copy()->timezone('Asia/Manila'),
            'adjustmentsSavedBy' => $adjustmentRecord?->updatedBy?->username,
            'generatedAt' => CarbonImmutable::now('Asia/Manila'),
        ])->layout('layouts.app', ['title' => 'VAT Return Preparation']);
    }

    private function summary(): array
    {
        return app(RecordedVatSummary::class)->forQuarter($this->selectedYear, $this->selectedQuarter);
    }

    private function items(array $summary): array
    {
        return app(BirVatReturnComputation::class)->compute($summary, $this->adjustmentValues());
    }

    private function records()
    {
        [$start, $end] = $this->quarterDateRange();

        return app(RecordedVatSummary::class)->recordsForDateRange($start, $end);
    }

    private function inputVatRecords(): Collection
    {
        [$start, $end] = $this->quarterDateRange();

        return app(RecordedVatSummary::class)->inputVatRecordsForDateRange($start, $end);
    }

    private function adjustmentRecord(): ?VatReturnAdjustment
    {
        try {
            return VatReturnAdjustment::query()
                ->with('updatedBy:id,username')
                ->where('year', $this->selectedYear)
                ->where('quarter', $this->selectedQuarter)
                ->first();
        } catch (Throwable) {
            return null;
        }
    }

    private function loadAdjustments(): void
    {
        $record = $this->adjustmentRecord();

        foreach (self::ADJUSTMENT_PROPERTIES as $field => $property) {
            if (in_array($field, BirVatReturnComputation::MANUAL_AMOUNT_FIELDS, true)) {
                $this->{$property} = number_format((float) ($record?->getAttribute($field) ?? 0), 2, '.', '');

                continue;
            }

            $this->{$property} = (string) ($record?->getAttribute($field) ?? '');
        }
    }

    /**
     * @return array<string, string>
     */
    private function adjustmentValues(): array
    {
        $values = [];

        foreach (self::ADJUSTMENT_PROPERTIES as $field => $property) {
            $values[$field] = (string) $this->{$property};
        }

        return $values;
    }

    private function quarterDateRange(): array
    {
        $start = CarbonImmutable::createSafe($this->selectedYear, (($this->selectedQuarter - 1) * 3) + 1, 1, 0, 0, 0, 'Asia/Manila');

        return [$start->toDateString(), $start->addMonths(2)->endOfMonth()->toDateString()];
    }

    private function periodLabel(): string
    {
        return 'Q'.$this->selectedQuarter.' '.$this->selectedYear;
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

        return array_reverse(range(min($earliestYear, $nowYear - 10), max($latestYear, $nowYear)));
    }

    private function authorizePermission(): void
    {
        abort_unless(auth()->user()?->can('tax.view'), 403);
    }
}
