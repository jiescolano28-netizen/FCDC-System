<?php

use App\Livewire\Accounting\AccountingOverview;
use App\Livewire\Accounting\FinancialStatements;
use App\Livewire\Accounting\GeneralLedger;
use App\Livewire\Accounting\TrialBalance;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $readiness = Mockery::mock(\App\Services\Accounting\OpeningBooksService::class);
    $readiness->shouldReceive('readiness')->andReturn([
        'production_activated' => false,
        'production' => 'Production activation unavailable: approval and reconciliation are incomplete.',
    ]);
    app()->instance(\App\Services\Accounting\OpeningBooksService::class, $readiness);
});

test('accounting overview and reports stay unavailable until production activation', function () {
    $employee = grantEmployeeTestPermissions(Employee::create([
        'username' => 'preactivation.viewer',
        'email' => 'preactivation.viewer@example.com',
        'password' => Hash::make('secret-password'),
    ]), ['accounting.view']);
    $this->actingAs($employee);

    foreach ([AccountingOverview::class, GeneralLedger::class, TrialBalance::class, FinancialStatements::class] as $component) {
        Livewire::test($component)
            ->assertSee('Production activation unavailable')
            ->assertDontSee('No posted activity in this period');
    }
});
