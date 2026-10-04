<?php

use App\Livewire\Accounting\TrialBalance;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createTrialBalanceEmployee(): Employee
{
    return Employee::create([
        'username' => 'trial.viewer',
        'email' => 'trial@example.com',
        'password' => Hash::make('trial-password'),
    ]);
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
        ->assertSee(route('accounting.trial-balance'), false);
});

test('period selections update the printable empty report without calculating demo journals', function () {
    $employee = createTrialBalanceEmployee();
    $this->actingAs($employee);

    session()->put('demo.journals.employee.'.$employee->id.'.history', [[
        'reference' => 'DEMO-JE-TRIAL',
        'description' => 'Illustrative journal',
        'debitTotal' => 500,
        'creditTotal' => 500,
    ]]);

    Livewire::test(TrialBalance::class)
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-01-31')
        ->assertSet('fromDate', '2026-01-01')
        ->assertSet('toDate', '2026-01-31')
        ->assertSee('From: 2026-01-01')
        ->assertSee('To: 2026-01-31')
        ->assertSee('Total debits unavailable')
        ->assertSee('Total credits unavailable')
        ->assertSee('No trial balance is available.')
        ->assertSee('This is not a real trial balance and does not certify that company books are balanced.')
        ->assertDontSee('DEMO-JE-TRIAL')
        ->assertDontSee('500.00');
});
