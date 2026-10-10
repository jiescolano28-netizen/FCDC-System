<?php

use App\Livewire\Accounting\AccountingPeriods;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\OpeningInventoryValuation;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\ManualJournalService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function accountingPeriodActor(array $permissions, string $name): Employee
{
    $employee = Employee::create([
        'username' => $name,
        'email' => $name.'@example.test',
        'password' => bcrypt('password'),
    ]);
    $role = Role::create(['name' => $name.'-role', 'guard_name' => 'web']);
    foreach ($permissions as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $employee->assignRole($role);

    return $employee;
}

function accountingMonth(string $month, string $status = 'open'): AccountingPostingPeriod
{
    $date = CarbonImmutable::parse($month.'-01');

    return AccountingPostingPeriod::create([
        'book_key' => 'FCDC',
        'fiscal_year' => $date->year,
        'period_month' => $date->month,
        'starts_on' => $date->startOfMonth()->toDateString(),
        'ends_on' => $date->endOfMonth()->toDateString(),
        'status' => $status,
    ]);
}

test('accounting period actions require separate close and reopen permissions', function () {
    $period = accountingMonth('2026-01');
    $viewer = accountingPeriodActor(['accounting.view'], 'period-viewer');
    $this->actingAs($viewer);
    Livewire::test(AccountingPeriods::class)->assertSee('January 2026');
    Livewire::test(AccountingPeriods::class)->call('close', $period->id)->assertForbidden();
    Livewire::test(AccountingPeriods::class)->call('selectForReopen', $period->id)->assertForbidden();

    expect($period->fresh()->status)->toBe('open');
});

test('a reconciled month closes with actor history and rejects posting into its closed date', function () {
    $period = accountingMonth('2026-01');
    $actor = accountingPeriodActor(['accounting.close-period', 'accounting.post-journal-entry'], 'period-close-poster');
    $cashId = DB::table('accounting_accounts')->insertGetId([
        'code' => '1000', 'name' => 'Cash', 'type' => 'Asset', 'classification' => 'cash',
        'normal_balance' => 'debit', 'is_active' => true, 'approved_at' => now(),
        'approved_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $capitalId = DB::table('accounting_accounts')->insertGetId([
        'code' => '3000', 'name' => 'Capital', 'type' => 'Equity', 'classification' => 'capital',
        'normal_balance' => 'credit', 'is_active' => true, 'approved_at' => now(),
        'approved_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $openingId = DB::table('accounting_journals')->insertGetId([
        'book_key' => 'FCDC', 'reference' => 'PERIOD-OPENING', 'source_type' => 'opening', 'source_id' => 'FCDC',
        'accounting_date' => '2026-01-01', 'posting_period_id' => $period->id, 'description' => 'Approved cutover',
        'status' => 'posted', 'prepared_by' => $actor->id, 'posted_at' => now(), 'posted_by' => $actor->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('accounting_journal_lines')->insert([
        ['accounting_journal_id' => $openingId, 'accounting_account_id' => $cashId, 'debit_cents' => 10000, 'credit_cents' => 0, 'created_at' => now(), 'updated_at' => now()],
        ['accounting_journal_id' => $openingId, 'accounting_account_id' => $capitalId, 'debit_cents' => 0, 'credit_cents' => 10000, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $closed = app(AccountingPeriodService::class)->close($period->id, 'January schedules reconciled', $actor);
    expect($closed->status)->toBe('closed')
        ->and($closed->closed_by)->toBe($actor->id)
        ->and($closed->close_reason)->toBe('January schedules reconciled')
        ->and($closed->closed_at)->not->toBeNull();

    $draft = app(ManualJournalService::class)->saveDraft([
        'accountingDate' => '2026-01-20',
        'reference' => 'POST-IN-CLOSED-MONTH',
        'description' => 'Must not post',
        'lines' => [
            ['accountId' => $cashId, 'debit' => '1.00', 'credit' => '0'],
            ['accountId' => $capitalId, 'debit' => '0', 'credit' => '1.00'],
        ],
    ], $actor->id);
    expect(fn () => app(ManualJournalService::class)->postDraft($draft->id, $actor->id))
        ->toThrow(ValidationException::class);
    expect($draft->fresh()->status)->toBe('draft');
});

test('period readiness blocks closing unbalanced books', function () {
    $period = accountingMonth('2026-01');
    $actor = accountingPeriodActor(['accounting.close-period'], 'period-closer');
    $accountId = DB::table('accounting_accounts')->insertGetId([
        'code' => '1999', 'name' => 'Unbalanced test asset', 'type' => 'Asset',
        'classification' => 'other_current_asset', 'normal_balance' => 'debit',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $journalId = DB::table('accounting_journals')->insertGetId([
        'book_key' => 'FCDC', 'reference' => 'UNBALANCED-JAN', 'source_type' => 'manual', 'source_id' => 'unbalanced',
        'accounting_date' => '2026-01-15', 'posting_period_id' => $period->id, 'description' => 'Unbalanced test',
        'status' => 'posted', 'prepared_by' => $actor->id, 'posted_at' => now(), 'posted_by' => $actor->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('accounting_journal_lines')->insert([
        'accounting_journal_id' => $journalId, 'accounting_account_id' => $accountId,
        'debit_cents' => 100, 'credit_cents' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => app(AccountingPeriodService::class)->close($period->id, 'Month reconciled', $actor))
        ->toThrow(ValidationException::class);
    expect($period->fresh()->status)->toBe('open');
});

test('reopening an earlier month requires every later closed month explicitly', function () {
    $january = accountingMonth('2026-01', 'closed');
    $february = accountingMonth('2026-02', 'closed');
    $actor = accountingPeriodActor(['accounting.reopen-period'], 'period-reopener');

    expect(fn () => app(AccountingPeriodService::class)->reopen($january->id, [], 'Correct prior month', $actor))
        ->toThrow(ValidationException::class);
    expect($january->fresh()->status)->toBe('closed')
        ->and($february->fresh()->status)->toBe('closed');
});

test('reopening records the whole explicitly authorized chain and requires chronological re-closing', function () {
    $january = accountingMonth('2026-01', 'closed');
    $february = accountingMonth('2026-02', 'closed');
    $actor = accountingPeriodActor(['accounting.reopen-period', 'accounting.close-period'], 'period-chain-reopener');

    $reopened = app(AccountingPeriodService::class)->reopen(
        $january->id,
        [$february->id],
        'Correct January and reconcile forward',
        $actor,
    );
    expect($reopened->modelKeys())->toBe([$january->id, $february->id])
        ->and($january->fresh()->status)->toBe('open')
        ->and($january->fresh()->reopened_by)->toBe($actor->id)
        ->and($february->fresh()->reopen_reason)->toBe('Correct January and reconcile forward');

    expect(fn () => app(AccountingPeriodService::class)->close($february->id, 'Close February first', $actor))
        ->toThrow(ValidationException::class);
});

test('fiscal closing entries must have posted linked reversals before a month reopens', function () {
    $period = accountingMonth('2026-01', 'closed');
    $laterOpenPeriod = accountingMonth('2026-12');
    $actor = accountingPeriodActor(['accounting.reopen-period'], 'period-fiscal-reopener');
    $closingId = DB::table('accounting_journals')->insertGetId([
        'book_key' => 'FCDC', 'reference' => 'FISCAL-CLOSE-DEC', 'source_type' => 'fiscal_year_closing',
        'source_id' => '2026', 'accounting_date' => '2026-12-31', 'posting_period_id' => $laterOpenPeriod->id,
        'description' => 'Fiscal close', 'status' => 'posted', 'prepared_by' => $actor->id,
        'posted_at' => now(), 'posted_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => app(AccountingPeriodService::class)->reopen($period->id, [], 'Correct and reconcile', $actor))
        ->toThrow(ValidationException::class);

    DB::table('accounting_journals')->insert([
        'book_key' => 'FCDC', 'reference' => 'FISCAL-CLOSE-REVERSAL', 'source_type' => 'reversal',
        'source_id' => 'FISCAL-CLOSE-REVERSAL', 'accounting_date' => '2027-01-01', 'posting_period_id' => accountingMonth('2027-01')->id,
        'description' => 'Reverse fiscal close', 'correction_of_id' => $closingId,
        'correction_reason' => 'Reopen year', 'status' => 'posted', 'prepared_by' => $actor->id,
        'posted_at' => now(), 'posted_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    expect(app(AccountingPeriodService::class)->reopen($period->id, [], 'Correct and reconcile', $actor))
        ->toHaveCount(1)
        ->and($period->fresh()->status)->toBe('open');
});

test('period readiness blocks closing when Accounts Payable differs from its invoice schedule', function () {
    $period = accountingMonth('2026-01');
    $actor = accountingPeriodActor(['accounting.close-period'], 'period-ap-mismatch');
    $accountIds = [];
    foreach ([
        ['1000', 'Cash', 'Asset', 'cash', 'debit'],
        ['2100', 'Accounts Payable', 'Liability', 'accounts_payable', 'credit'],
        ['3000', 'Capital', 'Equity', 'capital', 'credit'],
    ] as [$code, $name, $type, $classification, $normalBalance]) {
        $accountIds[$code] = DB::table('accounting_accounts')->insertGetId([
            'code' => $code, 'name' => $name, 'type' => $type, 'classification' => $classification,
            'normal_balance' => $normalBalance, 'is_active' => true, 'approved_at' => now(),
            'approved_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $journalId = DB::table('accounting_journals')->insertGetId([
        'book_key' => 'FCDC', 'reference' => 'AP-MISMATCH-OPENING', 'source_type' => 'opening', 'source_id' => 'FCDC',
        'accounting_date' => '2026-01-01', 'posting_period_id' => $period->id, 'description' => 'Opening',
        'status' => 'posted', 'prepared_by' => $actor->id, 'posted_at' => now(), 'posted_by' => $actor->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('accounting_journal_lines')->insert([
        ['accounting_journal_id' => $journalId, 'accounting_account_id' => $accountIds['1000'], 'debit_cents' => 10000, 'credit_cents' => 0, 'created_at' => now(), 'updated_at' => now()],
        ['accounting_journal_id' => $journalId, 'accounting_account_id' => $accountIds['2100'], 'debit_cents' => 0, 'credit_cents' => 5000, 'created_at' => now(), 'updated_at' => now()],
        ['accounting_journal_id' => $journalId, 'accounting_account_id' => $accountIds['3000'], 'debit_cents' => 0, 'credit_cents' => 5000, 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(app(AccountingPeriodService::class)->readiness($period)['blockers'])
        ->toContain('Accounts Payable does not match the supplier schedule.');
    expect(fn () => app(AccountingPeriodService::class)->close($period->id, 'Unmatched AP', $actor))
        ->toThrow(ValidationException::class);
    expect($period->fresh()->status)->toBe('open');
});

test('close readiness reconciles approved pre-cutover year-to-date earnings', function () {
    $period = accountingMonth('2026-07');
    $actor = accountingPeriodActor(['accounting.close-period'], 'period-ytd-close');
    $accounts = [];
    foreach ([
        ['1000', 'Cash', 'Asset', 'cash', 'debit'],
        ['3000', 'Capital', 'Equity', 'capital', 'credit'],
        ['4000', 'Sales', 'Revenue', 'sales', 'credit'],
        ['6000', 'Expense', 'Expense', 'operating_expense', 'debit'],
    ] as [$code, $name, $type, $classification, $normalBalance]) {
        $accounts[$code] = DB::table('accounting_accounts')->insertGetId([
            'code' => $code, 'name' => $name, 'type' => $type, 'classification' => $classification,
            'normal_balance' => $normalBalance, 'is_active' => true, 'approved_at' => now(),
            'approved_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $openingId = DB::table('accounting_journals')->insertGetId([
        'book_key' => 'FCDC', 'reference' => 'YTD-PERIOD-OPENING', 'source_type' => 'opening', 'source_id' => 'FCDC',
        'accounting_date' => '2026-07-01', 'posting_period_id' => $period->id, 'description' => 'Midyear cutover',
        'status' => 'posted', 'prepared_by' => $actor->id, 'posted_at' => now(), 'posted_by' => $actor->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('accounting_journal_lines')->insert([
        ['accounting_journal_id' => $openingId, 'accounting_account_id' => $accounts['1000'], 'debit_cents' => 100000, 'credit_cents' => 0, 'created_at' => now(), 'updated_at' => now()],
        ['accounting_journal_id' => $openingId, 'accounting_account_id' => $accounts['3000'], 'debit_cents' => 0, 'credit_cents' => 40000, 'created_at' => now(), 'updated_at' => now()],
        ['accounting_journal_id' => $openingId, 'accounting_account_id' => $accounts['4000'], 'debit_cents' => 0, 'credit_cents' => 60000, 'created_at' => now(), 'updated_at' => now()],
    ]);
    $summaryId = DB::table('accounting_ytd_summaries')->insertGetId([
        'book_key' => 'FCDC', 'fiscal_year' => 2026, 'through_date' => '2026-06-30',
        'evidence_reference' => 'YTD approved schedule', 'status' => 'approved',
        'prepared_by' => $actor->id, 'approved_at' => now(), 'approved_by' => $actor->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('accounting_ytd_summary_lines')->insert([
        ['accounting_ytd_summary_id' => $summaryId, 'accounting_account_id' => $accounts['4000'], 'amount_cents' => 100000, 'created_at' => now(), 'updated_at' => now()],
        ['accounting_ytd_summary_id' => $summaryId, 'accounting_account_id' => $accounts['6000'], 'amount_cents' => 40000, 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(app(AccountingPeriodService::class)->readiness($period)['ready'])->toBeTrue()
        ->and(app(AccountingPeriodService::class)->close($period->id, 'July reconciled with YTD support', $actor)->status)->toBe('closed');
});

test('period readiness blocks close when the valued inventory schedule differs from the ledger', function () {
    $period = accountingMonth('2026-01');
    $actor = accountingPeriodActor(['accounting.close-period'], 'period-inventory-mismatch');
    $inventoryAccount = DB::table('accounting_accounts')->insertGetId([
        'code' => '1200', 'name' => 'Inventory', 'type' => 'Asset', 'classification' => 'inventory',
        'normal_balance' => 'debit', 'is_active' => true, 'approved_at' => now(),
        'approved_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $equityAccount = DB::table('accounting_accounts')->insertGetId([
        'code' => '3000', 'name' => 'Capital', 'type' => 'Equity', 'classification' => 'capital',
        'normal_balance' => 'credit', 'is_active' => true, 'approved_at' => now(),
        'approved_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $item = Inventory::create([
        'name' => 'Period readiness stock', 'category' => 'Masonry', 'qty' => '2.00',
        'unit' => 'piece', 'unit_cost' => '50.00', 'reorder_level' => '0.00',
    ]);
    $valuation = OpeningInventoryValuation::create([
        'book_key' => 'FCDC', 'cutover_date' => '2026-01-01',
        'evidence_reference' => 'Period readiness count', 'status' => 'draft',
        'prepared_by' => $actor->id,
    ]);
    $valuation->lines()->create([
        'inventory_id' => $item->id, 'quantity' => '2.00', 'carrying_value_cents' => 10001,
    ]);
    $valuation->forceFill(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $actor->id])->save();
    $openingId = DB::table('accounting_journals')->insertGetId([
        'book_key' => 'FCDC', 'reference' => 'PERIOD-INV-OPENING', 'source_type' => 'opening', 'source_id' => 'FCDC',
        'accounting_date' => '2026-01-01', 'posting_period_id' => $period->id, 'description' => 'Opening inventory',
        'status' => 'posted', 'prepared_by' => $actor->id, 'posted_at' => now(), 'posted_by' => $actor->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('accounting_journal_lines')->insert([
        ['accounting_journal_id' => $openingId, 'accounting_account_id' => $inventoryAccount, 'debit_cents' => 10000, 'credit_cents' => 0, 'created_at' => now(), 'updated_at' => now()],
        ['accounting_journal_id' => $openingId, 'accounting_account_id' => $equityAccount, 'debit_cents' => 0, 'credit_cents' => 10000, 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(app(AccountingPeriodService::class)->readiness($period)['blockers'])
        ->toContain('Inventory does not match the valued-stock schedule.');
    expect(fn () => app(AccountingPeriodService::class)->close($period->id, 'Unmatched inventory', $actor))
        ->toThrow(ValidationException::class);
    expect($period->fresh()->status)->toBe('open');
});
