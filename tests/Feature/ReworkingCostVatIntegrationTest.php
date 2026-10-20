<?php

use App\Livewire\Inventory\ReworkingCostRecordManagement;
use App\Livewire\TaxCompliance\TaxReport;
use App\Livewire\TaxCompliance\VatRecords;
use App\Livewire\TaxCompliance\VatReturnPreparation;
use App\Livewire\TaxCompliance\VatSummary;
use App\Models\Employee;
use App\Models\PosTransaction;
use App\Models\PosVatRecord;
use App\Models\ReworkingCostRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function reworkingVatEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'reworking.vat',
        'email' => 'reworking.vat@example.com',
        'password' => Hash::make('vat-password'),
    ]), ['inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete', 'tax.view']);
}

function createReworkingVatRecord(string $batch, string $date, string $cost, string $inputVat): void
{
    Livewire::test(ReworkingCostRecordManagement::class)
        ->call('create')
        ->set('projectName', 'Warehouse Dismantling')
        ->set('batchNumber', $batch)
        ->set('recordDate', $date)
        ->set('description', 'Cleaning and repair of recovered steel bars')
        ->set('totalReworkingCost', $cost)
        ->set('inputVat', $inputVat)
        ->call('save')
        ->assertHasNoErrors();
}

function createReworkingVatSale(string $number, string $completedAt, string $taxable, string $vat, string $total): void
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
        'customer_name' => 'VAT Customer',
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

test('saved reworking input VAT appears in VAT records, summary, report and return preparation', function () {
    $employee = reworkingVatEmployee();
    $this->actingAs($employee);
    createReworkingVatSale('POS-REWORK-VAT', '2026-10-15 08:00:00', '1000.00', '120.00', '1120.00');
    createReworkingVatRecord('BATCH-VAT-001', '2026-10-20', '12500', '1500');

    Livewire::test(VatRecords::class)
        ->set('startDate', '2026-10-01')
        ->set('endDate', '2026-10-31')
        ->assertSee('BATCH-VAT-001')
        ->assertSee('₱12,500.00')
        ->assertSee('₱1,500.00')
        ->assertSee('Recorded input VAT');

    Livewire::test(VatSummary::class)
        ->set('selectedYear', 2026)
        ->set('selectedPeriod', 10)
        ->assertSee('Reworking cost total')
        ->assertSee('₱12,500.00')
        ->assertSee('Input VAT')
        ->assertSee('₱1,500.00')
        ->assertSee('Captured from saved Reworking Cost Records')
        ->assertSee('₱-1,380.00');

    Livewire::test(TaxReport::class)
        ->set('periodType', 'custom')
        ->set('startDate', '2026-10-01')
        ->set('endDate', '2026-10-31')
        ->assertSee('BATCH-VAT-001')
        ->assertSee('₱12,500.00')
        ->assertSee('₱1,500.00')
        ->assertSee('VAT payable estimate');

    Livewire::test(VatReturnPreparation::class)
        ->set('selectedYear', 2026)
        ->set('selectedQuarter', 4)
        ->assertSee('BATCH-VAT-001')
        ->assertSee('Reworking cost total')
        ->assertSee('₱12,500.00')
        ->assertSee('Input VAT on reworking cost records')
        ->assertSee('₱1,500.00')
        ->assertSee('POS-only VAT payable estimate')
        ->call('exportCsv')
        ->assertFileDownloaded('vat-preparation-2026-Q4.csv');
});

test('saved reworking records are read from the database by other VAT users and by the record module', function () {
    $inventoryEmployee = reworkingVatEmployee();
    $this->actingAs($inventoryEmployee);
    createReworkingVatRecord('BATCH-VAT-DB', '2026-10-20', '12500', '1500');

    $taxViewer = grantEmployeeTestPermissions(Employee::create([
        'username' => 'reworking.vat.viewer',
        'email' => 'reworking.vat.viewer@example.com',
        'password' => Hash::make('vat-password'),
    ]), ['inventory.view', 'tax.view']);

    // A different user has an empty session, so VAT compliance must read the
    // saved record table directly.
    $this->actingAs($taxViewer);

    Livewire::test(VatRecords::class)
        ->set('startDate', '2026-10-01')
        ->set('endDate', '2026-10-31')
        ->assertSee('BATCH-VAT-DB')
        ->assertSee('₱1,500.00');

    Livewire::test(VatSummary::class)
        ->set('selectedYear', 2026)
        ->set('selectedPeriod', 10)
        ->assertSee('₱1,500.00')
        ->assertSee('1 saved Reworking Cost Record(s)');

    // The module also loads the saved table when the session copy is gone.
    $this->actingAs($inventoryEmployee);
    session()->forget('demo.reworking-cost-records.employee.'.$inventoryEmployee->id);

    $databaseRecord = ReworkingCostRecord::query()->sole();

    Livewire::test(ReworkingCostRecordManagement::class)
        ->assertSee('BATCH-VAT-DB')
        ->assertSee('₱12,500.00')
        ->assertSee('₱1,500.00')
        ->call('edit', $databaseRecord->source_record_id)
        ->set('inputVat', '1800')
        ->call('save')
        ->assertHasNoErrors();

    expect(ReworkingCostRecord::query()->count())->toBe(1)
        ->and(ReworkingCostRecord::query()->sole()->input_vat)->toBe('1800.00');
});

test('editing a reworking record updates one input VAT entry without duplicating it', function () {
    $employee = reworkingVatEmployee();
    $this->actingAs($employee);
    createReworkingVatRecord('BATCH-VAT-002', '2026-11-05', '5000', '600');

    $sessionRecords = session('demo.reworking-cost-records.employee.'.$employee->id);
    Livewire::test(ReworkingCostRecordManagement::class)
        ->call('edit', $sessionRecords[0]['id'])
        ->set('inputVat', '720')
        ->call('save')
        ->assertHasNoErrors();

    expect(ReworkingCostRecord::query()->count())->toBe(1)
        ->and(ReworkingCostRecord::query()->sole()->input_vat)->toBe('720.00');

    Livewire::test(VatSummary::class)
        ->set('selectedYear', 2026)
        ->set('selectedPeriod', 11)
        ->assertSee('₱720.00')
        ->assertSee('1 saved Reworking Cost Record(s)');
});
