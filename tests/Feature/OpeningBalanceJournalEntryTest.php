<?php

use App\Livewire\Accounting\GeneralLedger;
use App\Livewire\Accounting\JournalEntry;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function openingJournalEntryEmployee(array $permissions): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'opening.journal.'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => Hash::make('opening-journal-password'),
    ]), $permissions);
}

function openingJournalEntryAccount(string $code, string $classification, string $type, string $normalBalance): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => ucfirst(str_replace('_', ' ', $classification)),
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => $normalBalance,
        'is_active' => true,
        'approved_at' => now(),
    ]);
}

function postOpeningJournalEntryPeriod(string $date): AccountingPostingPeriod
{
    $month = Carbon::parse($date, 'Asia/Manila')->startOfMonth();

    return AccountingPostingPeriod::firstOrCreate(
        ['book_key' => 'FCDC', 'fiscal_year' => $month->year, 'period_month' => $month->month],
        ['starts_on' => $month->toDateString(), 'ends_on' => $month->copy()->endOfMonth()->toDateString(), 'status' => 'open'],
    );
}

test('opening balances are prepared and approved from the journal entry page with separate permissions', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 09:00:00', 'Asia/Manila'));
    $cash = openingJournalEntryAccount('1000', 'cash', 'Asset', 'debit');
    $capital = openingJournalEntryAccount('3000', 'capital', 'Equity', 'credit');
    $preparer = openingJournalEntryEmployee(['accounting.view', 'accounting.maintain-opening-books']);
    $reviewer = openingJournalEntryEmployee(['accounting.view', 'accounting.approve-opening-books']);
    $viewer = openingJournalEntryEmployee(['accounting.view']);

    $this->actingAs($preparer);
    Livewire::test(JournalEntry::class)
        ->assertSee('Opening Balance / Cutover Journal')
        ->set('cutoverDate', '2026-07-01')
        ->set('openingLines', [
            ['accountId' => (string) $cash->id, 'debit' => '100.00', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '99.99'],
        ])
        ->assertSee('Not balanced')
        ->call('saveOpening')
        ->assertHasErrors('lines');
    expect(AccountingJournal::where('source_type', 'opening')->exists())->toBeFalse();

    Livewire::test(JournalEntry::class)
        ->set('cutoverDate', '2026-07-01')
        ->set('openingLines', [
            ['accountId' => (string) $cash->id, 'debit' => '100.00', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '100.00'],
        ])
        ->assertSee('Balanced')
        ->call('saveOpening')
        ->assertHasNoErrors()
        ->assertSee('Opening balance journal saved');

    $opening = AccountingJournal::where('source_type', 'opening')->where('source_id', 'FCDC')->firstOrFail();
    expect($opening->status)->toBe('draft')
        ->and($opening->accounting_date->toDateString())->toBe('2026-07-01')
        ->and($opening->lines->sum('debit_cents'))->toBe(10000)
        ->and($opening->lines->sum('credit_cents'))->toBe(10000);

    $this->actingAs($viewer);
    Livewire::test(JournalEntry::class)->call('saveOpening')->assertForbidden();
    Livewire::test(JournalEntry::class)->call('approveOpening')->assertForbidden();

    $this->actingAs($reviewer);
    Livewire::test(JournalEntry::class)
        ->call('approveOpening')
        ->assertHasNoErrors()
        ->assertSee('Opening balance journal approved and posted')
        ->assertSee('Approved and posted');

    expect($opening->fresh()->status)->toBe('posted')
        ->and($opening->fresh()->approved_by)->toBe($reviewer->id)
        ->and($opening->fresh()->posted_by)->toBe($reviewer->id);
});

test('the general ledger uses an approved opening balance and applies later posted activity as running balances', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-15 09:00:00', 'Asia/Manila'));
    $cash = openingJournalEntryAccount('1000', 'cash', 'Asset', 'debit');
    $capital = openingJournalEntryAccount('3000', 'capital', 'Equity', 'credit');
    $revenue = openingJournalEntryAccount('4000', 'sales', 'Revenue', 'credit');
    $preparer = openingJournalEntryEmployee(['accounting.view', 'accounting.maintain-opening-books']);
    $reviewer = openingJournalEntryEmployee(['accounting.view', 'accounting.approve-opening-books']);
    $viewer = openingJournalEntryEmployee(['accounting.view']);
    postOpeningJournalEntryPeriod('2026-07-01');

    $this->actingAs($preparer);
    Livewire::test(JournalEntry::class)
        ->set('cutoverDate', '2026-07-01')
        ->set('openingLines', [
            ['accountId' => (string) $cash->id, 'debit' => '100.00', 'credit' => ''],
            ['accountId' => (string) $capital->id, 'debit' => '', 'credit' => '100.00'],
        ])
        ->call('saveOpening')
        ->assertHasNoErrors();
    $this->actingAs($reviewer);
    Livewire::test(JournalEntry::class)->call('approveOpening')->assertHasNoErrors();

    $period = postOpeningJournalEntryPeriod('2026-07-10');
    $later = AccountingJournal::create([
        'book_key' => 'FCDC',
        'reference' => 'AFTER-OPENING-001',
        'source_type' => 'pos_sale',
        'source_id' => 'TEST-SALE-001',
        'accounting_date' => '2026-07-10',
        'posting_period_id' => $period->id,
        'description' => 'Later posted transaction',
        'status' => 'draft',
        'prepared_by' => $preparer->id,
    ]);
    $later->lines()->create(['accounting_account_id' => $cash->id, 'debit_cents' => 25000, 'credit_cents' => 0]);
    $later->lines()->create(['accounting_account_id' => $revenue->id, 'debit_cents' => 0, 'credit_cents' => 25000]);
    $later->forceFill(['status' => 'posted', 'posted_at' => now('UTC'), 'posted_by' => $reviewer->id])->save();

    $this->actingAs($viewer);
    $ledger = Livewire::test(GeneralLedger::class)
        ->set('accountId', (string) $cash->id)
        ->assertSee('Opening position')
        ->assertSee('AFTER-OPENING-001');

    $row = $ledger->viewData('allAccountRows')->first(fn (array $row) => $row['account']->id === $cash->id);

    expect($row)->not->toBeNull()
        ->and($row['opening'])->toBe(10000)
        ->and($row['total_debit_cents'])->toBe(25000)
        ->and($row['total_credit_cents'])->toBe(0)
        ->and($row['closing'])->toBe(35000)
        ->and($row['lines'])->toHaveCount(1)
        ->and($row['lines']->first()['balance_cents'])->toBe(35000);
});
