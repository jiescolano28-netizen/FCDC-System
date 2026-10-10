<?php

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function createAccountingOverviewEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'accounting.viewer',
        'email' => 'accounting@example.com',
        'password' => Hash::make('accounting-password'),
    ]), ['accounting.view']);
}

test('accounting overview distinguishes available manual journals from unavailable reports', function () {
    $this->get(route('accounting.overview'))->assertRedirect(route('login'));

    $employee = createAccountingOverviewEmployee();
    $this->actingAs($employee)->get(route('accounting.overview'))
        ->assertOk()
        ->assertSee('Manual journals are available')
        ->assertSee('does not calculate verified company revenue')
        ->assertSee('Posted manual entries are available in Journal Entry')
        ->assertSee('Recent posted entries are not summarized on this page.');
});

