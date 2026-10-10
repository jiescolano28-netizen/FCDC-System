<?php

use App\Livewire\TaxCompliance\VatReturnPreparation;
use App\Livewire\TaxCompliance\VatSummary;
use App\Livewire\TaxCompliance\VatRecords;
use App\Models\Employee;
use App\Models\PosTransaction;
use App\Models\PosTransactionLine;
use App\Models\PosVatRecord;
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
        ->assertSee('Saved worksheet item')
        ->set('selectedQuarter', 3)
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
