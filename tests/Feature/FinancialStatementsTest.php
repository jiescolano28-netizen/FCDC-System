<?php

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function createFinancialStatementsEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'statements.viewer',
        'email' => 'statements@example.com',
        'password' => Hash::make('statements-password'),
    ]), ['accounting.view']);
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

