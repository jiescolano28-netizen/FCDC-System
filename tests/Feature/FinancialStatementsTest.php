<?php

use App\Livewire\Accounting\FinancialStatements;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createFinancialStatementsEmployee(): Employee
{
    return Employee::create([
        'username' => 'statements.viewer',
        'email' => 'statements@example.com',
        'password' => Hash::make('statements-password'),
    ]);
}

test('financial statements is a separate authenticated accounting page', function () {
    $this->get(route('accounting.financial-statements'))->assertRedirect(route('login'));

    $this->actingAs(createFinancialStatementsEmployee())
        ->get(route('accounting.financial-statements'))
        ->assertOk()
        ->assertSee('Financial Statements')
        ->assertSee('Income Statement')
        ->assertSee('Balance Sheet')
        ->assertSee('Accounting')
        ->assertSee(route('accounting.financial-statements'), false)
        ->assertSee('No financial statement is available.', false)
        ->assertSee('not a real financial statement', false);
});

test('statement type and dates update the isolated non-operational document', function () {
    $employee = createFinancialStatementsEmployee();
    $this->actingAs($employee);

    session()->put('demo.journals.employee.'.$employee->id.'.history', [[
        'reference' => 'DEMO-JE-STATEMENT',
        'description' => 'Illustrative journal',
        'debitTotal' => 500,
        'creditTotal' => 500,
    ]]);
    session()->put('demo.pos.employee.'.$employee->id.'.sales', [[
        'id' => 'S-DEMO-STATEMENT',
        'total' => 250,
    ]]);

    Livewire::test(FinancialStatements::class)
        ->assertSee('Income Statement')
        ->set('statementType', 'balance-sheet')
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-01-31')
        ->assertSet('statementType', 'balance-sheet')
        ->assertSet('fromDate', '2026-01-01')
        ->assertSet('toDate', '2026-01-31')
        ->assertSee('Balance Sheet')
        ->assertSee('From: 2026-01-01')
        ->assertSee('To: 2026-01-31')
        ->assertSee('No financial statement is available.')
        ->assertSee('not a real financial statement')
        ->assertDontSee('DEMO-JE-STATEMENT')
        ->assertDontSee('S-DEMO-STATEMENT')
        ->assertDontSee('500.00')
        ->assertDontSee('250.00');
});
