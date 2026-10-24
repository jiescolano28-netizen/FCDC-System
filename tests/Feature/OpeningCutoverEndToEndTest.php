<?php

use App\Livewire\Accounting\AccountsPayable;
use App\Livewire\Accounting\FinancialStatements;
use App\Livewire\Accounting\GeneralLedger;
use App\Livewire\Accounting\JournalEntry;
use App\Livewire\Accounting\OpeningBooks;
use App\Livewire\Accounting\TrialBalance;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingMapping;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\Supplier;
use App\Services\Accounting\OpeningBooksService;
use App\Services\Accounting\SupplierPayablesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function cutoverEndToEndEmployee(string $username, array $permissions): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => $username,
        'email' => $username.'@example.test',
        'password' => Hash::make('demo-only-password'),
    ]), $permissions);
}

function cutoverEndToEndAccount(string $code, string $classification, string $type, string $normalBalance, int $approverId): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => 'DEMO '.$code.' '.ucwords(str_replace('_', ' ', $classification)),
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => $normalBalance,
        'is_active' => true,
        'approved_at' => now(),
        'approved_by' => $approverId,
    ]);
}

function cutoverEndToEndMapping(string $source, AccountingAccount $account, int $approverId): void
{
    AccountingPostingMapping::create([
        'source' => $source,
        'accounting_account_id' => $account->id,
        'approved_at' => now(),
        'approved_by' => $approverId,
    ]);
}

test('a labelled illustrative cutover with reconciled opening schedules activates the reports and records later postings once', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 09:00:00', 'Asia/Manila'));

    $preparer = cutoverEndToEndEmployee('demo.cutover.preparer', [
        'accounting.view',
        'accounting.maintain-opening-books',
        'accounting.maintain-suppliers',
        'accounting.create-journal-entry',
    ]);
    $reviewer = cutoverEndToEndEmployee('demo.cutover.reviewer', [
        'accounting.view',
        'accounting.approve-accounts',
        'accounting.approve-opening-books',
        'accounting.approve-journal-entry',
        'accounting.activate-books',
    ]);
    $poster = cutoverEndToEndEmployee('demo.cutover.poster', [
        'accounting.view',
        'accounting.post-journal-entry',
    ]);
    $viewer = cutoverEndToEndEmployee('demo.cutover.viewer', ['accounting.view']);

    $accounts = [
        'cash' => cutoverEndToEndAccount('1000', 'cash', 'Asset', 'debit', $reviewer->id),
        'bank' => cutoverEndToEndAccount('1010', 'bank', 'Asset', 'debit', $reviewer->id),
        'card_clearing' => cutoverEndToEndAccount('1020', 'card_clearing', 'Asset', 'debit', $reviewer->id),
        'inventory' => cutoverEndToEndAccount('1200', 'inventory', 'Asset', 'debit', $reviewer->id),
        'ap' => cutoverEndToEndAccount('2000', 'accounts_payable', 'Liability', 'credit', $reviewer->id),
        'output_vat' => cutoverEndToEndAccount('2200', 'output_vat', 'Liability', 'credit', $reviewer->id),
        'capital' => cutoverEndToEndAccount('3000', 'capital', 'Equity', 'credit', $reviewer->id),
        'opening_equity' => cutoverEndToEndAccount('3200', 'other_equity', 'Equity', 'credit', $reviewer->id),
        'sales' => cutoverEndToEndAccount('4000', 'sales', 'Revenue', 'credit', $reviewer->id),
        'cogs' => cutoverEndToEndAccount('5000', 'cost_of_goods_sold', 'Expense', 'debit', $reviewer->id),
        'operating_expense' => cutoverEndToEndAccount('6000', 'operating_expense', 'Expense', 'debit', $reviewer->id),
    ];

    foreach ([
        'cash' => 'cash',
        'bank' => 'bank',
        'card_clearing' => 'card_clearing',
        'sales' => 'sales',
        'output_vat' => 'output_vat',
        'cogs' => 'cogs',
        'inventory' => 'inventory',
        'accounts_payable' => 'ap',
        'recovery_offset' => 'opening_equity',
        'adjustment' => 'opening_equity',
    ] as $source => $accountKey) {
        cutoverEndToEndMapping($source, $accounts[$accountKey], $reviewer->id);
    }

    $this->actingAs($preparer);

    $steel = Inventory::create([
        'name' => 'DEMO Opening Steel Bars',
        'category' => 'DEMO Materials',
        'qty' => '10.00',
        'unit' => 'piece',
        'unit_cost' => '3000.00',
        'reorder_level' => '0.00',
        'description' => 'DEMO ONLY synthetic cutover stock',
    ]);
    $timber = Inventory::create([
        'name' => 'DEMO Opening Timber Boards',
        'category' => 'DEMO Materials',
        'qty' => '5.00',
        'unit' => 'piece',
        'unit_cost' => '1000.00',
        'reorder_level' => '0.00',
        'description' => 'DEMO ONLY synthetic cutover stock',
    ]);

    Livewire::test(AccountsPayable::class)
        ->set('supplierCode', 'DEMO-AP-01')
        ->set('supplierName', 'DEMO Northwind Materials')
        ->set('supplierAddress', 'DEMO ONLY, Sample City')
        ->call('saveSupplier')
        ->assertHasNoErrors();
    Livewire::test(AccountsPayable::class)
        ->set('supplierCode', 'DEMO-AP-02')
        ->set('supplierName', 'DEMO Southwind Supplies')
        ->set('supplierAddress', 'DEMO ONLY, Sample City')
        ->call('saveSupplier')
        ->assertHasNoErrors();

    $supplierA = Supplier::where('code', 'DEMO-AP-01')->firstOrFail();
    $supplierB = Supplier::where('code', 'DEMO-AP-02')->firstOrFail();

    Livewire::test(AccountsPayable::class)
        ->set('supplierId', $supplierA->id)
        ->set('invoiceNumber', 'DEMO-OPEN-001')
        ->set('recognitionDate', '2025-12-15')
        ->set('dueDate', '2026-01-31')
        ->set('amount', '12000.00')
        ->set('description', 'DEMO ONLY synthetic opening payable A')
        ->set('terms', 'DEMO Net 30')
        ->call('saveOpeningInvoice')
        ->assertHasNoErrors()
        ->assertSee('Draft');
    Livewire::test(AccountsPayable::class)
        ->set('supplierId', $supplierB->id)
        ->set('invoiceNumber', 'DEMO-OPEN-002')
        ->set('recognitionDate', '2025-12-20')
        ->set('dueDate', '2026-01-31')
        ->set('amount', '8000.00')
        ->set('description', 'DEMO ONLY synthetic opening payable B')
        ->set('terms', 'DEMO Net 30')
        ->call('saveOpeningInvoice')
        ->assertHasNoErrors()
        ->assertSee('Draft');

    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-01-01')
        ->set('inventoryEvidence', 'DEMO ONLY: synthetic cutover count and valuation')
        ->set('inventoryLines', [
            ['inventoryId' => (string) $steel->id, 'quantity' => '10.00', 'value' => '30000.00'],
            ['inventoryId' => (string) $timber->id, 'quantity' => '5.00', 'value' => '5000.00'],
        ])
        ->call('saveInventoryValuation')
        ->assertHasNoErrors();

    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('approveInventoryValuation')
        ->assertHasNoErrors()
        ->assertSee('Opening valuation approved');

    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-01-01')
        ->set('lines', [
            ['accountId' => (string) $accounts['cash']->id, 'debit' => '100000.00', 'credit' => ''],
            ['accountId' => (string) $accounts['inventory']->id, 'debit' => '35000.00', 'credit' => ''],
            ['accountId' => (string) $accounts['ap']->id, 'debit' => '', 'credit' => '20000.00'],
            ['accountId' => (string) $accounts['capital']->id, 'debit' => '', 'credit' => '115000.00'],
        ])
        ->call('saveOpening')
        ->assertHasNoErrors();

    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('approveOpening')
        ->assertHasNoErrors()
        ->assertSee('Opening supplier schedule reconciled')
        ->assertSee('Opening stock schedule reconciled');

    $opening = AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')->firstOrFail();
    expect($opening->status)->toBe('posted')
        ->and((int) $opening->lines()->sum('debit_cents'))->toBe(13500000)
        ->and((int) $opening->lines()->sum('credit_cents'))->toBe(13500000);

    $readiness = app(OpeningBooksService::class)->readiness();
    expect($readiness['production_ready'])->toBeTrue()
        ->and($readiness['production_activated'])->toBeFalse()
        ->and($readiness['opening'])->toBe('Balanced opening journal approved')
        ->and($readiness['supplier'])->toBe('Opening supplier schedule reconciled')
        ->and($readiness['inventory'])->toBe('Opening stock schedule reconciled');

    // The General Ledger and Financial Statements read the posted opening immediately.
    // The Trial Balance is deliberately hard-gated on production activation.
    $this->actingAs($viewer);
    $ledgerBeforeActivation = Livewire::test(GeneralLedger::class)
        ->set('accountId', (string) $accounts['cash']->id)
        ->set('cutoffDate', '2026-01-01')
        ->set('toDate', '2026-01-31')
        ->assertSee('Opening position')
        ->assertSee('Pre-activation preview.')
        ->assertSee('100,000.00');
    $openingCashRow = $ledgerBeforeActivation->viewData('allAccountRows')
        ->first(fn (array $row) => $row['account']->id === $accounts['cash']->id);
    expect($openingCashRow['opening'])->toBe(10000000)
        ->and($openingCashRow['closing'])->toBe(10000000);

    $trialBalanceBeforeActivation = Livewire::test(TrialBalance::class)
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-01-31')
        ->assertSee('Trial Balance unavailable');
    expect($trialBalanceBeforeActivation->viewData('dateError'))->toBe('Production ready for authorized activation');

    $balanceSheetBeforeActivation = Livewire::test(FinancialStatements::class)
        ->set('statementType', 'balance-sheet')
        ->set('toDate', '2026-01-31')
        ->assertSee('Pre-activation preview.');
    expect($balanceSheetBeforeActivation->viewData('report')['available'])->toBeTrue();

    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('activateProduction')
        ->assertHasNoErrors()
        ->assertSee('Production activated');
    expect(app(OpeningBooksService::class)->readiness()['production_activated'])->toBeTrue();

    // The pre-activation label is removed once an authorized reviewer activates production.
    Livewire::test(FinancialStatements::class)
        ->set('statementType', 'balance-sheet')
        ->set('toDate', '2026-01-31')
        ->assertDontSee('Pre-activation preview.');

    Carbon::setTestNow(Carbon::parse('2026-01-31 12:00:00', 'Asia/Manila'));

    $this->actingAs($preparer);
    Livewire::test(JournalEntry::class)
        ->set('accountingDate', '2026-01-15')
        ->set('reference', 'DEMO-CUT-SALE-001')
        ->set('externalReference', 'DEMO ONLY: synthetic cash sale')
        ->set('description', 'DEMO synthetic cash sale')
        ->set('lines', [
            ['accountId' => (string) $accounts['cash']->id, 'description' => 'Cash received', 'debit' => '11200.00', 'credit' => ''],
            ['accountId' => (string) $accounts['sales']->id, 'description' => 'Sales', 'debit' => '', 'credit' => '10000.00'],
            ['accountId' => (string) $accounts['output_vat']->id, 'description' => 'Output VAT', 'debit' => '', 'credit' => '1200.00'],
        ])
        ->call('saveDraft')
        ->assertHasNoErrors();
    $sale = AccountingJournal::where('reference', 'DEMO-CUT-SALE-001')->firstOrFail();

    // A saved draft must not enter posted balances.
    $trialBalanceWithDraft = Livewire::test(TrialBalance::class)
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-01-31');
    expect($trialBalanceWithDraft->viewData('totalDebitsCents'))->toBe(13500000)
        ->and($trialBalanceWithDraft->viewData('totalCreditsCents'))->toBe(13500000);

    Livewire::test(JournalEntry::class)
        ->call('submitForReview', $sale->id)
        ->assertHasNoErrors();
    $this->actingAs($reviewer);
    Livewire::test(JournalEntry::class)
        ->call('approveDraft', $sale->id)
        ->assertHasNoErrors();
    $this->actingAs($poster);
    Livewire::test(JournalEntry::class)
        ->call('postDraft', $sale->id)
        ->assertHasNoErrors();
    expect($sale->fresh()->status)->toBe('posted');

    $this->actingAs($preparer);
    Livewire::test(JournalEntry::class)
        ->set('accountingDate', '2026-01-20')
        ->set('reference', 'DEMO-CUT-EXP-001')
        ->set('externalReference', 'DEMO ONLY: synthetic operating expense')
        ->set('description', 'DEMO synthetic operating expense')
        ->set('lines', [
            ['accountId' => (string) $accounts['operating_expense']->id, 'description' => 'Utilities', 'debit' => '3000.00', 'credit' => ''],
            ['accountId' => (string) $accounts['cash']->id, 'description' => 'Cash paid', 'debit' => '', 'credit' => '3000.00'],
        ])
        ->call('saveDraft')
        ->assertHasNoErrors();
    $expense = AccountingJournal::where('reference', 'DEMO-CUT-EXP-001')->firstOrFail();
    Livewire::test(JournalEntry::class)
        ->call('submitForReview', $expense->id)
        ->assertHasNoErrors();
    $this->actingAs($reviewer);
    Livewire::test(JournalEntry::class)
        ->call('approveDraft', $expense->id)
        ->assertHasNoErrors();
    $this->actingAs($poster);
    Livewire::test(JournalEntry::class)
        ->call('postDraft', $expense->id)
        ->assertHasNoErrors();
    expect($expense->fresh()->status)->toBe('posted');

    $this->actingAs($viewer);

    $ledger = Livewire::test(GeneralLedger::class)
        ->set('accountId', (string) $accounts['cash']->id)
        ->set('cutoffDate', '2026-01-01')
        ->set('toDate', '2026-01-31')
        ->assertSee('DEMO-CUT-SALE-001')
        ->assertSee('DEMO-CUT-EXP-001')
        ->assertSee('108,200.00');
    $cashRow = $ledger->viewData('allAccountRows')
        ->first(fn (array $row) => $row['account']->id === $accounts['cash']->id);
    expect($cashRow['opening'])->toBe(10000000)
        ->and($cashRow['total_debit_cents'])->toBe(1120000)
        ->and($cashRow['total_credit_cents'])->toBe(300000)
        ->and($cashRow['closing'])->toBe(10820000)
        ->and($cashRow['lines'])->toHaveCount(2);

    $trialBalance = Livewire::test(TrialBalance::class)
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-01-31')
        ->assertSee('Books balance')
        ->assertSee('Accounts Payable: book 20,000.00; supplier and invoice schedule 20,000.00.')
        ->assertSee('Inventory: book 35,000.00; valued-item schedule 35,000.00.')
        ->assertSee('Reconciled.')
        ->assertSee('146,200.00');

    $trialBalanceRows = $trialBalance->viewData('accounts')->keyBy(fn (array $row) => $row['account']->code);
    expect($trialBalance->viewData('totalDebitsCents'))->toBe(14620000)
        ->and($trialBalance->viewData('totalCreditsCents'))->toBe(14620000)
        ->and($trialBalance->viewData('apScheduleCents'))->toBe(2000000)
        ->and($trialBalance->viewData('inventoryScheduleCents'))->toBe(3500000)
        ->and($trialBalance->viewData('apMismatch'))->toBeFalse()
        ->and($trialBalance->viewData('inventoryMismatch'))->toBeFalse()
        ->and($trialBalanceRows['1000']['closing_debit_cents'])->toBe(10820000)
        ->and($trialBalanceRows['1200']['closing_debit_cents'])->toBe(3500000)
        ->and($trialBalanceRows['2000']['closing_credit_cents'])->toBe(2000000)
        ->and($trialBalanceRows['3000']['closing_credit_cents'])->toBe(11500000)
        ->and($trialBalanceRows['4000']['closing_credit_cents'])->toBe(1000000)
        ->and($trialBalanceRows['2200']['closing_credit_cents'])->toBe(120000)
        ->and($trialBalanceRows['6000']['closing_debit_cents'])->toBe(300000);

    // Post-cutover sales must not retroactively invalidate the approved opening
    // stock schedule or flip production readiness back to blocked.
    expect(app(OpeningBooksService::class)->readiness())
        ->toMatchArray([
            'production_activated' => true,
            'production_ready' => true,
            'inventory' => 'Opening stock schedule reconciled',
        ]);

    Livewire::test(TrialBalance::class)
        ->set('fromDate', '2026-02-01')
        ->set('toDate', '2026-02-28')
        ->assertSee('No activity during this period')
        ->assertSee('108,200.00')
        ->assertSee('35,000.00');

    $incomeStatement = Livewire::test(FinancialStatements::class)
        ->set('statementType', 'income-statement')
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-01-31')
        ->assertSee('PHP 10,000.00')
        ->assertSee('PHP 3,000.00')
        ->assertSee('PHP 7,000.00');
    $income = $incomeStatement->viewData('report');
    expect($income['sales'])->toBe(1000000)
        ->and($income['cogs'])->toBe(0)
        ->and($income['grossProfit'])->toBe(1000000)
        ->and($income['operating'])->toBe(300000)
        ->and($income['netIncome'])->toBe(700000);

    $balanceSheet = Livewire::test(FinancialStatements::class)
        ->set('statementType', 'balance-sheet')
        ->set('toDate', '2026-01-31')
        ->assertSee('Accounting equation balances: Assets = Liabilities + Equity.');
    $position = $balanceSheet->viewData('report');
    expect($position['assets'])->toBe(14320000)
        ->and($position['liabilities'])->toBe(2120000)
        ->and($position['equity'])->toBe(12200000)
        ->and($position['liabilitiesAndEquity'])->toBe(14320000)
        ->and($position['balanced'])->toBeTrue();
});

test('the opening supplier and stock schedules are actual gates before activation', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 09:00:00', 'Asia/Manila'));

    $preparer = cutoverEndToEndEmployee('demo.gate.preparer', [
        'accounting.view',
        'accounting.maintain-opening-books',
        'accounting.maintain-suppliers',
    ]);
    $reviewer = cutoverEndToEndEmployee('demo.gate.reviewer', [
        'accounting.view',
        'accounting.approve-opening-books',
        'accounting.activate-books',
    ]);

    $cash = cutoverEndToEndAccount('1000', 'cash', 'Asset', 'debit', $reviewer->id);
    $ap = cutoverEndToEndAccount('2000', 'accounts_payable', 'Liability', 'credit', $reviewer->id);
    $supplier = Supplier::create([
        'code' => 'DEMO-GATE-01',
        'name' => 'DEMO Gate Supplier',
        'created_by' => $preparer->id,
    ]);

    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-01-01')
        ->set('lines', [
            ['accountId' => (string) $cash->id, 'debit' => '100.00', 'credit' => ''],
            ['accountId' => (string) $ap->id, 'debit' => '', 'credit' => '100.00'],
        ])
        ->call('saveOpening')
        ->assertHasNoErrors();

    // Without a matching supplier schedule the opening journal cannot post.
    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('approveOpening')
        ->assertHasErrors('opening');
    expect(AccountingJournal::where('source_type', 'opening')->value('status'))->toBe('draft')
        ->and(AccountingJournal::where('source_type', 'opening')->count())->toBe(1);

    $this->actingAs($preparer);
    app(SupplierPayablesService::class)->saveOpeningInvoice([
        'supplier_id' => $supplier->id,
        'invoice_number' => 'DEMO-GATE-INV-001',
        'recognition_date' => '2025-12-15',
        'due_date' => '2026-01-31',
        'amount' => '100.00',
        'description' => 'DEMO ONLY gate test opening payable',
        'terms' => 'DEMO Net 30',
    ], $preparer->id);

    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('approveOpening')
        ->assertHasNoErrors();

    // Supplier schedule now reconciles, but activation still requires an approved
    // per-item opening inventory valuation schedule (even when no stock exists).
    $readiness = app(OpeningBooksService::class)->readiness();
    expect($readiness['supplier'])->toBe('Opening supplier schedule reconciled')
        ->and($readiness['inventory'])->toBe('Opening inventory valuation schedule required')
        ->and($readiness['production_ready'])->toBeFalse()
        ->and($readiness['production_blockers'])->toContain('opening inventory must match the approved item schedule');

    Livewire::test(OpeningBooks::class)
        ->call('activateProduction')
        ->assertHasErrors('activation');
    expect(DB::table('accounting_production_activations')->count())->toBe(0);
});
