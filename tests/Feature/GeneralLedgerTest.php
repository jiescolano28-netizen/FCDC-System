<?php

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function createGeneralLedgerEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'ledger.viewer',
        'email' => 'ledger@example.com',
        'password' => Hash::make('ledger-password'),
    ]), ['accounting.view']);
}

test('general ledger is an authenticated named page in accounting navigation', function () {
    $this->get(route('accounting.general-ledger'))->assertRedirect(route('login'));

    $this->actingAs(createGeneralLedgerEmployee())
        ->get(route('accounting.general-ledger'))
        ->assertOk()
        ->assertSee('General Ledger')
        ->assertSee('Accounting')
        ->assertSee('All accounts')
        ->assertSee('Cash')
        ->assertSee('Accounts Payable')
        ->assertSee('General Ledger reporting is not available yet.')
        ->assertSee('Manual journal entries can be posted and browsed in Journal Entry.');
});
