<?php

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function createAccountingOverviewEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'accounting.viewer',
        'email' => 'accounting@example.com',
        'password' => Hash::make('accounting-password'),
    ]), ['accounting.view']);
}

test('accounting overview is an authenticated named page with non-operational financial states', function () {
    $this->get(route('accounting.overview'))->assertRedirect(route('login'));

    $employee = createAccountingOverviewEmployee();

    $response = $this->actingAs($employee)->get(route('accounting.overview'));
    $response->assertOk()
        ->assertSee('Accounting Overview')
        ->assertSee('Accounting')
        ->assertSee('href="'.route('accounting.overview').'"', false)
        ->assertSee('aria-current="page"', false)
        ->assertSee('Real accounting balances and reports are not implemented', false)
        ->assertSee('No posted accounting activity', false)
        ->assertSee('Journal Entry')
        ->assertSee('General Ledger')
        ->assertSee('Trial Balance')
        ->assertSee('Financial Statements');

    foreach ([
        'accounting.journal-entry' => '/accounting/journal-entry',
        'accounting.general-ledger' => '/accounting/general-ledger',
        'accounting.trial-balance' => '/accounting/trial-balance',
        'accounting.financial-statements' => '/accounting/financial-statements',
    ] as $routeName => $path) {
        if (Route::has($routeName)) {
            $response->assertSee('href="'.route($routeName).'"', false);
        } else {
            $response->assertDontSee('href="'.$path.'"', false);
        }
    }
});

test('session demo journals do not appear as accounting balances or postings', function () {
    $employee = createAccountingOverviewEmployee();
    $this->actingAs($employee);

    session()->put('demo.journals.employee.'.$employee->id.'.history', [[
        'reference' => 'DEMO-JE-001',
        'description' => 'Illustrative journal',
        'debitTotal' => 500,
        'creditTotal' => 500,
    ]]);

    $this->get(route('accounting.overview'))
        ->assertOk()
        ->assertSee('Real accounting balances and reports are not implemented', false)
        ->assertSee('No posted accounting activity', false)
        ->assertDontSee('DEMO-JE-001')
        ->assertDontSee('500.00');
});
