<?php

use App\Livewire\Inventory\ReworkingCostRecordManagement;
use App\Livewire\TaxCompliance\VatRecords;
use App\Livewire\TaxCompliance\VatReturnPreparation;
use App\Livewire\TaxCompliance\VatSummary;
use App\Models\Employee;
use App\Models\ReworkingCostRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function reworkingVatLifecycleEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'temp.lifecycle',
        'email' => 'temp.lifecycle@example.com',
        'password' => Hash::make('password'),
    ]), ['inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete', 'tax.view']);
}

test('session-only reworking records still feed the VAT screens as a fallback', function () {
    $employee = reworkingVatLifecycleEmployee();
    $this->actingAs($employee);

    // Laravel stores dotted session keys as nested arrays. Older records can
    // exist only in the session, so the fallback must read the nested path.
    session()->put('demo.reworking-cost-records.employee.'.$employee->id, [[
        'id' => 99,
        'project_id' => null,
        'project' => 'Legacy Warehouse',
        'batch_number' => 'LEGACY-VAT-1',
        'date' => '2026-10-15',
        'description' => 'Session-only legacy record',
        'total_reworking_cost' => '2000.00',
        'input_vat' => '240.00',
        'attachment' => null,
    ]]);

    expect(ReworkingCostRecord::query()->count())->toBe(0);

    Livewire::test(VatRecords::class)
        ->set('startDate', '2026-10-01')
        ->set('endDate', '2026-10-31')
        ->assertSee('LEGACY-VAT-1')
        ->assertSee('₱240.00');

    Livewire::test(VatSummary::class)
        ->set('selectedYear', 2026)
        ->set('selectedPeriod', 10)
        ->assertSee('1 saved Reworking Cost Record(s)')
        ->assertSee('₱240.00')
        ->assertSee('₱-240.00');
});

test('lifecycle through the module keeps VAT screens in sync without duplicates', function () {
    $employee = reworkingVatLifecycleEmployee();
    $this->actingAs($employee);

    // Create through the module save path.
    Livewire::test(ReworkingCostRecordManagement::class)
        ->call('create')
        ->set('projectName', 'Warehouse Dismantling')
        ->set('batchNumber', 'LIFECYCLE-1')
        ->set('recordDate', '2026-10-20')
        ->set('description', 'Lifecycle check')
        ->set('totalReworkingCost', '10000')
        ->set('inputVat', '1200')
        ->call('save')
        ->assertHasNoErrors();

    expect(ReworkingCostRecord::query()->count())->toBe(1);

    // Edit through the module save path: update, not duplicate.
    Livewire::test(ReworkingCostRecordManagement::class)
        ->call('edit', ReworkingCostRecord::query()->sole()->source_record_id)
        ->set('inputVat', '1500')
        ->call('save')
        ->assertHasNoErrors();

    expect(ReworkingCostRecord::query()->count())->toBe(1)
        ->and(ReworkingCostRecord::query()->sole()->input_vat)->toBe('1500.00');

    Livewire::test(VatRecords::class)
        ->set('startDate', '2026-10-01')
        ->set('endDate', '2026-10-31')
        ->assertSee('LIFECYCLE-1')
        ->assertSee('₱1,500.00');

    Livewire::test(VatSummary::class)
        ->set('selectedYear', 2026)
        ->set('selectedPeriod', 10)
        ->assertSee('1 saved Reworking Cost Record(s)')
        ->assertSee('₱1,500.00');

    Livewire::test(VatReturnPreparation::class)
        ->set('selectedYear', 2026)
        ->set('selectedQuarter', 4)
        ->assertSee('LIFECYCLE-1')
        ->assertSee('₱1,500.00');

    // Delete through the module: the VAT screens must drop it.
    Livewire::test(ReworkingCostRecordManagement::class)
        ->call('delete', ReworkingCostRecord::query()->sole()->source_record_id);

    expect(ReworkingCostRecord::query()->count())->toBe(0);

    Livewire::test(VatRecords::class)
        ->set('startDate', '2026-10-01')
        ->set('endDate', '2026-10-31')
        ->assertDontSee('LIFECYCLE-1');

    Livewire::test(VatSummary::class)
        ->set('selectedYear', 2026)
        ->set('selectedPeriod', 10)
        ->assertDontSee('1 saved Reworking Cost Record(s)');
});
