<?php

use App\Livewire\TaxCompliance\TaxReport;
use App\Livewire\TaxCompliance\VatRecords;
use App\Livewire\TaxCompliance\VatSummary;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\PosTransaction;
use App\Models\PosTransactionLine;
use App\Models\PosVatRecord;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function createVatRecordsEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'vat.viewer',
        'email' => 'vat@example.com',
        'password' => Hash::make('vat-password'),
    ]), ['tax.view']);
}
function createRecordedVatSale(
    string $number,
    string $completedAt,
    bool $includeVatRecord = true,
    ?int $inventoryId = null,
): PosTransaction {
    $transaction = PosTransaction::create([
        'transaction_number' => $number,
        'subtotal' => 180,
        'vat_rate' => 0.12,
        'vat_amount' => 21.60,
        'total' => 201.60,
        'payment_method' => 'cash',
        'amount_received' => 201.60,
        'change_due' => 0,
        'status' => 'completed',
        'customer_name' => 'Test Customer',
        'completed_at' => $completedAt,
    ]);
    PosTransactionLine::create([
        'pos_transaction_id' => $transaction->id,
        'inventory_id' => $inventoryId,
        'inventory_code' => 'BRD-001',
        'item_name' => 'Pine board',
        'category' => 'Lumber',
        'unit' => 'piece',
        'quantity' => 2,
        'selling_price' => 90,
        'unit_cost' => 50,
        'line_subtotal' => 180,
        'vat_amount' => 21.60,
        'line_total' => 201.60,
    ]);

    if ($includeVatRecord) {
        PosVatRecord::create([
            'pos_transaction_id' => $transaction->id,
            'taxable_sales' => $transaction->subtotal,
            'vat_rate' => $transaction->vat_rate,
            'output_vat' => $transaction->vat_amount,
            'total' => $transaction->total,
            'completed_at' => $transaction->completed_at,
        ]);
    }

    return $transaction;
}
function createVatReportSale(string $number, string $completedAt, string $taxable = '180.05', string $vat = '21.61', string $total = '201.66'): PosTransaction
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
        'customer_name' => 'Report Customer',
        'completed_at' => $completedAt,
    ]);
    PosTransactionLine::create([
        'pos_transaction_id' => $transaction->id,
        'inventory_code' => 'BRD-001',
        'item_name' => 'Saved pine board',
        'category' => 'Lumber',
        'unit' => 'piece',
        'quantity' => 2,
        'selling_price' => $taxable / 2,
        'unit_cost' => 50,
        'line_subtotal' => $taxable,
        'vat_amount' => $vat,
        'line_total' => $total,
    ]);
    PosVatRecord::create([
        'pos_transaction_id' => $transaction->id,
        'taxable_sales' => $taxable,
        'vat_rate' => 0.12,
        'output_vat' => $vat,
        'total' => $total,
        'completed_at' => $transaction->completed_at,
    ]);

    return $transaction;
}

test('tax report defaults to the Manila month and reconciles recorded month and quarter sales', function () {
    Carbon::setTestNow('2026-10-15 12:00:00 UTC');
    createVatReportSale('REPORT-OCT-BOUNDARY', '2026-09-30 16:00:00');
    createVatReportSale('REPORT-OCT-SECOND', '2026-10-15 08:00:00');
    createVatReportSale('REPORT-SEP-OUTSIDE', '2026-09-30 15:59:59');
    createVatReportSale('REPORT-NOV-OUTSIDE', '2026-10-31 16:00:00');
    $this->actingAs(createVatRecordsEmployee());
    Livewire::test(TaxReport::class)
        ->assertSet('periodType', 'monthly')
        ->assertSet('selectedYear', 2026)
        ->assertSet('selectedPeriod', 10)
        ->assertSee('October 2026')
        ->assertSee('REPORT-OCT-BOUNDARY')
        ->assertDontSee('REPORT-SEP-OUTSIDE')
        ->assertSee('₱360.10')
        ->assertSee('₱43.22')
        ->assertSee('₱403.32');

    Livewire::test(VatSummary::class)
        ->set('selectedYear', 2026)
        ->set('selectedPeriod', 10)
        ->assertSee('₱360.10')
        ->assertSee('₱43.22')
        ->assertSee('₱403.32');
    Livewire::test(TaxReport::class)
        ->set('periodType', 'quarterly')
        ->assertSee('₱540.15')
        ->assertSee('₱64.83')
        ->assertSee('₱604.98')
        ->set('periodType', 'custom')
        ->set('startDate', '2026-10-01')
        ->assertSee('₱360.10')
        ->assertSee('₱43.22')
        ->assertSee('₱403.32')
        ->assertSee('REPORT-OCT-BOUNDARY')
        ->assertSee('REPORT-OCT-SECOND')
        ->assertDontSee('REPORT-NOV-OUTSIDE');
});

test('tax report details are read-only and CSV exports all filtered transactions across pages', function () {
    Carbon::setTestNow('2026-10-15 12:00:00 UTC');
    $first = createVatReportSale('POS-CSV-001', '2026-10-01 00:00:00');
    createVatReportSale('POS-CSV-002', '2026-10-31 15:59:59');
    foreach (range(3, 22) as $number) {
        createVatReportSale(sprintf('POS-CSV-%03d', $number), '2026-10-15 04:30:00');
    }
    createVatReportSale('POS-CSV-OUTSIDE', '2026-10-31 16:00:00');
    $this->actingAs(createVatRecordsEmployee());

    $report = Livewire::test(TaxReport::class)
        ->set('periodType', 'custom')
        ->set('endDate', '2026-10-31')
        ->set('paginators.page', 2)
        ->assertSee('POS-CSV-001')
        ->assertSee('POS-CSV-002')
        ->assertSee('POS-CSV-022')
        ->call('viewRecord', $first->vatRecord->id)
        ->assertSee('Report Customer')
        ->assertSee('Saved pine board')
        ->assertSee('POS-CSV-001')
        ->call('exportCsv')
        ->assertFileDownloaded('vat-report-2026-10-01-to-2026-10-31.csv');
    $csv = base64_decode($report->effects['download']['content']);

    expect($csv)
        ->toContain('POS-CSV-022', 'Recorded POS VAT only', 'Asia/Manila', 'Fabellion Construction and Development Corp.', '2026-10-01 through 2026-10-31', '3961.10', '475.42', '4436.52')
        ->not->toContain('POS-CSV-OUTSIDE')
        ->and(substr_count($csv, 'POS-CSV-'))->toBe(22);
});

test('empty tax report periods show zero activity in the printable report and CSV', function () {
    Carbon::setTestNow('2026-10-15 12:00:00 UTC');
    $this->actingAs(createVatRecordsEmployee());

    $report = Livewire::test(TaxReport::class)
        ->set('periodType', 'custom')
        ->set('startDate', '2026-11-01')
        ->set('endDate', '2026-11-30')
        ->assertSee('No recorded POS sales for this period.')
        ->assertSee('₱0.00')
        ->assertDontSee('VAT-2026-08-001')
        ->call('exportCsv')
        ->assertFileDownloaded('vat-report-2026-11-01-to-2026-11-30.csv');
    $csv = base64_decode($report->effects['download']['content']);

    expect($csv)
        ->toContain('2026-11-01 through 2026-11-30', 'Recorded POS VAT only', '"Taxable sales",0.00', '"Output VAT",0.00')
        ->not->toContain('VAT-2026');
});

test('custom tax report dates stay valid while either boundary changes', function () {
    Carbon::setTestNow('2026-10-15 12:00:00 UTC');
    $this->actingAs(createVatRecordsEmployee());

    Livewire::test(TaxReport::class)
        ->set('periodType', 'custom')
        ->set('endDate', '2026-09-30')
        ->assertSet('startDate', '2026-09-30')
        ->set('startDate', '2026-10-01')
        ->assertSet('endDate', '2026-10-01')
        ->assertSee('2026-10-01 through 2026-10-01');
});

test('tax report route and detail and export actions require tax.view', function () {
    $employee = grantEmployeeTestPermissions(Employee::create([
        'username' => 'report-without-tax',
        'email' => 'report-without-tax@example.com',
        'password' => Hash::make('vat-password'),
    ]), ['pos.checkout']);
    $sale = createVatReportSale('REPORT-PRIVATE', '2026-10-15 04:30:00');
    $this->actingAs($employee)->get(route('tax.report'))->assertForbidden();

    expect(fn () => (new TaxReport)->viewRecord($sale->vatRecord->id))
        ->toThrow(HttpException::class);
    expect(fn () => (new TaxReport)->exportCsv())
        ->toThrow(HttpException::class);
});

test('tax report has an authenticated POS-only printable surface with established company identity', function () {
    $this->get(route('tax.report'))->assertRedirect(route('login'));

    $this->actingAs(createVatRecordsEmployee())
        ->get(route('tax.report'))->assertOk()
        ->assertSee('Fabellion Construction and Development Corp.')
        ->assertSee('Asia/Manila')
        ->assertSee('POS-only')
        ->assertSee('tax-report-document', false)
        ->assertSee(route('tax.report'), false);
});

test('VAT records require tax permission and display actual saved POS transactions', function () {
    $this->get(route('tax.vat-records'))->assertRedirect(route('login'));

    Carbon::setTestNow('2026-10-15 12:00:00 UTC');
    $sale = createRecordedVatSale('POS-20261015-ABC12345', '2026-10-15 04:30:00');
    $this->actingAs(createVatRecordsEmployee())
        ->get(route('tax.vat-records'))
        ->assertOk()
        ->assertSee('VAT Records')
        ->assertSee('POS-20261015-ABC12345')
        ->assertSee('2026-10-15')
        ->assertDontSee('VAT-2026-08-001')
        ->assertDontSee('Illustrative');

    expect($sale->vatRecord->total)->toBe('201.60');
    Carbon::setTestNow();
});
test('an empty VAT record selection shows no recorded sales', function () {
    $this->actingAs(createVatRecordsEmployee());

    Livewire::test(VatRecords::class)
        ->assertSee('No recorded POS sales.')
        ->assertDontSee('VAT-2026-08-001');
});

test('VAT records default to the Manila month and search transaction substrings case-insensitively', function () {
    Carbon::setTestNow('2026-10-15 12:00:00 UTC');
    $october = createRecordedVatSale('POS-Manila-October-01', '2026-10-01 00:00:00');
    createRecordedVatSale('POS-Manila-November-01', '2026-10-31 16:00:00');
    createRecordedVatSale('POS-Manila-September-30', '2026-09-30 15:59:59');
    $this->actingAs(createVatRecordsEmployee());

    Livewire::test(VatRecords::class)
        ->assertSee($october->transaction_number)
        ->assertDontSee('POS-Manila-November-01')
        ->assertDontSee('POS-Manila-September-30')
        ->set('search', 'oCtObEr-01')
        ->assertSee($october->transaction_number)
        ->assertDontSee('POS-Manila-November-01');

    Carbon::setTestNow();
});

test('inclusive Manila date range includes the full end date and October UTC boundary', function () {
    createRecordedVatSale('POS-BOUNDARY-OCT01', '2026-09-30 16:30:00');
    createRecordedVatSale('POS-END-2359', '2026-10-31 15:59:59');
    createRecordedVatSale('POS-NOVEMBER', '2026-10-31 16:00:00');
    $this->actingAs(createVatRecordsEmployee());

    Livewire::test(VatRecords::class)
        ->set('startDate', '2026-10-01')
        ->set('endDate', '2026-10-31')
        ->assertSee('POS-BOUNDARY-OCT01')
        ->assertSee('POS-END-2359')
        ->assertDontSee('POS-NOVEMBER')
        ->call('viewRecord', PosVatRecord::query()->where('pos_transaction_id', PosTransaction::query()->where('transaction_number', 'POS-BOUNDARY-OCT01')->value('id'))->value('id'))
        ->assertSee('October 1, 2026')
        ->assertSee('12:30 AM');
});

test('VAT detail shows saved sale lines and records cannot be changed or removed', function () {
    $inventory = Inventory::create([
        'name' => 'Pine board',
        'category' => 'Lumber',
        'qty' => 0,
        'unit' => 'piece',
        'unit_cost' => 50,
        'selling_price' => 90,
        'reorder_level' => 2,
    ]);
    $sale = createRecordedVatSale('POS-DETAIL-001', '2026-10-15 04:30:00', true, $inventory->id);
    $record = $sale->vatRecord;
    $inventory->update(['name' => 'Renamed board', 'selling_price' => 999]);
    $this->actingAs(createVatRecordsEmployee());

    Livewire::test(VatRecords::class)
        ->call('viewRecord', $record->id)
        ->assertSee('POS-DETAIL-001')
        ->assertSee('Test Customer')
        ->assertSee('Pine board')
        ->assertDontSee('Renamed board')
        ->assertSee('2.00')
        ->assertSee('₱90.00')
        ->assertDontSee('₱999.00')
        ->assertSee('₱21.60')
        ->assertSee('₱201.60')
        ->call('closeDetails')
        ->assertDontSee('VAT Record Details');

    expect(fn () => $record->update(['output_vat' => 0]))->toThrow(LogicException::class)
        ->and(fn () => $record->delete())->toThrow(LogicException::class);
});

test('historical inclusion copies source snapshots once and is repeatable', function () {
    $sale = createRecordedVatSale('POS-HISTORY-001', '2026-09-30 16:30:00', false);

    $this->artisan('tax:include-historical-pos-vat-records')->expectsOutput('Created 1 missing POS VAT record(s).')->assertSuccessful();
    $record = PosVatRecord::query()->sole();
    expect($record->taxable_sales)->toBe('180.00')
        ->and($record->output_vat)->toBe('21.60')
        ->and($record->total)->toBe('201.60')
        ->and($record->completed_at->toISOString())->toBe($sale->completed_at->toISOString());

    PosTransaction::query()->whereKey($sale->id)->update([
        'subtotal' => 999,
        'vat_amount' => 119.88,
        'total' => 1118.88,
    ]);
    $this->artisan('tax:include-historical-pos-vat-records')->expectsOutput('Created 0 missing POS VAT record(s).')->assertSuccessful();

    expect(PosVatRecord::query()->sole()->taxable_sales)->toBe('180.00')
        ->and(PosVatRecord::query()->sole()->output_vat)->toBe('21.60')
        ->and(PosVatRecord::query()->count())->toBe(1);
});

test('VAT record source is unique and required', function () {
    $sale = createRecordedVatSale('POS-UNIQUE-001', '2026-10-15 04:30:00');

    expect(fn () => PosVatRecord::create([
        'pos_transaction_id' => $sale->id,
        'taxable_sales' => 180,
        'vat_rate' => 0.12,
        'output_vat' => 21.60,
        'total' => 201.60,
        'completed_at' => $sale->completed_at,
    ]))->toThrow(UniqueConstraintViolationException::class);
    expect(fn () => PosVatRecord::create([
        'pos_transaction_id' => 999999,
        'taxable_sales' => 180,
        'vat_rate' => 0.12,
        'output_vat' => 21.60,
        'total' => 201.60,
        'completed_at' => now(),
    ]))->toThrow(LogicException::class);
});

test('tax route and detail actions remain inaccessible without tax permission', function () {
    $employee = grantEmployeeTestPermissions(Employee::create([
        'username' => 'cashier-without-tax',
        'email' => 'cashier-without-tax@example.com',
        'password' => Hash::make('vat-password'),
    ]), ['pos.checkout']);
    $this->actingAs($employee)->get(route('tax.vat-records'))->assertForbidden();

    $sale = createRecordedVatSale('POS-AUTH-001', '2026-10-15 04:30:00');
    expect(fn () => (new VatRecords)->viewRecord($sale->vatRecord->id))
        ->toThrow(HttpException::class);
});
