<?php

use App\Livewire\Accounting\AccountsPayable;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createAccountsPayableEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'payables.viewer',
        'email' => 'payables@example.com',
        'password' => Hash::make('payables-password'),
    ]), ['accounting.view']);
}

test('accounts payable exposes opening supplier schedules without presenting drafts as posted balances', function () {
    $this->get(route('accounting.accounts-payable'))->assertRedirect(route('login'));

    $this->actingAs(createAccountsPayableEmployee())
        ->get(route('accounting.accounts-payable'))
        ->assertOk()
        ->assertSee('Accounts Payable')
        ->assertSee('href="'.route('accounting.accounts-payable').'"', false)
        ->assertSee('Posted opening outstanding')
        ->assertSee('Posted opening overdue')
        ->assertSee('Supplier code')
        ->assertSee('Prepare opening unpaid invoice')
        ->assertSee('Search supplier or invoice')
        ->assertSee('wire:model.live.debounce.250ms="search"', false)
        ->assertSee('<option value="Draft">Draft</option>', false)
        ->assertSee('<option value="Overdue">Overdue</option>', false)
        ->assertSee('Drafts remain outside the books')
        ->assertDontSee('backend integration')
        ->assertDontSee('alert(');
});

test('supplier and invoice search with status filters handles an empty schedule', function () {
    $this->actingAs(createAccountsPayableEmployee());

    Livewire::test(AccountsPayable::class)
        ->assertSee('No supplier invoices match this search or state.')
        ->set('search', 'Northwind')
        ->assertSet('search', 'Northwind')
        ->assertSee('No supplier invoices match this search or state.')
        ->set('search', 'INV-1001')
        ->assertSet('search', 'INV-1001')
        ->assertSee('No supplier invoices match this search or state.')
        ->set('status', 'Draft')
        ->assertSet('status', 'Draft')
        ->set('status', 'Unpaid')
        ->assertSet('status', 'Unpaid')
        ->set('status', 'Overdue')
        ->assertSet('status', 'Overdue')
        ->set('status', 'All')
        ->assertSet('status', 'All')
        ->assertSee('No supplier invoices match this search or state.');
});
