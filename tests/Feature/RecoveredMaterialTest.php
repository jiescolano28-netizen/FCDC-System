<?php

use App\Livewire\DemolitionProjects\ProjectManagement;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\DemolitionProject;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\RecoveredMaterial;
use App\Models\RecoveredMaterialAssessment;
use App\Models\StockMovement;
use App\Services\Accounting\OpeningBooksService;
use App\Services\Inventory\AssessRecoveredMaterial;
use App\Services\Inventory\RecordValuedStockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function recoveredMaterialEmployee(array $permissions = []): Employee
{
    $suffix = Employee::count() + 1;
    $employee = Employee::create([
        'username' => 'recovery-'.$suffix,
        'email' => 'recovery-'.$suffix.'@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    if ($permissions !== []) {
        $role = Role::create(['name' => 'recovery-role-'.$suffix, 'guard_name' => 'web']);
        $role->givePermissionTo(collect($permissions)->map(fn (string $name) => Permission::findOrCreate($name, 'web')));
        $employee->assignRole($role);
    }

    return $employee;
}

function recoveredMaterialProject(): DemolitionProject
{
    return DemolitionProject::create([
        'name' => 'Warehouse demolition',
        'location' => 'Quezon City',
        'start_date' => '2026-10-01',
    ]);
}

function recoveredMaterialInventory(array $overrides = []): Inventory
{
    return Inventory::create(array_merge([
        'name' => 'Recovered timber',
        'category' => 'Lumber',
        'qty' => 2,
        'unit' => 'piece',
        'unit_cost' => 5,
        'selling_price' => 8,
        'reorder_level' => 1,
        'status' => 'active',
    ], $overrides));
}

function recoveredMaterialAccount(string $code, string $classification, string $type, string $balance, Employee $employee): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => ucfirst(str_replace('_', ' ', $classification)),
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => $balance,
        'is_active' => true,
        'approved_at' => now(),
        'approved_by' => $employee->id,
    ]);
}

function setupRecoveredMaterialBooks(Employee $employee, Inventory $item, bool $approveRecoveryMapping = true): AccountingAccount
{
    $inventory = recoveredMaterialAccount('1200', 'inventory', 'Asset', 'debit', $employee);
    $equity = recoveredMaterialAccount('3000', 'capital', 'Equity', 'credit', $employee);
    $loss = recoveredMaterialAccount('6100', 'operating_expense', 'Expense', 'debit', $employee);
    $counterpart = recoveredMaterialAccount('4900', 'other_income', 'Revenue', 'credit', $employee);
    $baseline = Inventory::create([
        'name' => 'Recovery opening baseline',
        'category' => 'Materials',
        'qty' => 10,
        'unit' => 'piece',
        'unit_cost' => 100,
        'selling_price' => 120,
        'reorder_level' => 0,
        'status' => 'active',
    ]);
    $value = (int) round((float) $item->qty * (float) $item->unit_cost * 100);
    $openingLines = [[
        'inventoryId' => (string) $baseline->id,
        'quantity' => '10',
        'value' => '1000.00',
    ]];
    $journalLines = [
        ['accountId' => $inventory->id, 'debit' => '1000.00', 'credit' => ''],
        ['accountId' => $equity->id, 'debit' => '', 'credit' => '1000.00'],
    ];
    $openingLines[] = [
        'inventoryId' => (string) $item->id,
        'quantity' => (string) $item->qty,
        'value' => number_format($value / 100, 2, '.', ''),
    ];
    $journalValue = 100000 + $value;
    $journalLines[0]['debit'] = number_format($journalValue / 100, 2, '.', '');
    $journalLines[1]['credit'] = number_format($journalValue / 100, 2, '.', '');
    $cutover = now('Asia/Manila')->toDateString();
    $books = app(OpeningBooksService::class);
    $books->saveInventoryValuation($cutover, 'Approved inventory count', $openingLines, $employee->id);
    $books->saveOpening($cutover, $journalLines, $employee->id);
    $books->approveInventoryValuation($employee->id);
    $books->approveOpening($employee->id);
    foreach ([['inventory', $inventory], ['adjustment', $loss], ['recovery_offset', $counterpart]] as [$source, $account]) {
        AccountingPostingMapping::create([
            'source' => $source,
            'accounting_account_id' => $account->id,
            'approved_at' => $source === 'recovery_offset' && ! $approveRecoveryMapping ? null : now(),
            'approved_by' => $source === 'recovery_offset' && ! $approveRecoveryMapping ? null : $employee->id,
        ]);
    }

    return $counterpart;
}

test('recovered material assessment reconciles quantities and posts only accepted stock to the linked ledger', function () {
    $employee = recoveredMaterialEmployee(['demolition-projects.view', 'demolition-projects.manage', 'inventory.movements.record', 'inventory.valuation.approve']);
    $this->actingAs($employee);
    $project = recoveredMaterialProject();
    $item = recoveredMaterialInventory(['qty' => 2]);
    $counterpart = setupRecoveredMaterialBooks($employee, $item);

    Livewire::test(ProjectManagement::class)
        ->set('recoveryProjectId', $project->id)
        ->set('recoveryMaterial', 'Salvaged timber')
        ->set('recoveryQuantity', '5.50')
        ->set('recoveryUnit', 'piece')
        ->set('recoveryCondition', 'fair')
        ->set('recoveryNotes', 'Stored under cover')
        ->call('recordRecovery')
        ->assertHasNoErrors();

    $recovery = RecoveredMaterial::firstOrFail();
    expect(fn () => app(RecordValuedStockMovement::class)->handle(
        $item->id,
        'stock_in',
        '1.00',
        'recovered_material',
        'Recovery #'.$recovery->id,
        now('Asia/Manila')->toDateString(),
        $employee->id,
        '10.00',
        'Unlinked recovery attempt',
        $project->id,
        $recovery->id,
    ))->toThrow(ValidationException::class);
    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id)
        ->set('acceptedQuantity', '3.00')
        ->set('rejectedQuantity', '2.50')
        ->set('rejectionReason', '')
        ->set('inventoryId', $item->id)
        ->call('saveAssessment')
        ->assertHasErrors(['rejectionReason']);

    expect(RecoveredMaterialAssessment::count())->toBe(0)
        ->and((float) $item->fresh()->qty)->toBe(2.0);

    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id)
        ->set('acceptedQuantity', '3.00')
        ->set('rejectedQuantity', '2.50')
        ->set('rejectionReason', 'Warped beyond reuse')
        ->set('recoveryUnitValue', '10.00')
        ->set('inventoryId', $item->id)
        ->call('saveAssessment')
        ->assertHasNoErrors();

    $assessment = RecoveredMaterialAssessment::firstOrFail();
    $movement = StockMovement::findOrFail($assessment->stock_movement_id);
    expect((float) $item->fresh()->qty)->toBe(5.0)
        ->and($assessment->accepted_quantity)->toBe('3.00')
        ->and($assessment->rejected_quantity)->toBe('2.50')
        ->and($movement->type)->toBe('stock_in')
        ->and((float) $movement->quantity)->toBe(3.0)
        ->and($movement->demolition_project_id)->toBe($project->id)
        ->and($movement->recovered_material_id)->toBe($recovery->id)
        ->and($movement->reason_category)->toBe('recovered_material')
        ->and(Inventory::availableForSale()->whereKey($item->id)->exists())->toBeTrue();
    $journal = $movement->accountingJournal()->with('lines.account')->firstOrFail();
    expect($assessment->assigned_unit_value_cents)->toBe(1000)
        ->and($assessment->assigned_value_cents)->toBe(3000)
        ->and($assessment->counterpart_accounting_account_id)->toBe($counterpart->id)
        ->and($assessment->valuation_approved_by)->toBe($employee->id)
        ->and($movement->value_cents)->toBe(3000)
        ->and($journal->source_type)->toBe('stock_movement')
        ->and($journal->lines->sum('debit_cents'))->toBe(3000)
        ->and($journal->lines->sum('credit_cents'))->toBe(3000)
        ->and($journal->lines->pluck('account.classification')->all())->toContain('inventory', 'other_income')
        ->and($journal->lines->pluck('account.classification')->all())->not->toContain('sales', 'accounts_payable');

    Livewire::test(ProjectManagement::class)
        ->assertSee('approved recovery value PHP 30.00')
        ->assertSee($counterpart->name)
        ->assertSee($journal->reference)
        ->assertSee('Warped beyond reuse');
    expect(fn () => $recovery->update(['quantity' => 9]))->toThrow(LogicException::class)
        ->and(fn () => $assessment->update(['accepted_quantity' => 4]))->toThrow(LogicException::class)
        ->and(fn () => $assessment->delete())->toThrow(LogicException::class);
});

test('unapproved recovery valuation rolls back a newly created inventory item and all posting effects', function () {
    $employee = recoveredMaterialEmployee(['demolition-projects.view', 'demolition-projects.manage', 'inventory.movements.record']);
    $this->actingAs($employee);
    $baselineItem = recoveredMaterialInventory(['qty' => 1]);
    setupRecoveredMaterialBooks($employee, $baselineItem);
    $project = recoveredMaterialProject();
    $recovery = RecoveredMaterial::create([
        'demolition_project_id' => $project->id,
        'recorded_by' => $employee->id,
        'material' => 'Salvaged aluminum',
        'quantity' => 2,
        'unit' => 'kg',
        'condition' => 'good',
    ]);

    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id)
        ->set('acceptedQuantity', '2')
        ->set('rejectedQuantity', '0')
        ->set('recoveryUnitValue', '5.00')
        ->set('inventoryChoice', 'new')
        ->set('newInventoryName', 'Unapproved aluminum')
        ->set('newInventoryCategory', 'Metals')
        ->call('saveAssessment')
        ->assertForbidden();

    expect(Inventory::where('name', 'Unapproved aluminum')->exists())->toBeFalse()
        ->and(RecoveredMaterialAssessment::count())->toBe(0)
        ->and(StockMovement::where('reason_category', 'recovered_material')->count())->toBe(0)
        ->and(AccountingJournal::where('source_type', 'stock_movement')->count())->toBe(0);
});

test('accepted recovery is rejected when the recovery counterpart policy is unapproved', function () {
    $employee = recoveredMaterialEmployee(['demolition-projects.view', 'demolition-projects.manage', 'inventory.movements.record', 'inventory.valuation.approve']);
    $this->actingAs($employee);
    $item = recoveredMaterialInventory(['qty' => 2]);
    setupRecoveredMaterialBooks($employee, $item, false);
    $project = recoveredMaterialProject();
    $recovery = RecoveredMaterial::create([
        'demolition_project_id' => $project->id,
        'recorded_by' => $employee->id,
        'material' => 'Unapproved stone',
        'quantity' => 2,
        'unit' => 'piece',
        'condition' => 'good',
    ]);

    expect(fn () => app(AssessRecoveredMaterial::class)->handle($recovery->id, [
        'accepted_quantity' => '2',
        'rejected_quantity' => '0',
        'assigned_unit_value' => '25.00',
        'inventory_id' => $item->id,
    ], $employee->id))->toThrow(ValidationException::class);

    expect((float) $item->fresh()->qty)->toBe(2.0)
        ->and(RecoveredMaterialAssessment::count())->toBe(0)
        ->and(StockMovement::where('reason_category', 'recovered_material')->count())->toBe(0)
        ->and(AccountingJournal::where('source_type', 'stock_movement')->count())->toBe(0);
});

test('recovered material acceptance rejects a zero-value fallback', function () {
    $employee = recoveredMaterialEmployee(['demolition-projects.view', 'demolition-projects.manage', 'inventory.movements.record', 'inventory.valuation.approve']);
    $this->actingAs($employee);
    $item = recoveredMaterialInventory(['qty' => 2]);
    setupRecoveredMaterialBooks($employee, $item);
    $project = recoveredMaterialProject();
    $recovery = RecoveredMaterial::create([
        'demolition_project_id' => $project->id,
        'recorded_by' => $employee->id,
        'material' => 'Scrap timber',
        'quantity' => 1,
        'unit' => 'piece',
        'condition' => 'fair',
    ]);

    expect(fn () => app(AssessRecoveredMaterial::class)->handle($recovery->id, [
        'accepted_quantity' => '1',
        'rejected_quantity' => '0',
        'assigned_unit_value' => '0.00',
        'inventory_id' => $item->id,
    ], $employee->id))->toThrow(ValidationException::class);

    expect((float) $item->fresh()->qty)->toBe(2.0)
        ->and(RecoveredMaterialAssessment::count())->toBe(0)
        ->and(StockMovement::where('reason_category', 'recovered_material')->count())->toBe(0);
});

test('assessment rejects unreconciled quantities and inventory items with a different unit', function () {
    $employee = recoveredMaterialEmployee(['demolition-projects.view', 'demolition-projects.manage']);
    $this->actingAs($employee);
    $project = recoveredMaterialProject();
    $recovery = RecoveredMaterial::create([
        'demolition_project_id' => $project->id,
        'recorded_by' => $employee->id,
        'material' => 'Reclaimed steel',
        'quantity' => 4,
        'unit' => 'kg',
        'condition' => 'good',
    ]);
    $wrongUnit = recoveredMaterialInventory(['unit' => 'piece']);

    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id)
        ->set('acceptedQuantity', '2')
        ->set('rejectedQuantity', '1')
        ->set('inventoryId', $wrongUnit->id)
        ->call('saveAssessment')
        ->assertHasErrors(['acceptedQuantity']);

    expect(fn () => app(AssessRecoveredMaterial::class)->handle($recovery->id, [
        'accepted_quantity' => '3',
        'rejected_quantity' => '1',
        'assigned_unit_value' => '10.00',
        'inventory_id' => $wrongUnit->id,
    ], $employee->id))->toThrow(ValidationException::class);

    expect(RecoveredMaterialAssessment::count())->toBe(0)
        ->and((float) $wrongUnit->fresh()->qty)->toBe(2.0);
});


test('assessment can create an inventory item with the recovered unit and receive accepted quantity', function () {
    $employee = recoveredMaterialEmployee(['demolition-projects.view', 'demolition-projects.manage', 'inventory.movements.record', 'inventory.valuation.approve']);
    $this->actingAs($employee);
    $baselineItem = recoveredMaterialInventory(['qty' => 1]);
    setupRecoveredMaterialBooks($employee, $baselineItem);
    $project = recoveredMaterialProject();
    $recovery = RecoveredMaterial::create([
        'demolition_project_id' => $project->id,
        'recorded_by' => $employee->id,
        'material' => 'Salvaged copper wire',
        'quantity' => 12.5,
        'unit' => 'meter',
        'condition' => 'fair',
    ]);

    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id)
        ->set('acceptedQuantity', '10.00')
        ->set('rejectedQuantity', '2.50')
        ->set('rejectionReason', 'Insulation damaged')
        ->set('inventoryChoice', 'new')
        ->set('newInventoryName', 'Recovered copper wire')
        ->set('newInventoryCategory', 'Electrical')
        ->set('recoveryUnitValue', '4.75')
        ->call('saveAssessment')
        ->assertHasNoErrors();

    $item = Inventory::where('name', 'Recovered copper wire')->firstOrFail();
    expect($item->unit)->toBe('meter')
        ->and($item->category)->toBe('Electrical')
        ->and($item->unit_cost)->toBe('4.75')
        ->and((float) $item->qty)->toBe(10.0)
        ->and(Inventory::availableForSale()->whereKey($item->id)->exists())->toBeFalse();

    $item->update(['selling_price' => 6.5]);
    expect(Inventory::availableForSale()->whereKey($item->id)->exists())->toBeTrue();
});
test('a partially accepted recovery can be reassessed through a linked current-period correction', function () {
    $employee = recoveredMaterialEmployee(['demolition-projects.view', 'demolition-projects.manage', 'inventory.movements.record', 'inventory.valuation.approve']);
    $this->actingAs($employee);
    $project = recoveredMaterialProject();
    $item = recoveredMaterialInventory(['qty' => 0]);
    setupRecoveredMaterialBooks($employee, $item);
    $recovery = RecoveredMaterial::create([
        'demolition_project_id' => $project->id,
        'recorded_by' => $employee->id,
        'material' => 'Reusable boards',
        'quantity' => 5,
        'unit' => 'piece',
        'condition' => 'good',
    ]);

    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id)
        ->set('acceptedQuantity', '5')
        ->set('rejectedQuantity', '0')
        ->set('recoveryUnitValue', '10.00')
        ->set('inventoryId', $item->id)
        ->call('saveAssessment')
        ->assertHasNoErrors();
    $original = RecoveredMaterialAssessment::firstOrFail();

    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id, true)
        ->set('acceptedQuantity', '3')
        ->set('rejectedQuantity', '2')
        ->set('rejectionReason', 'Two boards cracked on recheck')
        ->set('recoveryUnitValue', '10.00')
        ->set('inventoryId', $item->id)
        ->set('assessmentCorrectionReason', 'Second inspection found cracks')
        ->call('saveAssessment')
        ->assertHasNoErrors();

    $corrected = RecoveredMaterialAssessment::query()->where('supersedes_assessment_id', $original->id)->firstOrFail();
    expect((float) $item->fresh()->qty)->toBe(3.0)
        ->and($item->fresh()->carrying_value_cents)->toBe(3000)
        ->and((float) $original->fresh()->accepted_quantity)->toBe(5.0)
        ->and((float) $corrected->accepted_quantity)->toBe(3.0)
        ->and((float) $corrected->rejected_quantity)->toBe(2.0)
        ->and($corrected->stockMovement->correction_of_movement_id)->toBe($original->stock_movement_id)
        ->and($corrected->stockMovement->accountingJournal->correction_of_id)->toBe($original->stockMovement->accounting_journal_id)
        ->and($corrected->stockMovement->correction_reason)->toBe('Second inspection found cracks');
    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id, true)
        ->set('acceptedQuantity', '5')
        ->set('rejectedQuantity', '0')
        ->set('recoveryUnitValue', '10.00')
        ->set('inventoryId', $item->id)
        ->set('assessmentCorrectionReason', 'Accepted quantity restored after verification')
        ->call('saveAssessment')
        ->assertHasNoErrors();
    $restored = RecoveredMaterialAssessment::query()->where('supersedes_assessment_id', $corrected->id)->firstOrFail();
    expect((float) $item->fresh()->qty)->toBe(5.0)
        ->and($item->fresh()->carrying_value_cents)->toBe(5000)
        ->and($restored->stockMovement->correction_of_movement_id)->toBe($corrected->stock_movement_id)
        ->and($restored->stockMovement->accountingJournal->lines->sum('debit_cents'))->toBe(2000)
        ->and($restored->stockMovement->accountingJournal->lines->sum('credit_cents'))->toBe(2000);
});
test('recovery reassessment refuses to reverse unavailable accepted stock without partial effects', function () {
    $employee = recoveredMaterialEmployee(['demolition-projects.view', 'demolition-projects.manage', 'inventory.movements.record', 'inventory.valuation.approve']);
    $this->actingAs($employee);
    $project = recoveredMaterialProject();
    $item = recoveredMaterialInventory(['qty' => 0]);
    setupRecoveredMaterialBooks($employee, $item);
    $recovery = RecoveredMaterial::create([
        'demolition_project_id' => $project->id,
        'recorded_by' => $employee->id,
        'material' => 'Recovered panels',
        'quantity' => 5,
        'unit' => 'piece',
        'condition' => 'good',
    ]);
    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id)
        ->set('acceptedQuantity', '5')
        ->set('rejectedQuantity', '0')
        ->set('recoveryUnitValue', '10.00')
        ->set('inventoryId', $item->id)
        ->call('saveAssessment')
        ->assertHasNoErrors();
    $original = RecoveredMaterialAssessment::firstOrFail();
    app(RecordValuedStockMovement::class)->handle(
        $item->id, 'stock_out', '3.00', 'project_use', 'CONSUMED-PANELS',
        now('Asia/Manila')->toDateString(), $employee->id,
    );
    $movementCount = StockMovement::count();
    $journalCount = AccountingJournal::count();

    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id, true)
        ->set('acceptedQuantity', '1')
        ->set('rejectedQuantity', '4')
        ->set('rejectionReason', 'Four unavailable panels')
        ->set('recoveryUnitValue', '10.00')
        ->set('inventoryId', $item->id)
        ->set('assessmentCorrectionReason', 'Recheck found four panels already consumed')
        ->call('saveAssessment')
        ->assertHasErrors(['movement']);

    expect((float) $item->fresh()->qty)->toBe(2.0)
        ->and(RecoveredMaterialAssessment::count())->toBe(1)
        ->and($original->fresh()->stockMovement->reversal)->toBeNull()
        ->and(StockMovement::count())->toBe($movementCount)
        ->and(AccountingJournal::count())->toBe($journalCount);
});

test('failed recovery correction assessment rolls back the correction movement and journal', function () {
    $employee = recoveredMaterialEmployee(['demolition-projects.view', 'demolition-projects.manage', 'inventory.movements.record', 'inventory.valuation.approve']);
    $this->actingAs($employee);
    $project = recoveredMaterialProject();
    $item = recoveredMaterialInventory(['qty' => 0]);
    setupRecoveredMaterialBooks($employee, $item);
    $recovery = RecoveredMaterial::create([
        'demolition_project_id' => $project->id,
        'recorded_by' => $employee->id,
        'material' => 'Atomic panels',
        'quantity' => 5,
        'unit' => 'piece',
        'condition' => 'good',
    ]);
    Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id)
        ->set('acceptedQuantity', '5')
        ->set('rejectedQuantity', '0')
        ->set('recoveryUnitValue', '10.00')
        ->set('inventoryId', $item->id)
        ->call('saveAssessment')
        ->assertHasNoErrors();
    $movementCount = StockMovement::count();
    $journalCount = AccountingJournal::count();
    RecoveredMaterialAssessment::creating(function (RecoveredMaterialAssessment $assessment): void {
        if ($assessment->supersedes_assessment_id !== null) {
            throw new RuntimeException('injected reassessment persistence failure');
        }
    });

    expect(fn () => Livewire::test(ProjectManagement::class)
        ->call('beginAssessment', $recovery->id, true)
        ->set('acceptedQuantity', '3')
        ->set('rejectedQuantity', '2')
        ->set('rejectionReason', 'Two panels cracked')
        ->set('recoveryUnitValue', '10.00')
        ->set('inventoryId', $item->id)
        ->set('assessmentCorrectionReason', 'Correction write rollback')
        ->call('saveAssessment'))->toThrow(RuntimeException::class);

    expect((float) $item->fresh()->qty)->toBe(5.0)
        ->and($item->fresh()->carrying_value_cents)->toBe(5000)
        ->and(RecoveredMaterialAssessment::count())->toBe(1)
        ->and(StockMovement::count())->toBe($movementCount)
        ->and(AccountingJournal::count())->toBe($journalCount);
});

test('recovery management is independent of inventory movement permission', function () {
    $project = recoveredMaterialProject();
    $recovery = RecoveredMaterial::create([
        'demolition_project_id' => $project->id,
        'material' => 'Salvaged brick',
        'quantity' => 10,
        'unit' => 'piece',
        'condition' => 'good',
    ]);
    $inventoryOnly = recoveredMaterialEmployee(['inventory.movements.record']);
    $this->actingAs($inventoryOnly);

    Livewire::test(ProjectManagement::class)->call('beginAssessment', $recovery->id)->assertForbidden();

    expect(RecoveredMaterialAssessment::count())->toBe(0);
});

test('a fully rejected recovery saves its assessment without a stock movement', function () {
    $employee = recoveredMaterialEmployee();
    $project = recoveredMaterialProject();
    $item = recoveredMaterialInventory(['qty' => 2]);
    $recovery = RecoveredMaterial::create([
        'demolition_project_id' => $project->id,
        'recorded_by' => $employee->id,
        'material' => 'Rejected timber',
        'quantity' => 5,
        'unit' => 'piece',
        'condition' => 'poor',
    ]);

    $assessment = app(AssessRecoveredMaterial::class)->handle($recovery->id, [
        'accepted_quantity' => '0',
        'rejected_quantity' => '5',
        'rejection_reason' => 'Not reusable',
        'inventory_id' => $item->id,
    ], $employee->id);

    expect($assessment->stock_movement_id)->toBeNull()
        ->and((float) $item->fresh()->qty)->toBe(2.0);
});
