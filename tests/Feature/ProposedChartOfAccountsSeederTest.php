<?php

use App\Models\AccountingAccount;
use App\Models\AccountingPostingMapping;
use App\Models\Employee;
use App\Support\DemoDatabaseIsolation;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProposedChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('explicit chart seeding creates the complete inactive proposal without business activity', function () {
    $isolation = Mockery::mock(DemoDatabaseIsolation::class);
    $isolation->shouldReceive('verify')->once();
    $result = app(ProposedChartOfAccountsSeeder::class)->seed($isolation);

    expect($result['created'])->toHaveCount(54)
        ->and($result['exact_matches'])->toBe([])
        ->and($result['conflicts'])->toBe([])
        ->and(AccountingAccount::query()->whereIn('code', [
            '1010', '1020', '1030', '1040', '1050', '1060', '1070', '1080', '1500', '1510',
            '2010', '2020', '2030', '2040', '2050', '2060', '2070', '2080',
            '3010', '3020', '3030', '3040', '3050', '4010', '4020', '4030', '4040', '4090',
            '5010', '5020', '5030', '5090', '6010', '6020', '6030', '6040', '6050', '6060', '6070',
            '6080', '6090', '6100', '6110', '6120', '6130', '6140', '6150', '6160', '6190',
            '7010', '7020', '7030', '7040', '7090',
        ])->count())->toBe(54)
        ->and(AccountingAccount::query()->whereIn('code', ['1510', '2040', '3040', '6190'])->pluck('description', 'code')->all())
        ->toHaveKeys(['1510', '2040', '3040', '6190'])
        ->and(AccountingAccount::query()->where('is_active', true)->count())->toBe(0)
        ->and(AccountingAccount::query()->whereNotNull('approved_at')->count())->toBe(0)
        ->and(AccountingAccount::query()->whereNotNull('approved_by')->count())->toBe(0)
        ->and(AccountingAccount::query()->whereNotNull('used_at')->count())->toBe(0)
        ->and(AccountingPostingMapping::query()->count())->toBe(0)
        ->and(DB::table('accounting_journals')->count())->toBe(0)
        ->and(DB::table('accounting_journal_lines')->count())->toBe(0)
        ->and(DB::table('accounting_ytd_summaries')->count())->toBe(0);
});

test('rerun reports exact definitions and preserves approved operational state and history', function () {
    $seeder = app(ProposedChartOfAccountsSeeder::class);
    $isolation = Mockery::mock(DemoDatabaseIsolation::class);
    $isolation->shouldReceive('verify')->twice();
    $seeder->seed($isolation);

    $employee = Employee::create([
        'username' => 'chart-seeder-reviewer',
        'email' => 'chart-seeder-reviewer@example.com',
        'password' => 'test-password',
    ]);
    $now = now();
    $periodId = DB::table('accounting_posting_periods')->insertGetId([
        'book_key' => 'FCDC', 'fiscal_year' => 2026, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        'status' => 'open', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $journalId = DB::table('accounting_journals')->insertGetId([
        'book_key' => 'FCDC', 'reference' => 'SEEDER-HISTORY-001', 'source_type' => 'manual', 'source_id' => 'seed-test',
        'accounting_date' => '2026-10-15', 'posting_period_id' => $periodId, 'description' => 'Existing posted history',
        'status' => 'posted', 'prepared_by' => $employee->id, 'posted_at' => $now, 'posted_by' => $employee->id,
        'approved_at' => $now, 'approved_by' => $employee->id, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $lineId = DB::table('accounting_journal_lines')->insertGetId([
        'accounting_journal_id' => $journalId, 'accounting_account_id' => AccountingAccount::query()->where('code', '1010')->value('id'),
        'debit_cents' => 10000, 'credit_cents' => 0, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $journalBefore = (array) DB::table('accounting_journals')->where('id', $journalId)->first();
    $lineBefore = (array) DB::table('accounting_journal_lines')->where('id', $lineId)->first();
    ksort($journalBefore);
    ksort($lineBefore);

    $account = AccountingAccount::query()->where('code', '1010')->firstOrFail();
    $account->forceFill([
        'is_active' => true,
        'approved_at' => now()->subDay(),
        'approved_by' => $employee->id,
        'used_at' => now()->subHour(),
    ])->saveQuietly();
    $before = $account->fresh()->getAttributes();
    $createdAt = $before['created_at'];
    $updatedAt = $before['updated_at'];
    $mapping = AccountingPostingMapping::create([
        'source' => 'cash',
        'accounting_account_id' => $account->id,
        'approved_at' => now()->subDay(),
        'approved_by' => $employee->id,
    ]);
    $mappingBefore = (array) DB::table('accounting_posting_mappings')->where('id', $mapping->id)->first();
    ksort($mappingBefore);

    $result = $seeder->seed($isolation);
    $mappingAfter = (array) DB::table('accounting_posting_mappings')->where('id', $mapping->id)->first();
    $journalAfter = (array) DB::table('accounting_journals')->where('id', $journalId)->first();
    $lineAfter = (array) DB::table('accounting_journal_lines')->where('id', $lineId)->first();
    ksort($mappingAfter);
    ksort($journalAfter);
    ksort($lineAfter);

    expect($result['created'])->toBe([])
        ->and($result['exact_matches'])->toContain('1010')
        ->and($result['conflicts'])->toBe([])
        ->and($account->fresh()->getAttributes())->toBe($before)
        ->and($account->fresh()->created_at->toDateTimeString())->toBe($createdAt)
        ->and($account->fresh()->updated_at->toDateTimeString())->toBe($updatedAt)
        ->and($mappingAfter)->toBe($mappingBefore)
        ->and($journalAfter)->toBe($journalBefore)
        ->and($lineAfter)->toBe($lineBefore);
});

test('a conflicting definition leaves missing proposal accounts uncreated', function () {
    $seeder = app(ProposedChartOfAccountsSeeder::class);
    $isolation = Mockery::mock(DemoDatabaseIsolation::class);
    $isolation->shouldReceive('verify')->once();
    AccountingAccount::create([
        'code' => '1010', 'name' => 'Different Cash Name', 'description' => 'Existing definition',
        'type' => 'Asset', 'classification' => 'cash', 'normal_balance' => 'debit', 'is_active' => true,
    ]);
    AccountingAccount::create([
        'code' => '1020', 'name' => 'Cash in Bank', 'description' => 'Proposed category: Assets — 1000. Pending treatment: Ordinary proposed account.',
        'type' => 'Asset', 'classification' => 'bank', 'normal_balance' => 'debit', 'is_active' => false,
    ]);
    AccountingAccount::create([
        'code' => '9999', 'name' => 'Supporting Account', 'description' => null,
        'type' => 'Asset', 'classification' => 'other_current_asset', 'normal_balance' => 'debit', 'is_active' => false,
    ]);

    $result = $seeder->seed($isolation);

    expect($result['created'])->toBe([])
        ->and($result['exact_matches'])->toContain('1020')
        ->and($result['conflicts'])->toHaveKey('1010')
        ->and(AccountingAccount::query()->count())->toBe(3)
        ->and(AccountingAccount::query()->where('code', '1020')->exists())->toBeTrue()
        ->and(AccountingAccount::query()->where('code', '1030')->exists())->toBeFalse()
        ->and($result['preserved_unrelated'])->toContain(['code' => '9999', 'name' => 'Supporting Account']);

});
test('the ordinary application seeder does not invoke the proposed chart seeder', function () {
    app(DatabaseSeeder::class)->run();

    expect(AccountingAccount::query()->count())->toBe(0);
});

test('the explicit command refuses the unverified test environment before writing', function () {
    $this->artisan('demo:seed-proposed-chart')
        ->assertFailed()
        ->expectsOutputToContain('not explicitly allowlisted');

    expect(AccountingAccount::query()->count())->toBe(0);
});
test('the explicit command reports results after the isolation gate succeeds', function () {
    $isolation = Mockery::mock(DemoDatabaseIsolation::class);
    $isolation->shouldReceive('verify')->once();
    $this->instance(DemoDatabaseIsolation::class, $isolation);

    $this->artisan('demo:seed-proposed-chart')
        ->assertSuccessful()
        ->expectsOutputToContain('Created: 54')
        ->expectsOutputToContain('Exact matches: 0')
        ->expectsOutputToContain('Preserved unrelated/supporting accounts: 0');

    expect(AccountingAccount::query()->count())->toBe(54);
});
