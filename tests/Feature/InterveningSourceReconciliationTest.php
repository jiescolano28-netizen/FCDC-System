<?php

use App\Livewire\Accounting\OpeningBooks;
use App\Livewire\Pos\PointOfSale;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\PosTransaction;
use App\Models\StockMovement;
use App\Services\Accounting\AccountingPositionSchedules;
use App\Services\Accounting\InterveningSourceReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function reconciliationEmployee(array $permissions): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'reconcile.'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => Hash::make('secret-password'),
    ]), $permissions);
}

function unpostLegacySale(AccountingJournal $journal): void
{
    DB::table('stock_movements')->where('accounting_journal_id', $journal->id)->update(['accounting_journal_id' => null]);
    DB::table('accounting_journal_lines')->where('accounting_journal_id', $journal->id)->delete();
    DB::table('accounting_journals')->where('id', $journal->id)->delete();
}

function createLegacySale(Inventory $item, Employee $cashier, string $lineCost = '50.00'): PosTransaction
{
    setupPosAccountingBooks([$item], [$item->id => '500.00']);
    $cashier->givePermissionTo('pos.checkout', 'accounting.view', 'accounting.reconcile-sources');
    test()->actingAs($cashier);

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $item->id)
        ->set('amountReceived', '201.60')
        ->call('checkout')
        ->assertHasNoErrors();

    $sale = PosTransaction::query()->with('lines')->sole();
    $journal = AccountingJournal::query()->where('source_type', 'pos_sale')->where('source_id', (string) $sale->id)->sole();
    unpostLegacySale($journal);
    if ($lineCost === '') {
        DB::table('pos_transaction_lines')->where('pos_transaction_id', $sale->id)->update(['line_cost_cents' => null]);
    }

    return $sale;
}

test('an existing completed sale is linked once without another stock issue', function () {
    $employee = reconciliationEmployee(['pos.checkout', 'accounting.view', 'accounting.reconcile-sources']);
    $item = Inventory::create([
        'name' => 'Legacy steel', 'code' => 'LEGACY-STEEL', 'category' => 'Steel', 'unit' => 'piece',
        'qty' => 10, 'unit_cost' => 50, 'selling_price' => 180, 'reorder_level' => 1, 'status' => 'active',
    ]);
    $sale = createLegacySale($item, $employee);
    $quantityAfterSale = $item->fresh()->qty;
    $movementCount = StockMovement::query()->count();
    $sources = app(InterveningSourceReconciliation::class)->sources();
    expect($sources->where('type', 'pos_sale')->pluck('id')->all())->toBe([$sale->id])
        ->and($sources->where('type', 'stock_movement')->isEmpty())->toBeTrue();

    Livewire::test(OpeningBooks::class)
        ->call('includeInterveningSource', 'pos_sale', $sale->id)
        ->assertHasNoErrors()
        ->call('includeInterveningSource', 'pos_sale', $sale->id)
        ->assertHasErrors();

    $journal = AccountingJournal::query()->where('source_type', 'pos_sale')->where('source_id', (string) $sale->id)->sole();
    expect($journal->status)->toBe('posted')
        ->and($journal->lines()->sum('debit_cents'))->toBe($journal->lines()->sum('credit_cents'))
        ->and((int) $journal->lines()->where('accounting_account_id', AccountingPostingMapping::where('source', 'cogs')->value('accounting_account_id'))->value('debit_cents'))->toBe((int) $sale->lines->sum('line_cost_cents'))
        ->and($item->fresh()->qty)->toBe($quantityAfterSale)
        ->and(StockMovement::query()->count())->toBe($movementCount);
});

test('an existing sale without frozen historical line cost is not included', function () {
    $employee = reconciliationEmployee(['pos.checkout', 'accounting.view', 'accounting.reconcile-sources']);
    $item = Inventory::create([
        'name' => 'Legacy pipe', 'code' => 'LEGACY-PIPE', 'category' => 'Pipe', 'unit' => 'piece',
        'qty' => 10, 'unit_cost' => 50, 'selling_price' => 180, 'reorder_level' => 1, 'status' => 'active',
    ]);
    $sale = createLegacySale($item, $employee, '');

    Livewire::test(OpeningBooks::class)
        ->call('includeInterveningSource', 'pos_sale', $sale->id)
        ->assertHasErrors();

    expect(AccountingJournal::query()->where('source_type', 'pos_sale')->where('source_id', (string) $sale->id)->exists())->toBeFalse();
});

test('an unvalued historical receipt is rejected without creating an accounting link', function () {
    $employee = reconciliationEmployee(['accounting.view', 'accounting.reconcile-sources']);
    $item = Inventory::create([
        'name' => 'Legacy cement', 'code' => 'LEGACY-CEMENT', 'category' => 'Cement', 'unit' => 'bag',
        'qty' => 0, 'unit_cost' => 0, 'selling_price' => 100, 'reorder_level' => 1, 'status' => 'active',
    ]);
    DB::table('inventories')->where('id', $item->id)->update(['qty' => '3.00']);
    $item->refresh();
    $movement = StockMovement::create([
        'inventory_id' => $item->id, 'posted_by' => $employee->id, 'type' => 'stock_in', 'quantity' => '3.00',
        'reason_category' => 'supplier_receipt', 'reference' => 'OLD-RECEIPT-3', 'effective_date' => now('Asia/Manila')->toDateString(),
        'posted_at' => now('UTC'),
    ]);
    setupPosAccountingBooks([$item], [$item->id => '300.00']);
    test()->actingAs($employee);

    Livewire::test(OpeningBooks::class)
        ->call('includeInterveningSource', 'stock_movement', $movement->id)
        ->assertHasErrors();

    expect(AccountingJournal::query()->where('source_type', 'stock_movement')->where('source_id', (string) $movement->id)->exists())->toBeFalse();
});

test('a valued legacy stock movement receives an accounting link without changing physical quantity', function () {
    $employee = reconciliationEmployee(['accounting.view', 'accounting.reconcile-sources']);
    test()->actingAs($employee);
    $item = Inventory::create([
        'name' => 'Legacy valve', 'code' => 'LEGACY-VALVE', 'category' => 'Valve', 'unit' => 'piece',
        'qty' => 10, 'unit_cost' => 50, 'selling_price' => 100, 'reorder_level' => 1, 'status' => 'active',
    ]);
    $accounts = setupPosAccountingBooks([$item], [$item->id => '500.00']);
    $expense = AccountingAccount::create([
        'code' => '6999', 'name' => 'Stock adjustment expense', 'type' => 'Expense',
        'classification' => 'operating_expenses', 'normal_balance' => 'debit', 'is_active' => true,
        'approved_at' => now(), 'approved_by' => $employee->id,
    ]);
    AccountingPostingMapping::create([
        'source' => 'adjustment', 'accounting_account_id' => $expense->id,
        'approved_at' => now(), 'approved_by' => $employee->id,
    ]);
    $movement = StockMovement::create([
        'inventory_id' => $item->id, 'posted_by' => $employee->id, 'type' => 'adjustment', 'quantity' => '0.00',
        'reason_category' => 'adjustment', 'reference' => 'OLD-VALUE-ADJUSTMENT', 'effective_date' => now('Asia/Manila')->toDateString(),
        'posted_at' => now('UTC'), 'value_cents' => -100, 'carrying_value_after_cents' => 49900,
    ]);
    $quantityBefore = $item->fresh()->qty;
    $movementCount = StockMovement::query()->count();

    Livewire::test(OpeningBooks::class)
        ->call('includeInterveningSource', 'stock_movement', $movement->id)
        ->assertHasNoErrors()
        ->call('includeInterveningSource', 'stock_movement', $movement->id)
        ->assertHasErrors();

    $journal = AccountingJournal::query()->where('source_type', 'stock_movement')->where('source_id', (string) $movement->id)->sole();
    expect($journal->status)->toBe('posted')
        ->and($movement->fresh()->accounting_journal_id)->toBe($journal->id)
        ->and($item->fresh()->qty)->toBe($quantityBefore)
        ->and(StockMovement::query()->count())->toBe($movementCount)
        ->and((int) $journal->lines()->where('accounting_account_id', $accounts['inventory']->id)->value('credit_cents'))->toBe(100);
    $cutover = AccountingJournal::query()->where('source_type', 'opening')->where('source_id', 'FCDC')->value('accounting_date');
    [$inventorySchedule, $coverageError] = app(AccountingPositionSchedules::class)
        ->inventoryValueAsOf(now('Asia/Manila')->toDateString(), Carbon::parse($cutover)->toDateString());
    $inventoryLedger = (int) DB::table('accounting_journal_lines')
        ->join('accounting_journals', 'accounting_journals.id', '=', 'accounting_journal_lines.accounting_journal_id')
        ->where('accounting_journals.status', 'posted')->where('accounting_journal_lines.accounting_account_id', $accounts['inventory']->id)
        ->selectRaw('SUM(debit_cents - credit_cents) as balance')->value('balance');
    expect($coverageError)->toBeNull()
        ->and($inventorySchedule)->toBe(49900)
        ->and($inventoryLedger)->toBe($inventorySchedule);
});

test('reconciliation requires its dedicated permission', function () {
    $cashier = reconciliationEmployee(['pos.checkout', 'accounting.view', 'accounting.reconcile-sources']);
    $item = Inventory::create([
        'name' => 'Permission steel', 'code' => 'PERM-STEEL', 'category' => 'Steel', 'unit' => 'piece',
        'qty' => 10, 'unit_cost' => 50, 'selling_price' => 180, 'reorder_level' => 1, 'status' => 'active',
    ]);
    $sale = createLegacySale($item, $cashier);
    $viewer = reconciliationEmployee(['accounting.view']);
    test()->actingAs($viewer);

    Livewire::test(OpeningBooks::class)
        ->call('includeInterveningSource', 'pos_sale', $sale->id)
        ->assertForbidden();
});

test('a failed source inclusion leaves no journal or stock changes', function () {
    $cashier = reconciliationEmployee(['pos.checkout', 'accounting.view', 'accounting.reconcile-sources']);
    $item = Inventory::create([
        'name' => 'Atomic steel', 'code' => 'ATOMIC-STEEL', 'category' => 'Steel', 'unit' => 'piece',
        'qty' => 10, 'unit_cost' => 50, 'selling_price' => 180, 'reorder_level' => 1, 'status' => 'active',
    ]);
    $sale = createLegacySale($item, $cashier);
    $quantityBefore = $item->fresh()->qty;
    $movementCount = StockMovement::query()->count();
    DB::table('accounting_posting_mappings')->where('source', 'output_vat')->delete();

    Livewire::test(OpeningBooks::class)
        ->call('includeInterveningSource', 'pos_sale', $sale->id)
        ->assertHasErrors();

    expect(AccountingJournal::query()->where('source_type', 'pos_sale')->where('source_id', (string) $sale->id)->exists())->toBeFalse()
        ->and($item->fresh()->qty)->toBe($quantityBefore)
        ->and(StockMovement::query()->count())->toBe($movementCount);
});

test('the review identifies a source whose accounting links disagree', function () {
    $cashier = reconciliationEmployee(['pos.checkout', 'accounting.view', 'accounting.reconcile-sources']);
    $item = Inventory::create([
        'name' => 'Duplicate-link steel', 'code' => 'DUPLINK-STEEL', 'category' => 'Steel', 'unit' => 'piece',
        'qty' => 10, 'unit_cost' => 50, 'selling_price' => 180, 'reorder_level' => 1, 'status' => 'active',
    ]);
    $sale = createLegacySale($item, $cashier);
    $periodId = AccountingJournal::query()->where('source_type', 'opening')->where('source_id', 'FCDC')->value('posting_period_id');
    $date = $sale->completed_at->timezone('Asia/Manila')->toDateString();
    $sourceJournal = AccountingJournal::create([
        'book_key' => 'FCDC', 'reference' => 'LEGACY-POS-LINK', 'source_type' => 'pos_sale',
        'source_id' => (string) $sale->id, 'accounting_date' => $date, 'posting_period_id' => $periodId,
        'description' => 'Existing source identity without completed posting', 'status' => 'draft', 'prepared_by' => $cashier->id,
    ]);
    $otherJournal = AccountingJournal::create([
        'book_key' => 'FCDC', 'reference' => 'OTHER-STOCK-LINK', 'source_type' => 'manual',
        'source_id' => 'OTHER-STOCK-LINK', 'accounting_date' => $date, 'posting_period_id' => $periodId,
        'description' => 'Unrelated existing link', 'status' => 'draft', 'prepared_by' => $cashier->id,
    ]);
    DB::table('stock_movements')->where('source_reference', 'pos_sale:'.$sale->id)
        ->update(['accounting_journal_id' => $otherJournal->id]);

    $source = app(InterveningSourceReconciliation::class)
        ->sources()->first(fn (array $source) => $source['type'] === 'pos_sale' && $source['id'] === $sale->id);

    expect($source['status'])->toBe('duplicate-link')
        ->and($sourceJournal->status)->toBe('draft');
});
