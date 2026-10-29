<?php

use App\Livewire\Accounting\GeneralLedger;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Services\Accounting\OpeningBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $readiness = Mockery::mock(OpeningBooksService::class);
    $readiness->shouldReceive('readiness')->andReturn([
        'production_activated' => true,
        'production' => 'Production activated',
    ]);
    app()->instance(OpeningBooksService::class, $readiness);
});

function incompleteOpeningLedgerEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'ledger.incomplete.viewer',
        'email' => 'ledger.incomplete@example.com',
        'password' => Hash::make('ledger-password'),
    ]), ['accounting.view']);
}

function incompleteOpeningLedgerAccount(string $code, string $normalBalance): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => "Incomplete opening account {$code}",
        'type' => $normalBalance === 'debit' ? 'Asset' : 'Equity',
        'classification' => $normalBalance === 'debit' ? 'cash' : 'capital',
        'normal_balance' => $normalBalance,
        'is_active' => true,
        'approved_at' => now(),
    ]);
}

test('posted transactions remain visible when opening balances are not approved', function () {
    $viewer = incompleteOpeningLedgerEmployee();
    $cash = incompleteOpeningLedgerAccount('1000', 'debit');
    $equity = incompleteOpeningLedgerAccount('3000', 'credit');
    $period = AccountingPostingPeriod::firstOrCreateForDate('2026-02-15');
    $journal = AccountingJournal::create([
        'book_key' => 'FCDC',
        'reference' => 'POSTED-WITHOUT-OPENING',
        'source_type' => 'pos_sale',
        'source_id' => 'POSTED-WITHOUT-OPENING',
        'accounting_date' => '2026-02-15',
        'posting_period_id' => $period->id,
        'description' => 'Posted activity before cutover approval',
        'status' => 'draft',
        'prepared_by' => $viewer->id,
    ]);
    $journal->lines()->createMany([
        ['accounting_account_id' => $cash->id, 'debit_cents' => 12500, 'credit_cents' => 0],
        ['accounting_account_id' => $equity->id, 'debit_cents' => 0, 'credit_cents' => 12500],
    ]);
    $journal->forceFill(['status' => 'posted', 'posted_at' => now('UTC'), 'posted_by' => $viewer->id])->save();

    Livewire::actingAs($viewer)->test(GeneralLedger::class)
        ->set('accountId', (string) $cash->id)
        ->set('toDate', '2026-02-28')
        ->assertSee('POSTED-WITHOUT-OPENING')
        ->assertSee('Opening balances are incomplete')
        ->assertSee('125.00')
        ->assertDontSee('Posted General Ledger coverage is unavailable');
});
