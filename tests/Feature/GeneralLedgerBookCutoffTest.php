<?php

use App\Livewire\Accounting\GeneralLedger;
use App\Livewire\Accounting\TrialBalance;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Services\Accounting\OpeningBooksService;
use Carbon\CarbonImmutable;
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

function ledgerBookEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'ledger.book.viewer',
        'email' => 'ledger.book@example.com',
        'password' => Hash::make('ledger-book-password'),
    ]), ['accounting.view']);
}

function ledgerBookAccount(string $code, string $normalBalance = 'debit'): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => "Ledger book account {$code}",
        'type' => $normalBalance === 'debit' ? 'Asset' : 'Equity',
        'classification' => $normalBalance === 'debit' ? 'cash' : 'capital',
        'normal_balance' => $normalBalance,
        'is_active' => true,
        'approved_at' => now(),
    ]);
}

function postLedgerBookJournal(
    string $reference,
    string $book,
    string $date,
    array $lines,
    string $source = 'manual',
    string $entryType = 'standard',
    string $status = 'posted',
): AccountingJournal {
    $year = (int) substr($date, 0, 4);
    $month = (int) substr($date, 5, 2);
    $period = AccountingPostingPeriod::firstOrCreate(
        ['book_key' => $book, 'fiscal_year' => $year, 'period_month' => $month],
        [
            'starts_on' => sprintf('%04d-%02d-01', $year, $month),
            'ends_on' => CarbonImmutable::parse(sprintf('%04d-%02d-01', $year, $month))->endOfMonth()->toDateString(),
            'status' => 'open',
        ],
    );
    $actor = Employee::query()->first() ?? ledgerBookEmployee();
    $journal = AccountingJournal::create([
        'book_key' => $book,
        'reference' => $reference,
        'source_type' => $source,
        'entry_type' => $entryType,
        'source_id' => $source === 'opening' && $book === 'FCDC' ? 'FCDC' : $reference,
        'accounting_date' => $date,
        'posting_period_id' => $period->id,
        'description' => $reference,
        'status' => 'draft',
        'prepared_by' => $actor->id,
    ]);
    foreach ($lines as [$account, $debit, $credit]) {
        $journal->lines()->create([
            'accounting_account_id' => $account->id,
            'debit_cents' => $debit,
            'credit_cents' => $credit,
        ]);
    }

    if ($status === 'posted') {
        if ($source === 'manual') {
            $journal->forceFill(['status' => 'pending_review'])->save();
            $journal->forceFill([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();
        }
        $journal->forceFill(['status' => 'posted'])->save();
    }

    return $journal;
}

test('book and cut-off selectors scope posted activity and report opening, debits, credits and ending balances', function () {
    $viewer = ledgerBookEmployee();
    $cash = ledgerBookAccount('1000');
    $equity = ledgerBookAccount('3000', 'credit');

    postLedgerBookJournal('FCDC-OPENING', 'FCDC', '2026-01-01', [[$cash, 100000, 0], [$equity, 0, 100000]], 'opening');
    postLedgerBookJournal('FCDC-FEB-1', 'FCDC', '2026-02-01', [[$cash, 25000, 0], [$equity, 0, 25000]]);
    postLedgerBookJournal('FCDC-FEB-2', 'FCDC', '2026-02-10', [[$cash, 5000, 0], [$equity, 0, 5000]]);
    postLedgerBookJournal('FCDC-FEB-3', 'FCDC', '2026-02-28', [[$equity, 10000, 0], [$cash, 0, 10000]], 'reversal');
    postLedgerBookJournal('FCDC-MAR', 'FCDC', '2026-03-01', [[$cash, 90000, 0], [$equity, 0, 90000]]);

    postLedgerBookJournal('ALT-OPENING', 'ALT', '2026-01-01', [[$cash, 7000, 0], [$equity, 0, 7000]], 'opening');
    postLedgerBookJournal('ALT-FEB', 'ALT', '2026-02-05', [[$cash, 2000, 0], [$equity, 0, 2000]]);

    $component = Livewire::actingAs($viewer)->test(GeneralLedger::class)
        ->set('bookKey', 'FCDC')
        ->set('accountId', (string) $cash->id)
        ->set('cutoffDate', '2026-02-01')
        ->set('toDate', '2026-02-28')
        ->assertSee('Book')
        ->assertSee('Ledger view')
        ->assertSee('Cut-off date')
        ->assertSee('FCDC General Book')
        ->assertSee('Total debits')
        ->assertSee('Total credits')
        ->assertSee('Ending balance')
        ->assertSee('FCDC-FEB-1')
        ->assertDontSee('FCDC-MAR')
        ->assertDontSee('ALT-FEB');

    $row = $component->viewData('allAccountRows')->first();
    expect($row['opening'])->toBe(100000)
        ->and($row['total_debit_cents'])->toBe(30000)
        ->and($row['total_credit_cents'])->toBe(10000)
        ->and($row['closing'])->toBe(120000);

    $alt = Livewire::actingAs($viewer)->test(GeneralLedger::class)
        ->set('bookKey', 'ALT')
        ->set('accountId', (string) $cash->id)
        ->set('cutoffDate', '2026-01-01')
        ->set('toDate', '2026-02-28')
        ->assertSee('ALT-FEB')
        ->assertSee('Alt Book')
        ->assertDontSee('FCDC-FEB-1')
        ->assertDontSee('FCDC-OPENING');

    $altRow = $alt->viewData('allAccountRows')->first();
    expect($altRow['opening'])->toBe(0)
        ->and($altRow['total_debit_cents'])->toBe(9000)
        ->and($altRow['total_credit_cents'])->toBe(0)
        ->and($altRow['closing'])->toBe(9000);
});

test('cut-off opening balance excludes drafts and the general ledger ending balances agree with the trial balance', function () {
    $viewer = ledgerBookEmployee();
    $cash = ledgerBookAccount('1000');
    $equity = ledgerBookAccount('3000', 'credit');
    $sales = ledgerBookAccount('4000', 'credit');
    $expense = ledgerBookAccount('6000');

    postLedgerBookJournal('OPENING-RECON', 'FCDC', '2026-01-01', [[$cash, 100000, 0], [$equity, 0, 100000]], 'opening');
    postLedgerBookJournal('STANDARD-RECON', 'FCDC', '2026-02-05', [[$cash, 25000, 0], [$sales, 0, 25000]]);
    postLedgerBookJournal('ADJUSTING-RECON', 'FCDC', '2026-02-20', [[$expense, 500, 0], [$cash, 0, 500]], 'manual', 'adjusting');
    postLedgerBookJournal('DRAFT-RECON', 'FCDC', '2026-02-10', [[$cash, 99900, 0], [$equity, 0, 99900]], 'manual', 'standard', 'draft');

    $gl = Livewire::actingAs($viewer)->test(GeneralLedger::class)
        ->set('bookKey', 'FCDC')
        ->set('cutoffDate', '2026-02-01')
        ->set('toDate', '2026-02-28')
        ->assertDontSee('DRAFT-RECON')
        ->assertSee('Reconcile with Trial Balance');

    $tb = Livewire::actingAs($viewer)->test(TrialBalance::class)
        ->set('fromDate', '2026-02-01')
        ->set('toDate', '2026-02-28');

    $glClosing = $gl->viewData('allAccountRows')->mapWithKeys(fn (array $row) => [
        $row['account']->id => $row['closing'],
    ]);
    $tbClosing = $tb->viewData('accounts')->mapWithKeys(fn (array $row) => [
        $row['account']->id => $row['closing_debit_cents'] - $row['closing_credit_cents'],
    ]);

    foreach ($glClosing as $accountId => $endingBalance) {
        expect($tbClosing->get($accountId))->toBe($endingBalance);
    }
    expect($glClosing->get($cash->id))->toBe(124500)
        ->and($glClosing->get($expense->id))->toBe(500)
        ->and($glClosing->get($sales->id))->toBe(-25000)
        ->and($glClosing->get($equity->id))->toBe(-100000);
});
