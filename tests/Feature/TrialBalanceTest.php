<?php

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function createTrialBalanceEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'trial.viewer',
        'email' => 'trial@example.com',
        'password' => Hash::make('trial-password'),
    ]), ['accounting.view']);
}

test('trial balance is an authenticated named page in accounting navigation', function () {
    $this->get(route('accounting.trial-balance'))->assertRedirect(route('login'));

    $this->actingAs(createTrialBalanceEmployee())
        ->get(route('accounting.trial-balance'))
        ->assertOk()
        ->assertSee('Trial Balance')
        ->assertSee('Accounting')
        ->assertSee('From date')
        ->assertSee('To date')
        ->assertSee('Total debits')
        ->assertSee('Total credits')
        ->assertSee('No trial balance is available.')
        ->assertSee('This is not a real trial balance and does not certify that company books are balanced.')
        ->assertSee(route('accounting.trial-balance'), false)
        ->assertSee('class="app-body trial-balance-body"', false);
});

