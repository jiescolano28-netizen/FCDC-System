<?php

use App\Livewire\TaxCompliance\VatRecords;
use App\Livewire\TaxCompliance\VatSummary;
use App\Models\Employee;
use App\Models\PosTransaction;
use App\Models\PosVatRecord;
use App\Services\RecordedVatSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createVatSummaryEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'vat.summary.viewer',
        'email' => 'vat.summary@example.com',
        'password' => Hash::make('vat-password'),
    ]), ['tax.view']);
}

function createVatSummaryRecord(string $number, string $completedAt, string $taxable, string $vat, string $total): void
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
        'customer_name' => 'Test Customer',
        'completed_at' => $completedAt,
    ]);

    PosVatRecord::create([
        'pos_transaction_id' => $transaction->id,
        'taxable_sales' => $taxable,
        'vat_rate' => 0.12,
        'output_vat' => $vat,
        'total' => $total,
        'completed_at' => $completedAt,
    ]);
}

test('VAT summary is available only through its authenticated tax-authorized route', function () {
    $this->get(route('tax.vat-summary'))->assertRedirect(route('login'));

    $this->actingAs(createVatSummaryEmployee())
        ->get(route('tax.vat-summary'))
        ->assertOk()
        ->assertSee('VAT Summary')
        ->assertSee('POS-only')
        ->assertSee('Input VAT not captured')
        ->assertSee(route('tax.vat-records'), false);
});

test('monthly and quarterly selectors summarize the same recorded VAT amounts', function () {
    createVatSummaryRecord('OCT-A', '2026-09-30 16:30:00', '180.05', '21.61', '201.66');
    createVatSummaryRecord('OCT-B', '2026-10-15 08:00:00', '180.05', '21.61', '201.66');
    createVatSummaryRecord('SEP', '2026-09-30 15:59:00', '100.00', '12.00', '112.00');
    $this->actingAs(createVatSummaryEmployee());

    Livewire::test(VatSummary::class)
        ->assertSet('periodType', 'monthly')
        ->set('selectedYear', 2026)
        ->set('selectedPeriod', 10)
        ->assertSee('October 2026')
        ->assertSee('₱360.10')
        ->assertSee('₱43.22')
        ->assertSee('₱403.32')
        ->assertSee('Checkout-level rounding')
        ->set('periodType', 'quarterly')
        ->set('selectedPeriod', 4)
        ->assertSee('Q4 2026')
        ->assertSee('₱360.10')
        ->assertSee('₱43.22')
        ->assertSee('₱403.32')
        ->set('selectedPeriod', 3)
        ->assertSee('Q3 2026')
        ->assertSee('₱100.00')
        ->assertDontSee('₱360.10');

    Livewire::test(VatRecords::class)
        ->set('startDate', '2026-10-01')
        ->set('endDate', '2026-10-31')
        ->assertSee('OCT-A')
        ->assertSee('OCT-B')
        ->assertSee('₱21.61')
        ->assertSee('₱201.66')
        ->assertDontSee('SEP');
});

test('Manila year boundary and custom inclusive date ranges use recorded periods', function () {
    createVatSummaryRecord('YEAR-BOUNDARY', '2025-12-31 16:30:00', '180.05', '21.61', '201.66');

    $summary = app(RecordedVatSummary::class)->forMonth(2026, 1);
    expect($summary)->toMatchArray([
        'taxable_sales' => '180.05',
        'output_vat' => '21.61',
        'total_sales' => '201.66',
        'deductions_applied' => '0.00',
        'vat_payable_estimate' => '21.61',
    ]);

    $emptyDecember = app(RecordedVatSummary::class)->forMonth(2025, 12);
    expect($emptyDecember['taxable_sales'])->toBe('0.00');

    $customRange = app(RecordedVatSummary::class)->forDateRange('2026-01-01', '2026-01-01');
    expect($customRange)->toMatchArray([
        'record_count' => 1,
        'taxable_sales' => '180.05',
        'output_vat' => '21.61',
        'total_sales' => '201.66',
    ]);
});

test('empty period displays zero totals and explicitly reports no recorded POS sales', function () {
    $this->actingAs(createVatSummaryEmployee());

    Livewire::test(VatSummary::class)
        ->set('selectedYear', 2025)
        ->assertSet('selectedYear', 2025)
        ->set('selectedPeriod', 2)
        ->assertSee('No recorded POS sales')
        ->assertSee('₱0.00')
        ->assertSee('Input VAT not captured')
        ->assertSee('₱0.00');
});
