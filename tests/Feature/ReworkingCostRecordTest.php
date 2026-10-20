<?php

use App\Livewire\Inventory\ReworkingCostRecordManagement;
use App\Models\DemolitionProject;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function reworkingCostEmployee(array $permissions = ['inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete']): Employee
{
    static $counter = 0;
    $counter++;

    $employee = Employee::create([
        'username' => 'reworking.cost.manager.'.$counter,
        'email' => 'reworking.cost'.$counter.'@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    return grantEmployeeTestPermissions($employee, $permissions);
}

test('reworking cost record page appears below batch categories in the inventory navigation', function () {
    $this->actingAs(reworkingCostEmployee());

    $this->get(route('inventory.reworking-cost-records'))
        ->assertOk()
        ->assertSeeInOrder(['Batch categories', 'Reworking Cost Record'])
        ->assertSee('id="reworking-cost-modal"', false)
        ->assertSee('Supporting document')
        ->assertSee('PDF only')
        ->assertSee('maximum 10 MB');
});

test('a reworking cost record can be created, edited, and deleted in the interface', function () {
    $employee = reworkingCostEmployee();
    $this->actingAs($employee);

    Livewire::test(ReworkingCostRecordManagement::class)
        ->call('create')
        ->assertDispatched('reworking-cost-record-form-open')
        ->set('projectName', 'Warehouse Dismantling')
        ->set('batchNumber', 'BATCH-2026-001')
        ->set('recordDate', '2026-10-20')
        ->set('description', 'Cleaning, sorting, and minor repair of recovered steel bars')
        ->set('totalReworkingCost', '12500')
        ->set('inputVat', '1500')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('reworking-cost-record-form-close')
        ->assertSee('BATCH-2026-001')
        ->assertSee('Warehouse Dismantling')
        ->assertSee('₱12,500.00');

    $records = session('demo.reworking-cost-records.employee.'.$employee->id);
    expect($records)->toHaveCount(1)
        ->and($records[0]['project'])->toBe('Warehouse Dismantling')
        ->and($records[0]['total_reworking_cost'])->toBe('12500.00')
        ->and($records[0]['input_vat'])->toBe('1500.00')
        ->and($records[0]['attachment'])->toBeNull();

    Livewire::test(ReworkingCostRecordManagement::class)
        ->call('edit', $records[0]['id'])
        ->assertDispatched('reworking-cost-record-form-open')
        ->assertSet('batchNumber', 'BATCH-2026-001')
        ->set('batchNumber', 'BATCH-2026-002')
        ->set('totalReworkingCost', '13000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('BATCH-2026-002');

    $edited = session('demo.reworking-cost-records.employee.'.$employee->id)[0];
    expect($edited['batch_number'])->toBe('BATCH-2026-002')
        ->and($edited['total_reworking_cost'])->toBe('13000.00');

    Livewire::test(ReworkingCostRecordManagement::class)
        ->call('delete', $edited['id'])
        ->assertSee('No reworking cost records found.');

    expect(session('demo.reworking-cost-records.employee.'.$employee->id))->toBe([]);
});

test('a reworking cost record can be linked to a demolition project when projects exist', function () {
    $employee = reworkingCostEmployee();
    $this->actingAs($employee);

    $project = DemolitionProject::create([
        'name' => 'Warehouse Dismantling',
        'location' => 'San Mateo, Rizal',
        'start_date' => '2026-10-01',
    ]);

    Livewire::test(ReworkingCostRecordManagement::class)
        ->call('create')
        ->assertSee($project->code)
        ->set('projectId', $project->id)
        ->set('batchNumber', 'BATCH-2026-005')
        ->set('recordDate', '2026-10-20')
        ->set('description', 'Cleaning and preparing recovered steel bars')
        ->set('totalReworkingCost', '5000')
        ->set('inputVat', '600')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee($project->code.' · '.$project->name);

    $record = session('demo.reworking-cost-records.employee.'.$employee->id)[0];
    expect($record['project_id'])->toBe($project->id)
        ->and($record['project'])->toBe($project->code.' · '.$project->name);
});

test('supporting documents accept one PDF up to 10 MB and reject other files or larger files', function () {
    $employee = reworkingCostEmployee();
    $this->actingAs($employee);

    Livewire::test(ReworkingCostRecordManagement::class)
        ->set('projectName', 'Residential Recovery')
        ->set('batchNumber', 'BATCH-2026-003')
        ->set('recordDate', '2026-10-20')
        ->set('description', 'Preparation and cleaning of recovered timber')
        ->set('totalReworkingCost', '8400')
        ->set('inputVat', '1008')
        ->set('attachmentFile', UploadedFile::fake()->create('reworking-receipt.pdf', 120, 'application/pdf'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('reworking-receipt.pdf')
        ->assertSee('Download PDF');

    $record = session('demo.reworking-cost-records.employee.'.$employee->id)[0];
    expect($record['attachment'])->not->toBeNull()
        ->and($record['attachment']['name'])->toBe('reworking-receipt.pdf')
        ->and($record['attachment']['size'])->toBeGreaterThan(0);

    Livewire::test(ReworkingCostRecordManagement::class)
        ->set('attachmentFile', UploadedFile::fake()->create('reworking-receipt.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
        ->assertHasErrors(['attachmentFile']);

    Livewire::test(ReworkingCostRecordManagement::class)
        ->set('attachmentFile', UploadedFile::fake()->create('oversized-reworking-receipt.pdf', 10241, 'application/pdf'))
        ->assertHasErrors(['attachmentFile']);
});

test('reworking cost record actions require inventory permissions', function () {
    $viewer = reworkingCostEmployee(['inventory.view']);
    $this->actingAs($viewer);

    $this->get(route('inventory.reworking-cost-records'))->assertOk();

    Livewire::test(ReworkingCostRecordManagement::class)
        ->set('projectName', 'Unauthorized project')
        ->set('batchNumber', 'BATCH-2026-004')
        ->set('recordDate', '2026-10-20')
        ->set('description', 'Unauthorized record')
        ->set('totalReworkingCost', '100')
        ->call('save')
        ->assertForbidden();

    expect(session('demo.reworking-cost-records.employee.'.$viewer->id, []))->toBe([]);

    $withoutView = reworkingCostEmployee([]);
    $this->actingAs($withoutView)
        ->get(route('inventory.reworking-cost-records'))
        ->assertForbidden();
});
