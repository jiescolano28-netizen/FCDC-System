<?php

use App\Livewire\Accounting\TrialBalance;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\OpeningInventoryValuation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createTrialBalanceEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'trial.viewer',
        'email' => 'trial@example.com',
        'password' => Hash::make('trial-password'),
    ]), ['accounting.view']);
}

function trialBalanceAccount(string $code, string $classification, string $type, string $balance, bool $active = true): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => "Account {$code}",
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => $balance,
        'is_active' => $active,
        'approved_at' => $active ? now() : null,
    ]);
}

function postTrialBalanceJournal(string $reference, string $date, array $lines, string $source = 'manual', ?string $sourceId = null): AccountingJournal
{
    $year = (int) substr($date, 0, 4);
    $period = AccountingPostingPeriod::firstOrCreate(
        ['book_key' => 'FCDC', 'fiscal_year' => $year],
        ['starts_on' => "{$year}-01-01", 'ends_on' => "{$year}-12-31", 'status' => 'open'],
    );
    $actor = Employee::query()->first() ?? createTrialBalanceEmployee();
    $journal = AccountingJournal::create([
        'book_key' => 'FCDC',
        'reference' => $reference,
        'source_type' => $source,
        'source_id' => $sourceId ?? $reference,
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
    $journal->forceFill(['status' => 'posted'])->save();

    return $journal;
}

test('trial balance is an authenticated accounting report with supported date coverage', function () {
    $this->get(route('accounting.trial-balance'))->assertRedirect(route('login'));

    $this->actingAs(createTrialBalanceEmployee())
        ->get(route('accounting.trial-balance'))
        ->assertOk()
        ->assertSee('Trial Balance')
        ->assertSee('Accounting')
        ->assertSee('From date')
        ->assertSee('To date')
        ->assertSee('Posted General Ledger coverage is unavailable')
        ->assertSee(route('accounting.trial-balance'), false)
        ->assertSee('class="app-body trial-balance-body"', false);

    $restricted = grantEmployeeTestPermissions(Employee::create([
        'username' => 'trial.denied',
        'email' => 'trial.denied@example.com',
        'password' => Hash::make('trial-password'),
    ]), []);
    $this->actingAs($restricted)->get(route('accounting.trial-balance'))->assertForbidden();
});

test('trial balance carries opening balances through an empty month and presents normal debit and credit sides', function () {
    $viewer = createTrialBalanceEmployee();
    $cash = trialBalanceAccount('1000', 'cash', 'Asset', 'debit');
    $payable = trialBalanceAccount('2000', 'accounts_payable', 'Liability', 'credit');
    $equity = trialBalanceAccount('3000', 'capital', 'Equity', 'credit');
    $inactiveWithBalance = trialBalanceAccount('1010', 'other_asset', 'Asset', 'debit', false);
    postTrialBalanceJournal('OPENING-TB', '2026-01-01', [
        [$cash, 10000, 0], [$payable, 0, 2500], [$equity, 0, 8000], [$inactiveWithBalance, 500, 0],
    ], 'opening', 'FCDC');

    Livewire::actingAs($viewer)->test(TrialBalance::class)
        ->set('fromDate', '2026-02-01')
        ->set('toDate', '2026-02-28')
        ->assertSee('Account 1000')
        ->assertSee('100.00')
        ->assertSee('Account 2000')
        ->assertSee('25.00')
        ->assertSee('Account 1010')
        ->assertSee('5.00')
        ->assertSee('No activity during this period')
        ->assertSee('Books balance')
        ->assertSee('Schedule mismatch');
});

test('trial balance includes both boundary days, cumulative closing and reports unequal books without a plug', function () {
    $viewer = createTrialBalanceEmployee();
    $cash = trialBalanceAccount('1000', 'cash', 'Asset', 'debit');
    $equity = trialBalanceAccount('3000', 'capital', 'Equity', 'credit');
    $inactive = trialBalanceAccount('1010', 'other_asset', 'Asset', 'debit', false);
    postTrialBalanceJournal('OPENING-TB-2', '2026-01-01', [[$cash, 10000, 0], [$equity, 0, 10000]], 'opening', 'FCDC');
    postTrialBalanceJournal('FIRST-DAY-TB', '2026-02-01', [[$cash, 2500, 0], [$equity, 0, 2500]]);
    postTrialBalanceJournal('LAST-DAY-TB', '2026-02-28', [[$cash, 0, 1000], [$equity, 1000, 0]]);
    postTrialBalanceJournal('AFTER-TB', '2026-03-01', [[$inactive, 2000, 0], [$equity, 0, 2000]]);
    AccountingJournalLine::query()->whereHas('journal', fn ($query) => $query->where('reference', 'LAST-DAY-TB'))
        ->where('accounting_account_id', $equity->id)->update(['debit_cents' => 0]);

    Livewire::actingAs($viewer)->test(TrialBalance::class)
        ->set('fromDate', '2026-02-01')
        ->set('toDate', '2026-02-28')
        ->assertSee('Account 1000')
        ->assertSee('115.00')
        ->assertSee('25.00')
        ->assertSee('10.00')
        ->assertSee('Account 3000')
        ->assertSee('125.00')
        ->assertSee('Accounting error')
        ->assertDontSee('suspense')
        ->assertDontSee('plug');

    Livewire::actingAs($viewer)->test(TrialBalance::class)
        ->set('fromDate', '2025-12-31')
        ->set('toDate', '2026-01-02')
        ->assertSee('before approved accounting cutover coverage');

    Livewire::actingAs($viewer)->test(TrialBalance::class)
        ->set('fromDate', '2026-03-01')
        ->set('toDate', '2026-02-28')
        ->assertSee('Choose a valid inclusive date range.');
});

test('trial balance reports an inventory schedule mismatch independently of balanced books', function () {
    $viewer = createTrialBalanceEmployee();
    $inventory = trialBalanceAccount('1200', 'inventory', 'Asset', 'debit');
    $equity = trialBalanceAccount('3000', 'capital', 'Equity', 'credit');
    $item = Inventory::create([
        'name' => 'Valued trial balance stock', 'category' => 'Masonry', 'qty' => '2.00',
        'unit' => 'piece', 'unit_cost' => '50.00', 'reorder_level' => '0.00',
    ]);
    $valuation = OpeningInventoryValuation::create([
        'book_key' => 'FCDC',
        'cutover_date' => '2026-01-01',
        'evidence_reference' => 'Cutover stock count',
        'status' => 'draft',
        'prepared_by' => $viewer->id,
    ]);
    $valuation->lines()->create([
        'inventory_id' => $item->id,
        'quantity' => '2.00',
        'carrying_value_cents' => 10001,
    ]);
    $valuation->forceFill([
        'status' => 'approved',
        'approved_by' => $viewer->id,
        'approved_at' => now(),
    ])->save();
    postTrialBalanceJournal('OPENING-INV-TB', '2026-01-01', [
        [$inventory, 10000, 0], [$equity, 0, 10000],
    ], 'opening', 'FCDC');

    Livewire::actingAs($viewer)->test(TrialBalance::class)
        ->set('fromDate', '2026-02-01')
        ->set('toDate', '2026-02-28')
        ->assertSee('Books balance')
        ->assertSee('Inventory: book 100.00; valued-item schedule 100.01.')
        ->assertSee('Schedule mismatch.');
});
