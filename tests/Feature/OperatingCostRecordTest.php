<?php

use App\Livewire\Inventory\OperatingCostRecordManagement;
use App\Livewire\TaxCompliance\VatRecords;
use App\Livewire\TaxCompliance\VatSummary;
use App\Models\Employee;
use App\Models\OperatingCostRecord;
use App\Models\ReworkingCostRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function operatingCostEmployee(array $permissions = ['inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete']): Employee
{
    static $counter = 0;
    $counter++;

    $employee = Employee::create([
        'username' => 'operating.cost.manager.'.$counter,
        'email' => 'operating.cost'.$counter.'@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    return grantEmployeeTestPermissions($employee, $permissions);
}

test('operating cost record page appears below batch categories in the inventory navigation', function () {
    $this->actingAs(operatingCostEmployee());

    $this->get(route('inventory.operating-cost-records'))
        ->assertOk()
        ->assertSeeInOrder(['Batch categories', 'Reworking Cost Record', 'Operating Cost Record'])
        ->assertSee('id="operating-cost-batch-modal"', false)
        ->assertSee('id="operating-cost-modal"', false)
        ->assertSee('Batch / Reworking Cost')
        ->assertSee('Batch number')
        ->assertSee('Total reworking cost')
        ->assertSee('Input VAT')
        ->assertSee('Expense category')
        ->assertSee('Rent Expense')
        ->assertSee('Utilities Expense')
        ->assertSee('Repairs and Maintenance Expense')
        ->assertSee('Office Supplies Expense')
        ->assertSee('Transportation and Delivery Expense')
        ->assertSee('Cleaning Supplies Expense')
        ->assertSee('Other Operating Expenses');
});

test('the original reworking cost record page remains available at its own URL', function () {
    $this->actingAs(operatingCostEmployee());

    $this->get('/inventory/reworking-cost-records')
        ->assertOk()
        ->assertSee('Reworking Cost Record')
        ->assertSee('id="reworking-cost-modal"', false)
        ->assertSee('Total reworking cost')
        ->assertSee('Input VAT');
});

test('a batch reworking cost record can be created, edited, and deleted in the interface', function () {
    $employee = operatingCostEmployee();
    $this->actingAs($employee);

    Livewire::test(OperatingCostRecordManagement::class)
        ->call('createBatch')
        ->assertDispatched('operating-cost-batch-form-open')
        ->set('projectName', 'Warehouse Dismantling')
        ->set('batchNumber', 'BATCH-2026-001')
        ->set('batchDate', '2026-10-20')
        ->set('batchDescription', 'Cleaning, sorting, and minor repair of recovered steel bars')
        ->set('totalReworkingCost', '12500')
        ->set('inputVat', '1500')
        ->call('saveBatch')
        ->assertHasNoErrors()
        ->assertDispatched('operating-cost-batch-form-close')
        ->assertSee('BATCH-2026-001')
        ->assertSee('Warehouse Dismantling')
        ->assertSee('₱12,500.00')
        ->assertSee('₱1,500.00');

    $records = session('demo.reworking-cost-records.employee.'.$employee->id);
    expect($records)->toHaveCount(1)
        ->and($records[0]['project'])->toBe('Warehouse Dismantling')
        ->and($records[0]['total_reworking_cost'])->toBe('12500.00')
        ->and($records[0]['input_vat'])->toBe('1500.00')
        ->and($records[0]['attachment'])->toBeNull();

    $persisted = ReworkingCostRecord::query()->sole();
    expect($persisted->batch_number)->toBe('BATCH-2026-001')
        ->and($persisted->input_vat)->toBe('1500.00')
        ->and($persisted->employee_id)->toBe($employee->id);

    Livewire::test(OperatingCostRecordManagement::class)
        ->call('editBatch', $records[0]['id'])
        ->assertDispatched('operating-cost-batch-form-open')
        ->assertSet('batchNumber', 'BATCH-2026-001')
        ->set('batchNumber', 'BATCH-2026-002')
        ->set('totalReworkingCost', '13000')
        ->set('inputVat', '1560')
        ->call('saveBatch')
        ->assertHasNoErrors()
        ->assertSee('BATCH-2026-002');

    $edited = session('demo.reworking-cost-records.employee.'.$employee->id)[0];
    expect($edited['batch_number'])->toBe('BATCH-2026-002')
        ->and($edited['total_reworking_cost'])->toBe('13000.00')
        ->and($edited['input_vat'])->toBe('1560.00');

    expect(ReworkingCostRecord::query()->count())->toBe(1)
        ->and(ReworkingCostRecord::query()->sole()->input_vat)->toBe('1560.00');

    Livewire::test(OperatingCostRecordManagement::class)
        ->call('deleteBatch', $edited['id'])
        ->assertSee('No batch reworking cost records found.');

    expect(session('demo.reworking-cost-records.employee.'.$employee->id))->toBe([])
        ->and(ReworkingCostRecord::query()->count())->toBe(0);
});

test('batch input VAT saved through the interface remains available to VAT compliance', function () {
    $employee = operatingCostEmployee([
        'inventory.view',
        'inventory.create',
        'inventory.update',
        'inventory.delete',
        'tax.view',
    ]);
    $this->actingAs($employee);

    Livewire::test(OperatingCostRecordManagement::class)
        ->call('createBatch')
        ->set('projectName', 'Warehouse Dismantling')
        ->set('batchNumber', 'BATCH-VAT-UI')
        ->set('batchDate', '2026-10-20')
        ->set('batchDescription', 'Cleaning and repair of recovered steel bars')
        ->set('totalReworkingCost', '12500')
        ->set('inputVat', '1500')
        ->call('saveBatch')
        ->assertHasNoErrors();

    Livewire::test(VatRecords::class)
        ->set('startDate', '2026-10-01')
        ->set('endDate', '2026-10-31')
        ->assertSee('BATCH-VAT-UI')
        ->assertSee('₱1,500.00');

    Livewire::test(VatSummary::class)
        ->set('selectedYear', 2026)
        ->set('selectedPeriod', 10)
        ->assertSee('Reworking cost total')
        ->assertSee('₱12,500.00')
        ->assertSee('₱1,500.00');
});

test('batch supporting documents accept one PDF up to 10 MB and reject other files or larger files', function () {
    $this->actingAs(operatingCostEmployee());

    Livewire::test(OperatingCostRecordManagement::class)
        ->set('projectName', 'Residential Recovery')
        ->set('batchNumber', 'BATCH-2026-003')
        ->set('batchDate', '2026-10-20')
        ->set('batchDescription', 'Preparation and cleaning of recovered timber')
        ->set('totalReworkingCost', '8400')
        ->set('inputVat', '1008')
        ->set('batchAttachmentFile', UploadedFile::fake()->create('reworking-receipt.pdf', 120, 'application/pdf'))
        ->call('saveBatch')
        ->assertHasNoErrors()
        ->assertSee('reworking-receipt.pdf')
        ->assertSee('Download PDF');

    $record = ReworkingCostRecord::query()->sole();
    expect($record->attachment)->not->toBeNull()
        ->and($record->attachment['name'])->toBe('reworking-receipt.pdf')
        ->and($record->attachment['size'])->toBeGreaterThan(0);

    Livewire::test(OperatingCostRecordManagement::class)
        ->set('batchAttachmentFile', UploadedFile::fake()->create('reworking-receipt.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
        ->assertHasErrors(['batchAttachmentFile']);

    Livewire::test(OperatingCostRecordManagement::class)
        ->set('batchAttachmentFile', UploadedFile::fake()->create('oversized-reworking-receipt.pdf', 10241, 'application/pdf'))
        ->assertHasErrors(['batchAttachmentFile']);
});

test('an operating expense record can be created, edited, and deleted in the interface', function () {
    $employee = operatingCostEmployee();
    $this->actingAs($employee);

    Livewire::test(OperatingCostRecordManagement::class)
        ->call('setTab', 'expenses')
        ->assertSet('activeTab', 'expenses')
        ->call('create')
        ->assertDispatched('operating-cost-record-form-open')
        ->set('expenseDate', '2026-10-20')
        ->set('referenceNumber', 'OC-2026-001')
        ->set('expenseCategory', 'Rent Expense')
        ->set('expenseDescription', 'Warehouse and office rent for October')
        ->set('amount', '12500')
        ->set('paymentMethod', 'Bank Transfer')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('operating-cost-record-form-close')
        ->assertSee('OC-2026-001')
        ->assertSee('Rent Expense')
        ->assertSee('₱12,500.00')
        ->assertSee('Bank Transfer');

    $record = OperatingCostRecord::query()->sole();
    expect($record->expense_date->format('Y-m-d'))->toBe('2026-10-20')
        ->and($record->reference_number)->toBe('OC-2026-001')
        ->and($record->expense_category)->toBe('Rent Expense')
        ->and($record->description)->toBe('Warehouse and office rent for October')
        ->and($record->amount)->toBe('12500.00')
        ->and($record->payment_method)->toBe('Bank Transfer')
        ->and($record->employee_id)->toBe($employee->id);

    Livewire::test(OperatingCostRecordManagement::class)
        ->call('edit', $record->id)
        ->assertDispatched('operating-cost-record-form-open')
        ->assertSet('referenceNumber', 'OC-2026-001')
        ->assertSet('expenseCategory', 'Rent Expense')
        ->set('referenceNumber', 'OC-2026-002')
        ->set('expenseCategory', 'Utilities Expense')
        ->set('amount', '13000.50')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('OC-2026-002')
        ->assertSee('Utilities Expense')
        ->assertSee('₱13,000.50');

    expect(OperatingCostRecord::query()->count())->toBe(1);
    $record->refresh();
    expect($record->reference_number)->toBe('OC-2026-002')
        ->and($record->expense_category)->toBe('Utilities Expense')
        ->and($record->amount)->toBe('13000.50');

    Livewire::test(OperatingCostRecordManagement::class)
        ->call('delete', $record->id)
        ->assertSee('No operating cost records found.');

    expect(OperatingCostRecord::query()->count())->toBe(0);
});

test('operating expense entries do not change the input VAT read by VAT compliance', function () {
    $this->actingAs(operatingCostEmployee(['inventory.view', 'inventory.create', 'tax.view']));

    Livewire::test(OperatingCostRecordManagement::class)
        ->call('create')
        ->set('expenseDate', '2026-10-20')
        ->set('referenceNumber', 'OC-2026-010')
        ->set('expenseCategory', 'Utilities Expense')
        ->set('expenseDescription', 'Electricity bill')
        ->set('amount', '3500')
        ->set('paymentMethod', 'Cash')
        ->call('save')
        ->assertHasNoErrors();

    expect(OperatingCostRecord::query()->count())->toBe(1)
        ->and(ReworkingCostRecord::query()->count())->toBe(0);

    Livewire::test(VatSummary::class)
        ->set('selectedYear', 2026)
        ->set('selectedPeriod', 10)
        ->assertSee('₱0.00');
});

test('the expense category must be selected from the fixed dropdown', function () {
    $this->actingAs(operatingCostEmployee());

    Livewire::test(OperatingCostRecordManagement::class)
        ->set('expenseDate', '')
        ->call('save')
        ->assertHasErrors([
            'expenseDate',
            'referenceNumber',
            'expenseCategory',
            'expenseDescription',
            'amount',
            'paymentMethod',
        ]);

    Livewire::test(OperatingCostRecordManagement::class)
        ->set('expenseDate', '2026-10-20')
        ->set('referenceNumber', 'OC-2026-003')
        ->set('expenseCategory', 'Travel Expense')
        ->set('expenseDescription', 'Unlisted expense category')
        ->set('amount', '100')
        ->set('paymentMethod', 'Cash')
        ->call('save')
        ->assertHasErrors(['expenseCategory']);

    expect(OperatingCostRecord::query()->count())->toBe(0);
});

test('an expense receipt or supporting document can be uploaded, replaced, removed, and downloaded', function () {
    Storage::fake('local');

    $this->actingAs(operatingCostEmployee());

    Livewire::test(OperatingCostRecordManagement::class)
        ->set('expenseDate', '2026-10-20')
        ->set('referenceNumber', 'OC-2026-004')
        ->set('expenseCategory', 'Office Supplies Expense')
        ->set('expenseDescription', 'Printer paper and ink cartridges')
        ->set('amount', '840.25')
        ->set('paymentMethod', 'Cash')
        ->set('attachmentFile', UploadedFile::fake()->create('operating-receipt.pdf', 120, 'application/pdf'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('operating-receipt.pdf')
        ->assertSee('Download');

    $record = OperatingCostRecord::query()->sole();
    $storedPath = $record->attachment_path;

    expect($record->attachment_name)->toBe('operating-receipt.pdf')
        ->and($record->attachment_size)->toBeGreaterThan(0)
        ->and($storedPath)->not->toBeNull();

    Storage::disk('local')->assertExists($storedPath);

    Livewire::test(OperatingCostRecordManagement::class)
        ->call('downloadAttachment', $record->id)
        ->assertFileDownloaded('operating-receipt.pdf');

    Livewire::test(OperatingCostRecordManagement::class)
        ->call('edit', $record->id)
        ->set('attachmentFile', UploadedFile::fake()->create('replacement-receipt.png', 80, 'image/png'))
        ->call('save')
        ->assertHasNoErrors();

    $record->refresh();
    expect($record->attachment_name)->toBe('replacement-receipt.png');
    Storage::disk('local')->assertMissing($storedPath);
    Storage::disk('local')->assertExists($record->attachment_path);

    $replacementPath = $record->attachment_path;

    Livewire::test(OperatingCostRecordManagement::class)
        ->call('edit', $record->id)
        ->set('removeExistingAttachment', true)
        ->call('save')
        ->assertHasNoErrors();

    $record->refresh();
    expect($record->attachment_path)->toBeNull()
        ->and($record->attachment_name)->toBeNull();
    Storage::disk('local')->assertMissing($replacementPath);

    Livewire::test(OperatingCostRecordManagement::class)
        ->set('attachmentFile', UploadedFile::fake()->create('receipt.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
        ->assertHasErrors(['attachmentFile']);

    Livewire::test(OperatingCostRecordManagement::class)
        ->set('attachmentFile', UploadedFile::fake()->create('oversized-receipt.pdf', 10241, 'application/pdf'))
        ->assertHasErrors(['attachmentFile']);
});

test('operating cost record actions require inventory permissions', function () {
    $viewer = operatingCostEmployee(['inventory.view']);
    $this->actingAs($viewer);

    $this->get(route('inventory.operating-cost-records'))->assertOk();

    Livewire::test(OperatingCostRecordManagement::class)
        ->set('projectName', 'Unauthorized project')
        ->set('batchNumber', 'BATCH-2026-004')
        ->set('batchDate', '2026-10-20')
        ->set('batchDescription', 'Unauthorized batch record')
        ->set('totalReworkingCost', '100')
        ->call('saveBatch')
        ->assertForbidden();

    Livewire::test(OperatingCostRecordManagement::class)
        ->set('expenseDate', '2026-10-20')
        ->set('referenceNumber', 'OC-2026-005')
        ->set('expenseCategory', 'Rent Expense')
        ->set('expenseDescription', 'Unauthorized record')
        ->set('amount', '100')
        ->set('paymentMethod', 'Cash')
        ->call('save')
        ->assertForbidden();

    expect(OperatingCostRecord::query()->count())->toBe(0)
        ->and(ReworkingCostRecord::query()->count())->toBe(0);

    $withoutView = operatingCostEmployee([]);
    $this->actingAs($withoutView)
        ->get(route('inventory.operating-cost-records'))
        ->assertForbidden();
});
