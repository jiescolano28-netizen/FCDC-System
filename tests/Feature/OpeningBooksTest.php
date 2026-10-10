<?php

use App\Livewire\Accounting\OpeningBooks;
use App\Models\AccountingAccount;
use App\Models\AccountingJournalLine;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Employee;
use App\Services\Accounting\OpeningBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('opening inventory schedule reconciles to cutover stock, approves its carrying value without adding stock, and rejects mismatches', function () {
    $preparer = openingBooksEmployee(['accounting.view', 'accounting.maintain-opening-books']);
    $reviewer = openingBooksEmployee(['accounting.view', 'accounting.approve-opening-books']);
    $inventoryAccount = approvedOpeningAccount('1200', 'inventory', 'Asset', 'debit');
    $capital = approvedOpeningAccount('3000', 'capital', 'Equity', 'credit');
    $cash = approvedOpeningAccount('1000', 'cash', 'Asset', 'debit');
    $item = Inventory::create([
        'name' => 'Opening cement',
        'category' => 'Cement',
        'qty' => '12.50',
        'unit' => 'bag',
        'unit_cost' => '99.99',
        'reorder_level' => '0',
    ]);
    $cutover = now('Asia/Manila')->toDateString();
    $movementCount = StockMovement::where('inventory_id', $item->id)->count();
    $other = Inventory::create([
        'name' => 'Additional cement',
        'category' => 'Cement',
        'qty' => '4.00',
        'unit' => 'bag',
        'unit_cost' => '1.00',
        'reorder_level' => '0',
    ]);

    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', $cutover)
        ->set('lines', [
            ['accountId' => (string) $cash->id, 'debit' => '35.25', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '35.25'],
        ])
        ->call('saveOpening')
        ->assertHasErrors('opening');
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', $cutover)
        ->set('inventoryEvidence', 'Negative quantity')
        ->set('inventoryLines', [
            ['inventoryId' => (string) $item->id, 'quantity' => '-1.00', 'value' => '31.25'],
            ['inventoryId' => (string) $other->id, 'quantity' => '4.00', 'value' => '4.00'],
        ])
        ->call('saveInventoryValuation')
        ->assertHasErrors('inventoryLines');
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', $cutover)
        ->set('inventoryEvidence', 'Unsupported quantity/value pair')
        ->set('inventoryLines', [
            ['inventoryId' => (string) $item->id, 'quantity' => '12.50', 'value' => '0.00'],
            ['inventoryId' => (string) $other->id, 'quantity' => '4.00', 'value' => '4.00'],
        ])
        ->call('saveInventoryValuation')
        ->assertHasErrors('inventoryLines.0');
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', $cutover)
        ->set('inventoryEvidence', 'Count sheet with mismatch')
        ->set('inventoryLines', [
            ['inventoryId' => (string) $item->id, 'quantity' => '12.50', 'value' => '31.25'],
            ['inventoryId' => (string) $other->id, 'quantity' => '3.00', 'value' => '3.00'],
        ])
        ->call('saveInventoryValuation')
        ->assertHasErrors('inventoryLines.1.quantity');
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', $cutover)
        ->set('inventoryEvidence', 'Count sheet INV-OPEN-2026')
        ->set('inventoryLines', [
            ['inventoryId' => (string) $item->id, 'quantity' => '12.50', 'value' => '31.25'],
            ['inventoryId' => (string) $other->id, 'quantity' => '4.00', 'value' => '4.00'],
        ])
        ->call('saveInventoryValuation')
        ->assertHasNoErrors();
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', $cutover)
        ->set('lines', [
            ['accountId' => (string) $inventoryAccount->id, 'debit' => '35.25', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '35.25'],
        ])
        ->call('saveOpening')
        ->assertHasNoErrors();
    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('approveOpening')
        ->assertHasErrors('opening');
    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('approveInventoryValuation')
        ->assertHasNoErrors()
        ->assertSee('Opening valuation approved');

    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->call('reviseInventoryValuation')
        ->set('inventoryEvidence', 'Reviewed count sheet INV-OPEN-2026')
        ->call('saveInventoryValuation')
        ->assertHasNoErrors();
    expect(app(OpeningBooksService::class)->readiness()['inventory'])->toBe('Opening inventory valuation pending approval');
    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('approveInventoryValuation')
        ->assertHasNoErrors();
    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', $cutover)
        ->set('lines', [
            ['accountId' => (string) $inventoryAccount->id, 'debit' => '31.24', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '31.24'],
        ])
        ->call('saveOpening')
        ->assertHasErrors('opening');
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', $cutover)
        ->set('lines', [
            ['accountId' => (string) $inventoryAccount->id, 'debit' => '35.25', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '35.25'],
        ])
        ->call('saveOpening')
        ->assertHasNoErrors();
    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('approveOpening')
        ->assertHasNoErrors()
        ->assertSee('Opening stock schedule reconciled')
        ->assertSee('12.50')
        ->assertSee('2.5000')
        ->assertSee('31.25');
    $valuation = App\Models\OpeningInventoryValuation::with('lines')->firstOrFail();
    $valuationLine = $valuation->lines->firstOrFail();
    expect(fn () => $valuation->update(['evidence_reference' => 'rewritten']))
        ->toThrow(LogicException::class, 'Posted opening inventory valuation is immutable.')
        ->and(fn () => $valuationLine->update(['carrying_value_cents' => 1]))
        ->toThrow(LogicException::class, 'Approved opening inventory valuation lines are immutable.');

    expect(StockMovement::where('inventory_id', $item->id)->count())->toBe($movementCount)
        ->and($item->fresh()->qty)->toBe('12.50')
        ->and((int) DB::table('opening_inventory_valuation_lines')->where('inventory_id', $item->id)->value('carrying_value_cents'))->toBe(3125)
        ->and(app(OpeningBooksService::class)->readiness()['inventory'])->toBe('Opening stock schedule reconciled');


});

function openingBooksEmployee(array $permissions): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'opening.'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => Hash::make('opening-password'),
    ]), $permissions);
}

function approvedOpeningAccount(string $code, string $classification, string $type, string $normalBalance): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => ucfirst($classification),
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => $normalBalance,
        'is_active' => true,
        'approved_at' => now(),
        'approved_by' => auth()->id(),
    ]);
}

test('an authorized reviewer approves one balanced cash and capital opening at a Manila cutover date', function () {
    $preparer = openingBooksEmployee(['accounting.view', 'accounting.maintain-opening-books']);
    $reviewer = openingBooksEmployee(['accounting.view', 'accounting.approve-opening-books']);
    $cash = approvedOpeningAccount('1000', 'cash', 'Asset', 'debit');
    $capital = approvedOpeningAccount('3000', 'capital', 'Equity', 'credit');

    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-07-01')
        ->set('lines', [
            ['accountId' => (string) $cash->id, 'debit' => '100.00', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '100.00'],
        ])
        ->call('saveOpening')
        ->assertHasNoErrors()
        ->assertSee('Pending approval');

    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('approveOpening')
        ->assertHasNoErrors()
        ->assertSee('Approved cutover: July 1, 2026')
        ->assertSee('Production activation unavailable');

    expect(DB::table('accounting_journals')->where('source_type', 'opening')->count())->toBe(1)
        ->and(DB::table('accounting_journal_lines')->sum('debit_cents'))->toBe(10000)
        ->and(DB::table('accounting_journal_lines')->sum('credit_cents'))->toBe(10000)
        ->and(DB::table('accounting_journals')->value('approved_by'))->toBe($reviewer->id)
        ->and(DB::table('accounting_posting_periods')->value('starts_on'))->toBe('2026-01-01')
        ->and(DB::table('accounting_posting_periods')->value('ends_on'))->toBe('2026-12-31');
});

test('opening approval rejects unequal and empty lines, unapproved accounts, duplicate posting and unauthorized reviewers', function () {
    $preparer = openingBooksEmployee(['accounting.view', 'accounting.maintain-opening-books']);
    $reviewer = openingBooksEmployee(['accounting.view', 'accounting.approve-accounts']);
    $cash = approvedOpeningAccount('1000', 'cash', 'Asset', 'debit');
    $capital = approvedOpeningAccount('3000', 'capital', 'Equity', 'credit');
    $pending = AccountingAccount::create([
        'code' => '1010',
        'name' => 'Pending Cash',
        'type' => 'Asset',
        'classification' => 'cash',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);

    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-07-01')
        ->set('lines', [
            ['accountId' => (string) $cash->id, 'debit' => '100.00', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '99.99'],
        ])
        ->call('saveOpening')
        ->assertHasErrors('lines');

    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-07-01')
        ->set('lines', [])
        ->call('saveOpening')
        ->assertHasErrors('lines');
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-07-01')
        ->set('lines', [
            ['accountId' => (string) $cash->id, 'debit' => '-1.00', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '1.00'],
        ])
        ->call('saveOpening')
        ->assertHasErrors('lines');
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-07-01')
        ->set('lines', [
            ['accountId' => (string) $cash->id, 'debit' => '1.001', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '1.00'],
        ])
        ->call('saveOpening')
        ->assertHasErrors('lines');
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-07-01')
        ->set('lines', [
            ['accountId' => (string) $pending->id, 'debit' => '1.00', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '1.00'],
        ])
        ->call('saveOpening')
        ->assertHasErrors('lines.0.accountId');

    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)->call('approveOpening')->assertForbidden();
    expect(DB::table('accounting_journals')->count())->toBe(0);
});

test('opening cutover readiness names missing chart and controlled supporting evidence rather than reporting zero', function () {
    $viewer = openingBooksEmployee(['accounting.view']);

    $this->actingAs($viewer)->get(route('accounting.opening-books'))
        ->assertOk()
        ->assertSee('Opening Books & Cutover')
        ->assertSee('Chart approval required')
        ->assertSee('Posting mapping approvals required')
        ->assertSee('Opening supplier schedule required')
        ->assertSee('Opening inventory valuation schedule required')
        ->assertSee('Valuation policies not approved')
        ->assertSee('Save a cutover date to determine YTD evidence requirements');
});

test('midyear cutover requires separately approved supported YTD income and expense and preserves approval identity', function () {
    $preparer = openingBooksEmployee(['accounting.view', 'accounting.maintain-opening-books']);
    $reviewer = openingBooksEmployee(['accounting.view', 'accounting.approve-opening-books']);
    $cash = approvedOpeningAccount('1000', 'cash', 'Asset', 'debit');
    $capital = approvedOpeningAccount('3000', 'capital', 'Equity', 'credit');
    $sales = approvedOpeningAccount('4000', 'sales', 'Revenue', 'credit');

    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-07-01')
        ->set('lines', [
            ['accountId' => (string) $cash->id, 'debit' => '100.00', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '100.00'],
        ])
        ->call('saveOpening')
        ->assertHasNoErrors();
    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)->call('approveOpening')->assertHasNoErrors();
    Livewire::test(OpeningBooks::class)->assertSee('Pre-cutover YTD evidence required');

    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('ytdThroughDate', '2026-06-30')
        ->set('ytdEvidence', 'Approved management income schedule')
        ->set('ytdLines', [['accountId' => (string) $sales->id, 'amount' => '250.00']])
        ->call('saveYtdSummary')
        ->assertHasNoErrors();
    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('approveYtdSummary')
        ->assertHasNoErrors()
        ->assertSee('Approved through June 30, 2026');

    expect(DB::table('accounting_ytd_summaries')->value('approved_by'))->toBe($reviewer->id)
        ->and(DB::table('accounting_ytd_summary_lines')->value('amount_cents'))->toBe(25000)
        ->and($sales->fresh()->used_at)->not->toBeNull()
        ->and(app(OpeningBooksService::class)->readiness()['ytd'])->toBe('Approved pre-cutover YTD evidence');
});

test('posted opening history is immutable, duplicate posting is rejected, and inventory openings require a schedule', function () {
    $preparer = openingBooksEmployee(['accounting.view', 'accounting.maintain-opening-books']);
    $reviewer = openingBooksEmployee(['accounting.view', 'accounting.approve-opening-books']);
    $cash = approvedOpeningAccount('1000', 'cash', 'Asset', 'debit');
    $capital = approvedOpeningAccount('3000', 'capital', 'Equity', 'credit');
    $inventory = approvedOpeningAccount('1200', 'inventory', 'Asset', 'debit');
    $payables = approvedOpeningAccount('2000', 'accounts_payable', 'Liability', 'credit');

    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-01-01')
        ->set('lines', [
            ['accountId' => (string) $cash->id, 'debit' => '100.00', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '100.00'],
        ])
        ->call('saveOpening')
        ->assertHasNoErrors();
    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)->call('approveOpening')->assertHasNoErrors();
    Livewire::test(OpeningBooks::class)->assertSee('Not required for January 1 cutover');

    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-01-01')
        ->set('lines', [
            ['accountId' => (string) $cash->id, 'debit' => '100.00', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '100.00'],
        ])
        ->call('saveOpening')
        ->assertHasErrors('opening');
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-01-01')
        ->set('lines', [
            ['accountId' => (string) $inventory->id, 'debit' => '100.00', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '100.00'],
        ])
        ->call('saveOpening')
        ->assertHasErrors('opening');
    $postedLine = AccountingJournalLine::firstOrFail();
    expect(fn () => $postedLine->forceFill(['debit_cents' => 1])->save())
        ->toThrow(DomainException::class, 'Posted journal lines are immutable.');
});
