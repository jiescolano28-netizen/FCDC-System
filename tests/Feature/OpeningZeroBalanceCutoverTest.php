<?php

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

function zeroCutoverEmployee(string $username, array $permissions): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => $username,
        'email' => $username.'@example.test',
        'password' => Hash::make('demo-only-password'),
    ]), $permissions);
}

function zeroCutoverAccount(string $code, string $classification, string $type, string $normalBalance, int $approverId): AccountingAccount
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

function zeroCutoverBooks(int $reviewerId): array
{
    $accounts = [
        'cash' => zeroCutoverAccount('1000', 'cash', 'Asset', 'debit', $reviewerId),
        'bank' => zeroCutoverAccount('1010', 'bank', 'Asset', 'debit', $reviewerId),
        'card_clearing' => zeroCutoverAccount('1020', 'card_clearing', 'Asset', 'debit', $reviewerId),
        'inventory' => zeroCutoverAccount('1200', 'inventory', 'Asset', 'debit', $reviewerId),
        'accounts_payable' => zeroCutoverAccount('2000', 'accounts_payable', 'Liability', 'credit', $reviewerId),
        'output_vat' => zeroCutoverAccount('2200', 'output_vat', 'Liability', 'credit', $reviewerId),
        'capital' => zeroCutoverAccount('3000', 'capital', 'Equity', 'credit', $reviewerId),
        'sales' => zeroCutoverAccount('4000', 'sales', 'Revenue', 'credit', $reviewerId),
        'cogs' => zeroCutoverAccount('5000', 'cost_of_goods_sold', 'Expense', 'debit', $reviewerId),
        'adjustment' => zeroCutoverAccount('6000', 'operating_expense', 'Expense', 'debit', $reviewerId),
    ];
    foreach ([
        'cash' => 'cash',
        'bank' => 'bank',
        'card_clearing' => 'card_clearing',
        'sales' => 'sales',
        'output_vat' => 'output_vat',
        'cogs' => 'cogs',
        'inventory' => 'inventory',
        'accounts_payable' => 'accounts_payable',
        'recovery_offset' => 'capital',
        'adjustment' => 'adjustment',
    ] as $source => $accountKey) {
        AccountingPostingMapping::create([
            'source' => $source,
            'accounting_account_id' => $accounts[$accountKey]->id,
            'approved_at' => now(),
            'approved_by' => $reviewerId,
        ]);
    }

    return $accounts;
}

test('a zero-inventory and zero-AP cutover is approved, activated and reported without inventing balances', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 09:00:00', 'Asia/Manila'));

    $preparer = zeroCutoverEmployee('demo.zero.preparer', [
        'accounting.view',
        'accounting.maintain-opening-books',
    ]);
    $reviewer = zeroCutoverEmployee('demo.zero.reviewer', [
        'accounting.view',
        'accounting.approve-opening-books',
        'accounting.activate-books',
    ]);
    $accounts = zeroCutoverBooks($reviewer->id);
    $books = app(OpeningBooksService::class);

    expect(Inventory::query()->count())->toBe(0);

    // A zero opening inventory is documented explicitly. No stock quantity or
    // value is invented: the approved schedule is empty because there are no items.
    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-01-01')
        ->set('inventoryEvidence', 'DEMO ONLY: no inventory items at cutover; zero closing count')
        ->set('inventoryLines', [])
        ->call('saveInventoryValuation')
        ->assertHasNoErrors();

    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('approveInventoryValuation')
        ->assertHasNoErrors();

    // A completely zero journal is invalid by design. The valid zero-inventory,
    // zero-AP cutover still needs positive, balanced cash and capital lines.
    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-01-01')
        ->set('lines', [
            ['accountId' => (string) $accounts['cash']->id, 'debit' => '', 'credit' => ''],
            ['accountId' => (string) $accounts['capital']->id, 'debit' => '', 'credit' => ''],
        ])
        ->call('saveOpening')
        ->assertHasErrors();

    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-01-01')
        ->set('lines', [
            ['accountId' => (string) $accounts['cash']->id, 'debit' => '100.00', 'credit' => ''],
            ['accountId' => (string) $accounts['capital']->id, 'debit' => '', 'credit' => '100.00'],
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
        ->and((int) $opening->lines()->sum('debit_cents'))->toBe(10000)
        ->and((int) $opening->lines()->sum('credit_cents'))->toBe(10000)
        ->and($opening->lines()->whereHas('account', fn ($query) => $query->where('classification', 'inventory'))->count())->toBe(0)
        ->and($opening->lines()->whereHas('account', fn ($query) => $query->where('classification', 'accounts_payable'))->count())->toBe(0)
        ->and(\App\Models\SupplierOpeningInvoice::query()->count())->toBe(0);

    $readiness = $books->readiness();
    expect($readiness)->toMatchArray([
        'production_activated' => false,
        'production_ready' => true,
        'supplier' => 'Opening supplier schedule reconciled',
        'inventory' => 'Opening stock schedule reconciled',
    ]);

    Livewire::test(OpeningBooks::class)
        ->call('activateProduction')
        ->assertHasNoErrors()
        ->assertSee('Production activated');

    $trialBalance = Livewire::test(TrialBalance::class)
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-01-31')
        ->assertSee('Books balance');

    expect($trialBalance->viewData('totalDebitsCents'))->toBe(10000)
        ->and($trialBalance->viewData('totalCreditsCents'))->toBe(10000)
        ->and($trialBalance->viewData('apScheduleCents'))->toBe(0)
        ->and($trialBalance->viewData('inventoryScheduleCents'))->toBe(0)
        ->and($trialBalance->viewData('apBookCents'))->toBe(0)
        ->and($trialBalance->viewData('inventoryBookCents'))->toBe(0)
        ->and($trialBalance->viewData('apMismatch'))->toBeFalse()
        ->and($trialBalance->viewData('inventoryMismatch'))->toBeFalse();
});

test('a zero-quantity inventory item uses an approved zero-value schedule line and stays reconciled', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 09:00:00', 'Asia/Manila'));

    $preparer = zeroCutoverEmployee('demo.zero.item.preparer', [
        'accounting.view',
        'accounting.maintain-opening-books',
    ]);
    $reviewer = zeroCutoverEmployee('demo.zero.item.reviewer', [
        'accounting.view',
        'accounting.approve-opening-books',
        'accounting.activate-books',
    ]);
    $accounts = zeroCutoverBooks($reviewer->id);
    $item = Inventory::create([
        'name' => 'DEMO Zero On-Hand Item',
        'category' => 'DEMO Materials',
        'qty' => '0.00',
        'unit' => 'piece',
        'unit_cost' => '0.00',
        'reorder_level' => '0.00',
        'description' => 'DEMO ONLY zero on-hand item; no value invented',
    ]);

    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-01-01')
        ->set('inventoryEvidence', 'DEMO ONLY: zero count sheet for zero on-hand item')
        ->set('inventoryLines', [
            ['inventoryId' => (string) $item->id, 'quantity' => '0.00', 'value' => '0.00'],
        ])
        ->call('saveInventoryValuation')
        ->assertHasNoErrors();

    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('approveInventoryValuation')
        ->assertHasNoErrors();

    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-01-01')
        ->set('lines', [
            ['accountId' => (string) $accounts['cash']->id, 'debit' => '100.00', 'credit' => ''],
            ['accountId' => (string) $accounts['capital']->id, 'debit' => '', 'credit' => '100.00'],
        ])
        ->call('saveOpening')
        ->assertHasNoErrors();

    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('approveOpening')
        ->assertHasNoErrors();

    expect($item->fresh()->qty)->toBe('0.00')
        ->and((int) $item->fresh()->carrying_value_cents)->toBe(0)
        ->and(app(OpeningBooksService::class)->readiness()['inventory'])->toBe('Opening stock schedule reconciled');

    Livewire::test(OpeningBooks::class)->call('activateProduction')->assertHasNoErrors();
    expect(app(OpeningBooksService::class)->readiness()['production_activated'])->toBeTrue();

    // A later stock movement must not retroactively invalidate the approved
    // zero-value opening schedule.
    $item->stockMovements()->create([
        'posted_by' => $preparer->id,
        'type' => 'stock_in',
        'quantity' => '1.00',
        'reason_category' => 'demo_post_cutover_receipt',
        'effective_date' => '2026-01-02',
        'posted_at' => now(),
    ]);
    expect(app(OpeningBooksService::class)->readiness()['inventory'])->toBe('Opening stock schedule reconciled');
});

test('a nonzero opening AP credit posts only when the approved supplier schedule matches exactly', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 09:00:00', 'Asia/Manila'));

    $preparer = zeroCutoverEmployee('demo.ap.preparer', [
        'accounting.view',
        'accounting.maintain-opening-books',
        'accounting.maintain-suppliers',
    ]);
    $reviewer = zeroCutoverEmployee('demo.ap.reviewer', [
        'accounting.view',
        'accounting.approve-opening-books',
        'accounting.activate-books',
    ]);
    $accounts = zeroCutoverBooks($reviewer->id);
    $supplier = Supplier::create([
        'code' => 'DEMO-ZERO-AP-01',
        'name' => 'DEMO Exact Match Supplier',
        'created_by' => $preparer->id,
    ]);

    $this->actingAs($preparer);
    app(SupplierPayablesService::class)->saveOpeningInvoice([
        'supplier_id' => $supplier->id,
        'invoice_number' => 'DEMO-OPEN-EXACT-001',
        'recognition_date' => '2025-12-15',
        'due_date' => '2026-01-31',
        'amount' => '50.00',
        'description' => 'DEMO ONLY exact opening payable',
        'terms' => 'DEMO Net 30',
    ], $preparer->id);

    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-01-01')
        ->set('inventoryEvidence', 'DEMO ONLY: no inventory at cutover')
        ->set('inventoryLines', [])
        ->call('saveInventoryValuation')
        ->assertHasNoErrors();
    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)->call('approveInventoryValuation')->assertHasNoErrors();

    // A mismatched AP credit is rejected before posting.
    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-01-01')
        ->set('lines', [
            ['accountId' => (string) $accounts['cash']->id, 'debit' => '49.99', 'credit' => ''],
            ['accountId' => (string) $accounts['accounts_payable']->id, 'debit' => '', 'credit' => '49.99'],
        ])
        ->call('saveOpening')
        ->assertHasNoErrors();
    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('approveOpening')
        ->assertHasErrors('opening');
    expect(AccountingJournal::where('source_type', 'opening')->value('status'))->toBe('draft');

    // The exact approved supplier schedule is the only acceptable credit.
    $this->actingAs($preparer);
    Livewire::test(OpeningBooks::class)
        ->set('cutoverDate', '2026-01-01')
        ->set('lines', [
            ['accountId' => (string) $accounts['cash']->id, 'debit' => '50.00', 'credit' => ''],
            ['accountId' => (string) $accounts['accounts_payable']->id, 'debit' => '', 'credit' => '50.00'],
        ])
        ->call('saveOpening')
        ->assertHasNoErrors();
    $this->actingAs($reviewer);
    Livewire::test(OpeningBooks::class)
        ->call('approveOpening')
        ->assertHasNoErrors();

    $opening = AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')->firstOrFail();
    expect($opening->status)->toBe('posted')
        ->and((int) $opening->lines()->whereHas('account', fn ($query) => $query->where('classification', 'accounts_payable'))->sum('credit_cents'))->toBe(5000)
        ->and((int) \App\Models\SupplierOpeningInvoice::activePosted()->sum('amount_cents'))->toBe(5000)
        ->and(app(OpeningBooksService::class)->readiness()['supplier'])->toBe('Opening supplier schedule reconciled');

    Livewire::test(OpeningBooks::class)->call('activateProduction')->assertHasNoErrors();
    $trialBalance = Livewire::test(TrialBalance::class)
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-01-31');
    expect($trialBalance->viewData('apScheduleCents'))->toBe(5000)
        ->and($trialBalance->viewData('apBookCents'))->toBe(5000)
        ->and($trialBalance->viewData('apMismatch'))->toBeFalse();
});
