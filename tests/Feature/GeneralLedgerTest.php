<?php

use App\Livewire\Accounting\GeneralLedger;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createGeneralLedgerEmployee(): Employee
{
    return Employee::create([
        'username' => 'ledger.viewer',
        'email' => 'ledger@example.com',
        'password' => Hash::make('ledger-password'),
    ]);
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
        ->assertSee('No ledger entries are available.')
        ->assertSee('Real ledger calculation and reporting are not implemented.')
        ->assertSee(route('accounting.general-ledger'), false);
});

test('account and date selections remain usable without creating ledger activity', function () {
    $employee = createGeneralLedgerEmployee();
    $this->actingAs($employee);

    session()->put('demo.journals.employee.'.$employee->id.'.history', [[
        'reference' => 'DEMO-JE-LEDGER',
        'description' => 'Illustrative journal',
        'debitTotal' => 500,
        'creditTotal' => 500,
    ]]);
    session()->put('demo.pos.employee.'.$employee->id.'.sales', [[
        'id' => 'S-DEMO-LEDGER',
        'total' => 250,
    ]]);

    Livewire::test(GeneralLedger::class)
        ->assertSee('All accounts')
        ->assertSee('Cash')
        ->set('accountCode', '2010')
        ->assertSet('accountCode', '2010')
        ->set('fromDate', '2026-01-01')
        ->assertSet('fromDate', '2026-01-01')
        ->set('toDate', '2026-01-31')
        ->assertSet('toDate', '2026-01-31')
        ->assertSee('No ledger entries are available.')
        ->assertSee('Real ledger calculation and reporting are not implemented.')
        ->assertDontSee('Running balance')
        ->assertDontSee('DEMO-JE-LEDGER')
        ->assertDontSee('S-DEMO-LEDGER')
        ->assertDontSee('500.00')
        ->assertDontSee('250.00');
});
