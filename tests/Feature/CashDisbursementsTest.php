<?php

use App\Livewire\Accounting\CashDisbursements;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createCashDisbursementsEmployee(): Employee
{
    return Employee::create([
        'username' => 'cash.viewer',
        'email' => 'cash@example.com',
        'password' => Hash::make('cash-password'),
    ]);
}

test('cash disbursements is an authenticated accounting page with explicitly unavailable payment information and actions', function () {
    $this->get(route('accounting.cash-disbursements'))->assertRedirect(route('login'));

    $this->actingAs(createCashDisbursementsEmployee())
        ->get(route('accounting.cash-disbursements'))
        ->assertOk()
        ->assertSee('Cash Disbursements')
        ->assertSee('href="'.route('accounting.cash-disbursements').'"', false)
        ->assertSee('Accounting')
        ->assertSee('No disbursement records are available', false)
        ->assertSee('Payment totals unavailable', false)
        ->assertSee('Payment count unavailable', false)
        ->assertSee('Add Disbursement')
        ->assertSee('aria-describedby="cash-disbursement-maintenance-note"', false)
        ->assertSee('Disbursement creation, editing, and payment processing are unavailable', false)
        ->assertSee('disabled aria-describedby="cash-disbursement-maintenance-note"', false)
        ->assertDontSee('alert(');
});

test('reference and payee search with payment method filters keeps the empty demonstration dataset', function () {
    $this->actingAs(createCashDisbursementsEmployee());

    Livewire::test(CashDisbursements::class)
        ->assertSee('No disbursement records are available')
        ->set('search', 'REF-1001')
        ->assertSet('search', 'REF-1001')
        ->assertSee('No disbursement records are available')
        ->set('search', 'Northwind')
        ->assertSet('search', 'Northwind')
        ->set('method', 'Cash')
        ->assertSet('method', 'Cash')
        ->assertSee('No disbursement records are available')
        ->set('method', 'Check')
        ->assertSet('method', 'Check')
        ->set('method', 'Bank Transfer')
        ->assertSet('method', 'Bank Transfer')
        ->set('method', 'All')
        ->assertSet('method', 'All')
        ->assertSee('No disbursement records are available');
});
