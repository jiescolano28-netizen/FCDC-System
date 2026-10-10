<?php

use App\Models\AccountingAccount;
use App\Models\AccountingPostingMapping;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Services\Accounting\OpeningBooksService;
use App\Services\Inventory\RecordValuedStockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function valuedMovementEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'valued.'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => Hash::make('secret-password'),
    ]), ['inventory.movements.record', 'inventory.valuation.approve']);
}

function valuedMovementAccount(string $code, string $classification, string $type, string $balance): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => ucfirst(str_replace('_', ' ', $classification)),
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => $balance,
        'is_active' => true,
        'approved_at' => now(),
        'approved_by' => auth()->id(),
    ]);
}

function setupValuedMovementBooks(Employee $employee, Inventory $item): void
{
    $inventory = valuedMovementAccount('1200', 'inventory', 'Asset', 'debit');
    $equity = valuedMovementAccount('3000', 'capital', 'Equity', 'credit');
    $loss = valuedMovementAccount('6100', 'operating_expense', 'Expense', 'debit');
    $receiptOffset = valuedMovementAccount('4900', 'other_income', 'Revenue', 'credit');
    $cutover = now('Asia/Manila')->toDateString();
    $books = app(OpeningBooksService::class);
    $books->saveInventoryValuation($cutover, 'Approved inventory count', [[
        'inventoryId' => (string) $item->id,
        'quantity' => (string) $item->qty,
        'value' => '1000.00',
    ]], $employee->id);
    $books->saveOpening($cutover, [
        ['accountId' => $inventory->id, 'debit' => '1000.00', 'credit' => ''],
        ['accountId' => $equity->id, 'debit' => '', 'credit' => '1000.00'],
    ], $employee->id);
    $books->approveInventoryValuation($employee->id);
    $books->approveOpening($employee->id);
    foreach ([['inventory', $inventory], ['adjustment', $loss], ['recovery_offset', $receiptOffset]] as [$source, $account]) {
        AccountingPostingMapping::create([
            'source' => $source,
            'accounting_account_id' => $account->id,
            'approved_at' => now(),
            'approved_by' => $employee->id,
        ]);
    }
}

test('valued receipts and issues use moving-average carrying value and full depletion consumes the residual', function () {
    $employee = valuedMovementEmployee();
    $this->actingAs($employee);
    $item = Inventory::create([
        'name' => 'Valued timber', 'category' => 'Lumber', 'qty' => '10.00',
        'unit' => 'piece', 'unit_cost' => '100.00', 'reorder_level' => '0',
    ]);
    setupValuedMovementBooks($employee, $item);
    $valueUnapproved = grantEmployeeTestPermissions(Employee::create([
        'username' => 'valued.viewer.'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => Hash::make('secret-password'),
    ]), ['inventory.movements.record']);
    $this->actingAs($valueUnapproved);
    \Livewire\Livewire::test(\App\Livewire\Inventory\InventoryManagement::class)
        ->set('stockItemId', $item->id)
        ->set('stockQuantity', '10.00')
        ->set('stockUnitValue', '200.00')
        ->set('stockReasonCategory', 'purchase_receipt')
        ->set('stockReference', 'REC-UNAUTHORIZED')
        ->set('stockEffectiveDate', now('Asia/Manila')->toDateString())
        ->call('recordStockIn')
        ->assertForbidden();
    $this->actingAs($employee);
    expect(DB::table('accounting_journals')->where('source_type', 'opening')->value('status'))->toBe('posted')
        ->and($item->fresh()->carrying_value_cents)->toBe(100000)
        ->and((float) $item->fresh()->unit_cost)->toBe(100.0)
        ->and(DB::table('opening_inventory_valuations')->value('status'))->toBe('approved');
    $service = app(RecordValuedStockMovement::class);

    $today = now('Asia/Manila')->toDateString();
    \Livewire\Livewire::test(\App\Livewire\Inventory\InventoryManagement::class)
        ->set('stockItemId', $item->id)
        ->set('stockQuantity', '10.00')
        ->set('stockUnitValue', '200.00')
        ->set('stockReasonCategory', 'purchase_receipt')
        ->set('stockReference', 'REC-200')
        ->set('stockEffectiveDate', $today)
        ->call('recordStockIn')
        ->assertHasNoErrors();
    $receipt = StockMovement::query()->where('inventory_id', $item->id)->where('type', 'stock_in')->firstOrFail();
    expect($item->fresh()->qty)->toBe('20.00')
        ->and($item->fresh()->carrying_value_cents)->toBe(300000)
        ->and($receipt->value_cents)->toBe(200000)
        ->and($receipt->carrying_value_after_cents)->toBe(300000)
        ->and($receipt->accountingJournal->lines->sum('debit_cents'))->toBe(200000)
        ->and($receipt->accountingJournal->lines->sum('credit_cents'))->toBe(200000);

    \Livewire\Livewire::test(\App\Livewire\Inventory\InventoryManagement::class)
        ->set('issueItemId', $item->id)
        ->set('issueQuantity', '2.00')
        ->set('issueReasonCategory', 'project_use')
        ->set('issueReference', 'JOB-42')
        ->set('issueEffectiveDate', $today)
        ->call('recordStockOut')
        ->assertHasNoErrors();
    $issue = StockMovement::query()->where('inventory_id', $item->id)->where('type', 'stock_out')->firstOrFail();
    expect($issue->value_cents)->toBe(-30000)
        ->and($issue->carrying_value_after_cents)->toBe(270000)
        ->and($item->fresh()->qty)->toBe('18.00')
        ->and((float) $item->fresh()->unit_cost)->toBe(150.0);

    $depletion = $service->handle($item->id, 'stock_out', '18.00', 'damage_loss', 'LOSS-9', $today, $employee->id);
    expect($depletion->value_cents)->toBe(-270000)
        ->and($depletion->carrying_value_after_cents)->toBe(0)
        ->and($item->fresh()->qty)->toBe('0.00');
});

test('valued movements reject backdating and roll back quantity, movement, and journal when posting mapping is unapproved', function () {
    $employee = valuedMovementEmployee();
    $this->actingAs($employee);
    $item = Inventory::create([
        'name' => 'Valued block', 'category' => 'Masonry', 'qty' => '10.00',
        'unit' => 'piece', 'unit_cost' => '100.00', 'reorder_level' => '0',
    ]);
    setupValuedMovementBooks($employee, $item);
    $service = app(RecordValuedStockMovement::class);
    $today = now('Asia/Manila')->toDateString();
    $service->handle($item->id, 'stock_out', '2.00', 'project_use', 'JOB-1', $today, $employee->id);
    expect(fn () => app(\App\Services\Inventory\RecordStockIn::class)->handle(
        $item->id, 1, 'return', null, 'QUANTITY-ONLY', $today, $employee->id,
    ))->toThrow(ValidationException::class);

    expect(fn () => $service->handle($item->id, 'stock_in', '1.00', 'return', 'BACKDATE', now('Asia/Manila')->subDay()->toDateString(), $employee->id, '100.00'))
        ->toThrow(ValidationException::class);
    $movementCount = StockMovement::count();
    $journalCount = DB::table('accounting_journals')->count();
    expect(fn () => $service->handle($item->id, 'stock_out', '99.00', 'project_use', 'TOO-MUCH', $today, $employee->id))
        ->toThrow(ValidationException::class);
    expect(fn () => $service->handle($item->id, 'stock_out', '1.00', 'project_use', 'FUTURE', now('Asia/Manila')->addDay()->toDateString(), $employee->id))
        ->toThrow(ValidationException::class);

    DB::table('accounting_posting_periods')->update(['status' => 'closed']);
    expect(fn () => $service->handle($item->id, 'stock_out', '1.00', 'project_use', 'CLOSED', $today, $employee->id))
        ->toThrow(ValidationException::class)
        ->and($item->fresh()->qty)->toBe('8.00')
        ->and(StockMovement::count())->toBe($movementCount)
        ->and(DB::table('accounting_journals')->count())->toBe($journalCount);
    DB::table('accounting_posting_periods')->update(['status' => 'open']);

    AccountingPostingMapping::where('source', 'adjustment')->update(['approved_at' => null]);
    $movementCount = StockMovement::count();
    $journalCount = DB::table('accounting_journals')->count();
    expect(fn () => $service->handle($item->id, 'stock_out', '1.00', 'project_use', 'ROLLBACK', $today, $employee->id))
        ->toThrow(ValidationException::class);

    expect($item->fresh()->qty)->toBe('8.00')
        ->and(StockMovement::count())->toBe($movementCount)
        ->and(DB::table('accounting_journals')->count())->toBe($journalCount);
});

test('counted increases require authorized approved value and preserve exact carrying value', function () {
    $employee = valuedMovementEmployee();
    $this->actingAs($employee);
    $item = Inventory::create([
        'name' => 'Counted block', 'category' => 'Masonry', 'qty' => '10.00',
        'unit' => 'piece', 'unit_cost' => '100.00', 'reorder_level' => '0',
    ]);
    setupValuedMovementBooks($employee, $item);

    $movement = app(RecordValuedStockMovement::class)->handle(
        $item->id, 'adjustment', '12.00', 'count_correction', 'COUNT-12',
        now('Asia/Manila')->toDateString(), $employee->id, '150.00',
    );

    expect($movement->quantity)->toBe('2.00')
        ->and($movement->value_cents)->toBe(30000)
        ->and($movement->carrying_value_after_cents)->toBe(130000)
        ->and($item->fresh()->carrying_value_cents)->toBe(130000)
        ->and($movement->accountingJournal->lines->sum('debit_cents'))->toBe(30000)
        ->and($movement->accountingJournal->lines->sum('credit_cents'))->toBe(30000)
        ->and(fn () => $item->fresh()->update(['unit_cost' => '999.00']))->toThrow(LogicException::class);
});
test('value corrections after consumption preserve quantity and original journals while updating remaining and consumed schedules', function () {
    $employee = valuedMovementEmployee();
    $this->actingAs($employee);
    $item = Inventory::create([
        'name' => 'Corrected timber', 'category' => 'Lumber', 'qty' => '10.00',
        'unit' => 'piece', 'unit_cost' => '100.00', 'reorder_level' => '0',
    ]);
    setupValuedMovementBooks($employee, $item);

    $receipt = app(RecordValuedStockMovement::class)->handle(
        $item->id, 'stock_in', '10.00', 'other', 'RECEIPT-19',
        now('Asia/Manila')->toDateString(), $employee->id, '20.00',
    );
    app(RecordValuedStockMovement::class)->handle(
        $item->id, 'stock_out', '4.00', 'project_use', 'ISSUE-19',
        now('Asia/Manila')->toDateString(), $employee->id,
    );
    $sourceJournal = $receipt->accountingJournal()->with('lines')->firstOrFail();

    $correction = app(\App\Services\Inventory\CorrectValuedStockMovement::class)->correct(
        $receipt->id, 'Receipt value was overstated', -1000, -1000, AccountingAccount::where('code', '6100')->value('id'), $employee->id,
    );

    expect((float) $item->fresh()->qty)->toBe(16.0)
        ->and($item->fresh()->carrying_value_cents)->toBe(95000)
        ->and($correction->quantity)->toBe('0.00')
        ->and($correction->correction_of_movement_id)->toBe($receipt->id)
        ->and($correction->correction_reason)->toBe('Receipt value was overstated')
        ->and($sourceJournal->fresh()->lines->sum('debit_cents'))->toBe(20000)
        ->and($sourceJournal->fresh()->lines->sum('credit_cents'))->toBe(20000)
        ->and($correction->accountingJournal->lines->sum('debit_cents'))->toBe(2000)
        ->and($correction->accountingJournal->lines->sum('credit_cents'))->toBe(2000);
});

test('value correction exceeding its source amount rolls back all correction effects', function () {
    $employee = valuedMovementEmployee();
    $this->actingAs($employee);
    $item = Inventory::create([
        'name' => 'Bounded timber', 'category' => 'Lumber', 'qty' => '10.00',
        'unit' => 'piece', 'unit_cost' => '100.00', 'reorder_level' => '0',
    ]);
    setupValuedMovementBooks($employee, $item);
    $receipt = app(RecordValuedStockMovement::class)->handle(
        $item->id, 'stock_in', '10.00', 'other', 'RECEIPT-BOUND',
        now('Asia/Manila')->toDateString(), $employee->id, '20.00',
    );
    $movementCount = StockMovement::count();
    $journalCount = DB::table('accounting_journals')->count();

    expect(fn () => app(\App\Services\Inventory\CorrectValuedStockMovement::class)->correct(
        $receipt->id, 'Unsupported reduction', -20001, 0, null, $employee->id,
    ))->toThrow(ValidationException::class);

    expect(StockMovement::count())->toBe($movementCount)
        ->and(DB::table('accounting_journals')->count())->toBe($journalCount)
        ->and($item->fresh()->carrying_value_cents)->toBe(120000);
});

test('failure while linking a value correction rolls back its journal and stock history', function () {
    $employee = valuedMovementEmployee();
    $this->actingAs($employee);
    $item = Inventory::create([
        'name' => 'Atomic timber', 'category' => 'Lumber', 'qty' => '10.00',
        'unit' => 'piece', 'unit_cost' => '100.00', 'reorder_level' => '0',
    ]);
    setupValuedMovementBooks($employee, $item);
    $receipt = app(RecordValuedStockMovement::class)->handle(
        $item->id, 'stock_in', '10.00', 'other', 'RECEIPT-ATOMIC',
        now('Asia/Manila')->toDateString(), $employee->id, '20.00',
    );
    $movementCount = StockMovement::count();
    $journalCount = DB::table('accounting_journals')->count();
    StockMovement::creating(function (StockMovement $movement): void {
        if ($movement->type === 'valuation_correction') {
            throw new RuntimeException('injected movement persistence failure');
        }
    });

    expect(fn () => app(\App\Services\Inventory\CorrectValuedStockMovement::class)->correct(
        $receipt->id, 'Injected failure', -1000, 0, null, $employee->id,
    ))->toThrow(RuntimeException::class);

    expect(StockMovement::count())->toBe($movementCount)
        ->and(DB::table('accounting_journals')->count())->toBe($journalCount)
        ->and($item->fresh()->carrying_value_cents)->toBe(120000);
});

test('a reversed valued receipt cannot receive a later source-linked value correction', function () {
    $employee = valuedMovementEmployee();
    $this->actingAs($employee);
    $item = Inventory::create([
        'name' => 'Reversed timber', 'category' => 'Lumber', 'qty' => '10.00',
        'unit' => 'piece', 'unit_cost' => '100.00', 'reorder_level' => '0',
    ]);
    setupValuedMovementBooks($employee, $item);
    $receipt = app(RecordValuedStockMovement::class)->handle(
        $item->id, 'stock_in', '10.00', 'other', 'RECEIPT-REVERSED',
        now('Asia/Manila')->toDateString(), $employee->id, '20.00',
    );
    StockMovement::create([
        'inventory_id' => $item->id,
        'posted_by' => $employee->id,
        'type' => 'reversal',
        'quantity' => '-10.00',
        'reason_category' => 'other',
        'reference' => 'REV-RECEIPT-REVERSED',
        'effective_date' => now('Asia/Manila')->toDateString(),
        'posted_at' => now('UTC'),
        'reverses_movement_id' => $receipt->id,
    ]);
    $movementCount = StockMovement::count();
    $journalCount = DB::table('accounting_journals')->count();

    expect(fn () => app(\App\Services\Inventory\CorrectValuedStockMovement::class)->correct(
        $receipt->id, 'Do not correct reversed source', -1000, 0, null, $employee->id,
    ))->toThrow(ValidationException::class);

    expect(StockMovement::count())->toBe($movementCount)
        ->and(DB::table('accounting_journals')->count())->toBe($journalCount)
        ->and($item->fresh()->qty)->toBe('20.00');
});
