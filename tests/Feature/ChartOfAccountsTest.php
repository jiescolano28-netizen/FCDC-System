<?php

use App\Livewire\Accounting\ChartOfAccounts;
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

test('chart of accounts is an authenticated named page in the accounting navigation', function () {
    $this->get(route('accounting.chart-of-accounts'))->assertRedirect(route('login'));

    $this->actingAs(createChartOfAccountsEmployee())
        ->get(route('accounting.chart-of-accounts'))
        ->assertOk()
        ->assertSee('Chart of Accounts')
        ->assertSee('Cash and cash equivalents')
        ->assertSee('Illustrative reference data')
        ->assertSee(route('accounting.chart-of-accounts'), false)
        ->assertSee('Accounting')
        ->assertSee('Add Account')
        ->assertSee('Edit', false)
        ->assertSee('<button class="chart-account-action" type="button" disabled', false)
        ->assertSee('<button class="chart-account-edit" type="button" disabled', false)
        ->assertDontSee('backend integration')
        ->assertDontSee('alert(');
});

test('chart account search matches codes and names without case sensitivity', function () {
    $this->actingAs(createChartOfAccountsEmployee());

    Livewire::test(ChartOfAccounts::class)
        ->set('search', '1100')
        ->assertSee('Accounts Receivable')
        ->assertDontSee('Cash and cash equivalents')
        ->set('search', 'sales revenue')
        ->assertSee('Sales Revenue')
        ->assertDontSee('Accounts Receivable');
});

test('chart account type and status filters combine with search and show empty results', function () {
    $this->actingAs(createChartOfAccountsEmployee());

    Livewire::test(ChartOfAccounts::class)
        ->set('search', '1010')
        ->set('accountType', 'Asset')
        ->set('status', 'Active')
        ->assertSee('Cash')
        ->assertDontSee('Sales Revenue')
        ->set('accountType', 'Expense')
        ->assertSee('No sample accounts match the selected filters.')
        ->assertDontSee('Cash and cash equivalents')
        ->set('accountType', 'All')
        ->set('status', 'Inactive')
        ->assertSee('No sample accounts match the selected filters.');
});
