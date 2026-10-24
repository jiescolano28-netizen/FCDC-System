<?php

use App\Livewire\TaxCompliance\VatRecords;
use App\Livewire\TaxCompliance\VatReturnPreparation;
use App\Livewire\TaxCompliance\VatSummary;
use App\Models\Employee;
use App\Models\PosTransaction;
use App\Models\PosTransactionLine;
use App\Models\PosVatRecord;
use App\Models\ReworkingCostRecord;
use App\Models\VatReturnAdjustment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createVatReturnPreparationEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'vat.return.viewer',
        'email' => 'vat.return@example.com',
        'password' => Hash::make('vat-password'),
    ]), ['tax.view']);
}

function createVatPreparationRecord(string $number, string $completedAt, string $taxable, string $vat, string $total): PosVatRecord
{
    $transaction = PosTransaction::create([
        'transaction_number' => $number,
        'subtotal' => $taxable,
        'vat_rate' => 0.12,
        'vat_amount' => $vat,
        'total' => $total,
        'payment_method' => 'cash',
        'amount_received' => $total,
        'change_due' => 0,
        'status' => 'completed',
        'customer_name' => 'Worksheet Customer',
        'completed_at' => $completedAt,
    ]);

    PosTransactionLine::create([
        'pos_transaction_id' => $transaction->id,
        'inventory_code' => 'TEST-ITEM',
        'item_name' => 'Saved worksheet item',
        'category' => 'Test materials',
        'unit' => 'piece',
        'quantity' => 1,
        'selling_price' => $taxable,
        'unit_cost' => 0,
        'line_subtotal' => $taxable,
        'vat_amount' => $vat,
        'line_total' => $total,
    ]);

    return PosVatRecord::create([
        'pos_transaction_id' => $transaction->id,
        'taxable_sales' => $taxable,
        'vat_rate' => 0.12,
        'output_vat' => $vat,
        'total' => $total,
        'completed_at' => $completedAt,
    ]);
}

function createVatPreparationInputVatRecord(string $batch, string $date, string $cost, string $inputVat): ReworkingCostRecord
{
    return ReworkingCostRecord::create([
        'employee_id' => null,
        'source_record_id' => ReworkingCostRecord::query()->max('source_record_id') + 1,
        'project_name' => 'Warehouse Dismantling',
        'batch_number' => $batch,
        'record_date' => $date,
        'description' => 'Cleaning and repair of recovered steel bars',
        'total_reworking_cost' => $cost,
        'input_vat' => $inputVat,
    ]);
}

test('VAT preparation requires authentication and tax.view for worksheet and CSV access', function () {
    $this->get(route('tax.vat-return-preparation'))->assertRedirect(route('login'));

    $this->actingAs(Employee::create([
        'username' => 'vat.return.denied',
        'email' => 'vat.return.denied@example.com',
        'password' => Hash::make('vat-password'),
    ]))->get(route('tax.vat-return-preparation'))->assertForbidden();

    $this->actingAs(createVatReturnPreparationEmployee())
        ->get(route('tax.vat-return-preparation'))
        ->assertOk()
        ->assertSee('VAT Return Preparation')
        ->assertSee('Asia/Manila')
        ->assertSee('not an official Form 2550Q', false)
        ->assertSee('Fabellion Construction and Development Corp.')
        ->assertSee('Save as PDF');
});

test('worksheet starts in current Manila quarter and its selectable periods reconcile with VAT Summary and Records', function () {
    Carbon::setTestNow('2026-10-10 12:00:00 UTC');
    createVatPreparationRecord('Q4-BOUNDARY', '2026-09-30 16:30:00', '180.05', '21.61', '201.66');
    createVatPreparationRecord('Q4-SECOND', '2026-10-15 08:00:00', '180.05', '21.61', '201.66');
    createVatPreparationRecord('Q3-LAST', '2026-09-30 15:59:00', '100.00', '12.00', '112.00');
    $this->actingAs(createVatReturnPreparationEmployee());

    $worksheet = Livewire::test(VatReturnPreparation::class)
        ->assertSet('selectedYear', 2026)
        ->assertSet('selectedQuarter', 4)
        ->assertSee('Q4 2026')
        ->assertSee('₱360.10')
        ->assertSee('₱43.22')
        ->assertSee('₱403.32')
        ->assertSee('Input VAT not captured')
        ->assertSee('Deductions applied')
        ->assertSee('₱0.00')
        ->assertSee('POS-only VAT payable estimate')
        ->assertSee('Q4-BOUNDARY')
        ->assertSee('View details')
        ->call('viewRecord', PosVatRecord::query()->whereHas('posTransaction', fn ($query) => $query->where('transaction_number', 'Q4-BOUNDARY'))->value('id'))
        ->assertSee('Worksheet Customer')
        ->assertSee('VAT-inclusive total')
        ->assertSee('Saved worksheet item');

    $printDocument = new DOMDocument;
    @$printDocument->loadHTML('<?xml encoding="UTF-8">'.$worksheet->html(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $printText = $printDocument->getElementById('vat-return-print')?->textContent;
    expect($printText)->toContain(
        'Fabellion Construction and Development Corp.',
        'Q4 2026',
        '2026-10-10 20:00:00 +08:00',
        'Reporting timezone: Asia/Manila',
        'Taxable sales',
        '₱360.10',
        '₱43.22',
        '₱403.32',
        'Input VAT not captured',
        'Deductions applied',
        'POS-only VAT payable estimate',
        'not an official Form 2550Q',
        'not a complete official return',
        'not a submitted filing',
    );

    $worksheet->set('selectedQuarter', 3)
        ->assertSee('Q3 2026')
        ->assertSee('Q3-LAST')
        ->assertDontSee('Q4-BOUNDARY');

    $summary = Livewire::test(VatSummary::class)
        ->set('periodType', 'quarterly')
        ->set('selectedYear', 2026)
        ->set('selectedPeriod', 4);
    expect($worksheet->html())->toContain('Q3-LAST')
        ->and($summary->html())->toContain('₱360.10', '₱43.22', '₱403.32');

    Livewire::test(VatRecords::class)
        ->set('startDate', '2026-10-01')
        ->set('endDate', '2026-12-31')
        ->assertSee('Q4-BOUNDARY')
        ->assertSee('Q4-SECOND')
        ->assertDontSee('Q3-LAST');
});

test('empty worksheet period is zeroed and new recorded sales immediately update worksheet and summary CSV', function () {
    Carbon::setTestNow('2026-10-10 12:00:00 UTC');
    $this->actingAs(createVatReturnPreparationEmployee());

    $worksheet = Livewire::test(VatReturnPreparation::class)
        ->set('selectedYear', 2025)
        ->set('selectedQuarter', 2)
        ->assertSee('No recorded POS sales')
        ->assertSee('₱0.00')
        ->assertDontSee('demonstration')
        ->set('selectedYear', 2026)
        ->set('selectedQuarter', 4);

    createVatPreparationRecord('LIVE-NEW-SALE', '2026-10-15 08:00:00', '180.05', '21.61', '201.66');

    $worksheet->set('selectedQuarter', 3)
        ->set('selectedQuarter', 4)
        ->assertSee('₱180.05')
        ->assertSee('₱21.61')
        ->assertSee('LIVE-NEW-SALE')
        ->call('exportCsv')
        ->assertFileDownloaded('vat-preparation-2026-Q4.csv');

    $csv = base64_decode($worksheet->effects['download']['content']);
    expect($csv)->toContain(
        'Fabellion Construction and Development Corp.',
        'Reporting period',
        'Q4 2026',
        'Taxable sales',
        '180.05',
        'Recorded output VAT',
        '21.61',
        'VAT-inclusive total',
        '201.66',
        'Input-VAT capture status',
        'not captured',
        'Deductions applied',
        '0.00',
        'POS-only VAT payable estimate',
        'Reporting timezone',
        'Asia/Manila',
        'Generated at',
        '2026-10-10 20:00:00 +08:00',
        'Scope',
        'Recorded POS sales only',
        'not an official Form 2550Q',
    );
});

test('worksheet detail review and CSV export are forbidden without tax.view', function () {
    $employee = Employee::create([
        'username' => 'vat.return.denied.action',
        'email' => 'vat.return.denied.action@example.com',
        'password' => Hash::make('vat-password'),
    ]);

    $this->actingAs($employee);

    Livewire::test(VatReturnPreparation::class)
        ->assertForbidden();
});

test('worksheet is organized into preparation-only BIR 2550Q sections with CSV and PDF export', function () {
    $this->actingAs(createVatReturnPreparationEmployee());

    Livewire::test(VatReturnPreparation::class)
        ->assertSee('Part I — Taxpayer and return period')
        ->assertSee('Part II — Sales and output VAT')
        ->assertSee('Part III — Input VAT')
        ->assertSee('Part IV — Net VAT payable')
        ->assertSee('BIR Form 2550Q')
        ->assertSee('does not submit the return to the BIR')
        ->assertSee('Export CSV')
        ->assertSee('Save as PDF');
});

test('worksheet computes BIR 2550Q items 34, 37, 51, 60 and 61 from recorded sales, reworking input VAT and saved manual adjustments', function () {
    Carbon::setTestNow('2026-10-10 12:00:00 UTC');
    $this->actingAs(createVatReturnPreparationEmployee());
    createVatPreparationRecord('BIR-2550Q-SALE', '2026-10-15 08:00:00', '1000.00', '120.00', '1120.00');
    createVatPreparationInputVatRecord('BIR-2550Q-BATCH', '2026-10-20', '500.00', '60.00');

    $worksheet = Livewire::test(VatReturnPreparation::class)
        ->set('selectedYear', 2026)
        ->set('selectedQuarter', 4)
        ->assertSee('31A · ₱1,000.00')
        ->assertSee('31B · ₱120.00')
        ->assertSee('50A · ₱500.00')
        ->assertSee('50B · ₱60.00')
        ->set('outputVatUncollectedReceivables', '20.00')
        ->set('outputVatRecoveredReceivables', '5.00')
        ->set('inputTaxCarriedOver', '10.00')
        ->set('deferredCapitalGoodsInputTaxPrior', '2.00')
        ->set('transitionalInputTax', '1.00')
        ->set('presumptiveInputTax', '3.00')
        ->set('otherInputTax', '4.00')
        ->set('otherInputTaxDescription', 'Manual input VAT from other purchases')
        ->set('deferredCapitalGoodsInputTaxCurrent', '5.00')
        ->set('inputTaxVatExemptSales', '6.00')
        ->set('vatRefundTccClaimed', '7.00')
        ->set('inputVatUnpaidPayables', '8.00')
        ->set('otherInputTaxDeductions', '9.00')
        ->set('otherInputTaxDeductionsDescription', 'Other allowable deduction')
        ->set('inputVatSettledUnpaidPayables', '2.00')
        ->call('saveAdjustments')
        ->assertHasNoErrors()
        ->assertSee('Manual adjustments saved for Q4 2026.')
        ->assertSee('34A · ₱1,000.00')
        ->assertSee('Item 37 — Total Adjusted Output Tax Due')
        ->assertSee('₱105.00')
        ->assertSee('Total Available Input Tax')
        ->assertSee('₱80.00')
        ->assertSee('Total Allowable Input Tax')
        ->assertSee('₱43.00')
        ->assertSee('₱62.00');

    $saved = VatReturnAdjustment::query()->sole();
    expect($saved->year)->toBe(2026)
        ->and($saved->quarter)->toBe(4)
        ->and($saved->output_vat_uncollected_receivables)->toBe('20.00')
        ->and($saved->output_vat_recovered_receivables)->toBe('5.00')
        ->and($saved->input_tax_carried_over)->toBe('10.00')
        ->and($saved->other_input_tax)->toBe('4.00')
        ->and($saved->other_input_tax_description)->toBe('Manual input VAT from other purchases')
        ->and($saved->input_vat_settled_unpaid_payables)->toBe('2.00');

    // Saved adjustments reload for the same quarter and do not leak into another period.
    $worksheet->set('selectedQuarter', 3)
        ->assertSet('outputVatUncollectedReceivables', '0.00')
        ->assertSet('inputTaxCarriedOver', '0.00')
        ->set('selectedQuarter', 4)
        ->assertSet('outputVatUncollectedReceivables', '20.00')
        ->assertSet('inputTaxCarriedOver', '10.00')
        ->assertSee('BIR-2550Q-BATCH');

    $worksheet->call('exportCsv')->assertFileDownloaded('vat-preparation-2026-Q4.csv');
    $csv = base64_decode($worksheet->effects['download']['content']);
    expect($csv)->toContain(
        'Item 34B - Total sales and output tax due',
        'Item 37 - Total adjusted output tax due',
        'Item 51 - Total available input tax',
        'Item 60 - Total allowable input tax',
        'Item 61 - Net VAT payable',
        '105.00',
        '80.00',
        '43.00',
        '62.00',
    );
});

test('worksheet shows excess input tax when allowable input tax exceeds adjusted output tax', function () {
    Carbon::setTestNow('2026-10-10 12:00:00 UTC');
    $this->actingAs(createVatReturnPreparationEmployee());
    createVatPreparationRecord('BIR-EXCESS-SALE', '2026-10-15 08:00:00', '50.00', '6.00', '56.00');
    createVatPreparationInputVatRecord('BIR-EXCESS-BATCH', '2026-10-20', '100.00', '100.00');

    Livewire::test(VatReturnPreparation::class)
        ->set('selectedYear', 2026)
        ->set('selectedQuarter', 4)
        ->assertSee('Excess Input Tax')
        ->assertSee('₱94.00')
        ->assertSee('Item 37B less Item 60B');
});

test('manual 2550Q adjustments reject negative or over-precision amounts', function () {
    $this->actingAs(createVatReturnPreparationEmployee());

    Livewire::test(VatReturnPreparation::class)
        ->set('outputVatUncollectedReceivables', '-5')
        ->set('inputTaxCarriedOver', '1.234')
        ->call('saveAdjustments')
        ->assertHasErrors([
            'outputVatUncollectedReceivables' => 'min',
            'inputTaxCarriedOver' => 'decimal',
        ]);

    expect(VatReturnAdjustment::query()->count())->toBe(0);
});
