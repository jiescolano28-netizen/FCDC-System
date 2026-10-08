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

test('accounts payable is a separate authenticated accounting page with unavailable financial figures and actions', function () {
    $this->get(route('accounting.accounts-payable'))->assertRedirect(route('login'));

    $this->actingAs(createAccountsPayableEmployee())
        ->get(route('accounting.accounts-payable'))
        ->assertOk()
        ->assertSee('Accounts Payable')
        ->assertSee('href="'.route('accounting.accounts-payable').'"', false)
        ->assertSee('Accounting')
        ->assertSee('No verified payable balances or obligations are available', false)
        ->assertSee('Amount unavailable', false)
        ->assertSee('Open invoices unavailable', false)
        ->assertSee('No supplier invoices are available in this demonstration', false)
        ->assertSee('Search supplier or invoice')
        ->assertSee('wire:model.live.debounce.250ms="search"', false)
        ->assertSee('wire:model.live="status"', false)
        ->assertSee('<option value="Unpaid">Unpaid</option>', false)
        ->assertSee('<option value="Partially Paid">Partially Paid</option>', false)
        ->assertSee('<option value="Paid">Paid</option>', false)
        ->assertSee('Add Payable')
        ->assertSee('aria-describedby="payable-maintenance-note"', false)
        ->assertSee('disabled aria-describedby="payable-maintenance-note"', false)
        ->assertSee('Payable creation, editing, and payment actions are unavailable', false)
        ->assertDontSee('backend integration')
        ->assertDontSee('alert(');
});

test('supplier and invoice search with payable status filters keeps the empty demonstration dataset', function () {
    $this->actingAs(createAccountsPayableEmployee());

    Livewire::test(AccountsPayable::class)
        ->assertSee('No supplier invoices are available in this demonstration')
        ->set('search', 'Northwind')
        ->assertSet('search', 'Northwind')
        ->assertSee('No supplier invoices are available in this demonstration')
        ->set('search', 'INV-1001')
        ->assertSet('search', 'INV-1001')
        ->assertSee('No supplier invoices are available in this demonstration')
        ->set('status', 'Unpaid')
        ->assertSet('status', 'Unpaid')
        ->assertSee('No supplier invoices are available in this demonstration')
        ->set('status', 'Partially Paid')
        ->assertSet('status', 'Partially Paid')
        ->set('status', 'Paid')
        ->assertSet('status', 'Paid')
        ->set('status', 'All')
        ->assertSet('status', 'All')
        ->assertSee('No supplier invoices are available in this demonstration');
});
