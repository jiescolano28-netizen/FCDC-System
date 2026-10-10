<?php

use App\Livewire\Accounting\ChartOfAccounts;
use App\Models\AccountingAccount;
use App\Models\AccountingPostingMapping;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createChartOfAccountsEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'account.viewer',
        'email' => 'accounts@example.com',
        'password' => Hash::make('account-password'),
    ]), ['accounting.view']);
}

test('accounting viewers see only persisted production accounts and cannot maintain them', function () {
    $this->get(route('accounting.chart-of-accounts'))->assertRedirect(route('login'));

    $this->actingAs(createChartOfAccountsEmployee())
        ->get(route('accounting.chart-of-accounts'))
        ->assertOk()
        ->assertSee('Chart of Accounts')
        ->assertSee('No accounts match the selected filters.')
        ->assertDontSee('Cash and cash equivalents')
        ->assertDontSee('Illustrative reference data')
        ->assertDontSee('Add Account');
});

test('authorized staff create accounts which require separate approval before use', function () {
    $employee = grantEmployeeTestPermissions(Employee::create([
        'username' => 'account.maintainer',
        'email' => 'maintainer@example.com',
        'password' => Hash::make('account-password'),
    ]), ['accounting.view', 'accounting.maintain-accounts']);

    $this->actingAs($employee);

    $component = Livewire::test(ChartOfAccounts::class)
        ->set('accountCode', '6100')
        ->set('accountName', 'Cost of Goods Sold')
        ->set('description', 'Direct cost of construction materials sold')
        ->set('type', 'Expense')
        ->set('classification', 'cost_of_goods_sold')
        ->set('normalBalance', 'debit')
        ->call('saveAccount')
        ->assertHasNoErrors()
        ->assertSee('Pending approval');

    $account = AccountingAccount::where('code', '6100')->firstOrFail();
    expect($account->type)->toBe('Expense')
        ->and($account->classification)->toBe('cost_of_goods_sold')
        ->and($account->normal_balance)->toBe('debit')
        ->and($account->approved_at)->toBeNull()
        ->and($account->isApprovedForPosting())->toBeFalse();

    $reviewer = grantEmployeeTestPermissions(Employee::create([
        'username' => 'account.reviewer',
        'email' => 'reviewer@example.com',
        'password' => Hash::make('account-password'),
    ]), ['accounting.view', 'accounting.approve-accounts']);

    $this->actingAs($reviewer);
    Livewire::test(ChartOfAccounts::class)
        ->call('approveAccount', $account->id)
        ->assertHasNoErrors()
        ->assertSee('Approved');

    expect($account->fresh()->approved_at)->not->toBeNull()
        ->and($account->fresh()->isApprovedForPosting())->toBeTrue();
});

test('statement classifications follow account type and keep COGS within Expense', function () {
    $employee = grantEmployeeTestPermissions(Employee::create([
        'username' => 'classification.maintainer',
        'email' => 'classification@example.com',
        'password' => Hash::make('account-password'),
    ]), ['accounting.view', 'accounting.maintain-accounts']);
    $this->actingAs($employee);

    Livewire::test(ChartOfAccounts::class)
        ->set('type', 'Expense')
        ->assertSet('classification', 'cost_of_goods_sold')
        ->assertSee('Cost of goods sold (Expense)')
        ->assertDontSee('Cash</option>');
});

test('account search and type and status filters read persisted records', function () {
    $this->actingAs(createChartOfAccountsEmployee());
    AccountingAccount::create([
        'code' => '1200', 'name' => 'Structural steel', 'description' => 'Steel stock',
        'type' => 'Asset', 'classification' => 'inventory', 'normal_balance' => 'debit', 'is_active' => true,
    ]);
    AccountingAccount::create([
        'code' => '6200', 'name' => 'Site hauling', 'description' => 'Transport costs',
        'type' => 'Expense', 'classification' => 'operating_expense', 'normal_balance' => 'debit', 'is_active' => false,
    ]);

    Livewire::test(ChartOfAccounts::class)
        ->set('search', 'STEEL STOCK')
        ->assertSee('Structural steel')
        ->assertDontSee('Site hauling')
        ->set('search', '')
        ->set('accountType', 'Expense')
        ->set('status', 'Inactive')
        ->assertSee('Site hauling')
        ->assertDontSee('Structural steel');
});

test('deactivation preserves a used account identity and reactivation requires renewed approval', function () {
    $maintainer = grantEmployeeTestPermissions(Employee::create([
        'username' => 'account.maintainer3',
        'email' => 'maintainer3@example.com',
        'password' => Hash::make('account-password'),
    ]), ['accounting.view', 'accounting.maintain-accounts']);
    $account = AccountingAccount::create([
        'code' => '1010',
        'name' => 'Cash',
        'description' => 'Cash on hand',
        'type' => 'Asset',
        'classification' => 'cash',
        'normal_balance' => 'debit',
        'is_active' => true,
        'approved_at' => now(),
    ]);
    $account->markUsed();

    $this->actingAs($maintainer);
    Livewire::test(ChartOfAccounts::class)
        ->call('toggleActive', $account->id)
        ->assertHasNoErrors();

    expect($account->fresh()->is_active)->toBeFalse()
        ->and($account->fresh()->code)->toBe('1010')
        ->and($account->fresh()->used_at)->not->toBeNull();
    Livewire::test(ChartOfAccounts::class)
        ->call('toggleActive', $account->id)
        ->assertHasNoErrors();
    expect($account->fresh()->is_active)->toBeTrue()
        ->and($account->fresh()->approved_at)->toBeNull();
});

test('changing a posting mapping clears its approval', function () {
    $maintainer = grantEmployeeTestPermissions(Employee::create([
        'username' => 'mapping.maintainer',
        'email' => 'mapping-maintainer@example.com',
        'password' => Hash::make('account-password'),
    ]), ['accounting.view', 'accounting.maintain-accounts']);
    $first = AccountingAccount::create([
        'code' => '1010', 'name' => 'Cash', 'type' => 'Asset', 'classification' => 'cash',
        'normal_balance' => 'debit', 'is_active' => true, 'approved_at' => now(),
    ]);
    $second = AccountingAccount::create([
        'code' => '1020', 'name' => 'Secondary cash', 'type' => 'Asset', 'classification' => 'cash',
        'normal_balance' => 'debit', 'is_active' => true, 'approved_at' => now(),
    ]);

    $this->actingAs($maintainer);
    Livewire::test(ChartOfAccounts::class)
        ->set('mappingAccounts.cogs', (string) $second->id)
        ->call('saveMapping', 'cogs')
        ->assertHasErrors(['mappingAccounts.cogs']);
    expect(AccountingPostingMapping::where('source', 'cogs')->exists())->toBeFalse();
    $mapping = AccountingPostingMapping::create([
        'source' => 'cash', 'accounting_account_id' => $first->id, 'approved_at' => now(),
    ]);
    $reviewer = grantEmployeeTestPermissions(Employee::create([
        'username' => 'mapping.reviewer',
        'email' => 'mapping-reviewer@example.com',
        'password' => Hash::make('account-password'),
    ]), ['accounting.view', 'accounting.approve-accounts']);
    $this->actingAs($reviewer);
    Livewire::test(ChartOfAccounts::class)
        ->call('approveMapping', 'cash')
        ->assertHasNoErrors();
    expect($mapping->fresh()->isApprovedForPosting())->toBeTrue();

    $this->actingAs($maintainer);
    Livewire::test(ChartOfAccounts::class)
        ->set('mappingAccounts.cash', (string) $second->id)
        ->call('saveMapping', 'cash')
        ->assertHasNoErrors()
        ->assertSee('Pending approval');

    expect($mapping->fresh()->accounting_account_id)->toBe($second->id)
        ->and($mapping->fresh()->approved_at)->toBeNull();
});

test('account code and statement classification are immutable after use and used accounts cannot be deleted', function () {
    $employee = grantEmployeeTestPermissions(Employee::create([
        'username' => 'account.maintainer2',
        'email' => 'maintainer2@example.com',
        'password' => Hash::make('account-password'),
    ]), ['accounting.view', 'accounting.maintain-accounts']);
    $account = AccountingAccount::create([
        'code' => '1200',
        'name' => 'Inventory',
        'description' => 'Stock',
        'type' => 'Asset',
        'classification' => 'inventory',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);
    $account->markUsed();

    $this->actingAs($employee);
    Livewire::test(ChartOfAccounts::class)
        ->call('editAccount', $account->id)
        ->set('type', 'Expense')
        ->set('classification', 'cost_of_goods_sold')
        ->call('saveAccount')
        ->assertHasErrors(['type', 'classification']);

    Livewire::test(ChartOfAccounts::class)
        ->call('deleteAccount', $account->id)
        ->assertForbidden();

    expect($account->fresh()->type)->toBe('Asset')
        ->and($account->fresh()->classification)->toBe('inventory');
});

test('unauthorized account maintenance and approval are rejected on the server', function () {
    $viewer = createChartOfAccountsEmployee();
    $account = AccountingAccount::create([
        'code' => '1010',
        'name' => 'Cash',
        'description' => 'Cash on hand',
        'type' => 'Asset',
        'classification' => 'cash',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);

    $this->actingAs($viewer);
    Livewire::test(ChartOfAccounts::class)
        ->set('accountCode', '1020')
        ->set('accountName', 'Bank')
        ->set('type', 'Asset')
        ->set('classification', 'bank')
        ->set('normalBalance', 'debit')
        ->call('saveAccount')
        ->assertForbidden();

    Livewire::test(ChartOfAccounts::class)
        ->call('approveAccount', $account->id)
        ->assertForbidden();

    expect(AccountingAccount::count())->toBe(1)
        ->and($account->fresh()->approved_at)->toBeNull();
});

test('persisted account and mapping changes require renewed approval outside the UI', function () {
    $account = AccountingAccount::create([
        'code' => '1010',
        'name' => 'Cash',
        'description' => 'Cash on hand',
        'type' => 'Asset',
        'classification' => 'cash',
        'normal_balance' => 'debit',
        'is_active' => true,
        'approved_at' => now(),
    ]);
    $replacement = AccountingAccount::create([
        'code' => '1020',
        'name' => 'Bank',
        'type' => 'Asset',
        'classification' => 'bank',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);

    $mapping = AccountingPostingMapping::create([
        'source' => 'cash',
        'accounting_account_id' => $account->id,
        'approved_at' => now(),
    ]);

    $account->update(['name' => 'Cash on hand new']);
    expect($account->fresh()->approved_at)->toBeNull();

    $account->forceFill(['approved_at' => now()])->save();
    $mapping->update(['accounting_account_id' => $replacement->id]);

    expect($mapping->fresh()->approved_at)->toBeNull();
});

test('approved mappings cannot post through an account with a mismatched classification', function () {
    $account = AccountingAccount::create([
        'code' => '1010',
        'name' => 'Cash',
        'type' => 'Asset',
        'classification' => 'cash',
        'normal_balance' => 'debit',
        'is_active' => true,
        'approved_at' => now(),
    ]);
    $mapping = AccountingPostingMapping::create([
        'source' => 'cash',
        'accounting_account_id' => $account->id,
        'approved_at' => now(),
    ]);

    $account->update(['classification' => 'bank']);
    $account->forceFill(['approved_at' => now()])->save();

    expect($mapping->fresh()->isApprovedForPosting())->toBeFalse();
});
