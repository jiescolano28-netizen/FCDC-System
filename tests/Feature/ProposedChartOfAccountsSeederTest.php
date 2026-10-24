<?php

use App\Models\AccountingAccount;
use App\Models\AccountingPostingMapping;
use App\Support\DemoDatabaseIsolation;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OfficialChartOfAccountsSeeder;
use Database\Seeders\ProposedChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('official chart seeding creates the complete approved chart and posting mappings', function () {
    $result = app(OfficialChartOfAccountsSeeder::class)->seed();

    expect($result['created'])->toHaveCount(21)
        ->and($result['updated'])->toBe([])
        ->and($result['exact_matches'])->toBe([])
        ->and($result['conflicts'])->toBe([])
        ->and($result['preserved_unrelated'])->toBe([])
        ->and(AccountingAccount::query()->where('is_active', true)->count())->toBe(21)
        ->and(AccountingAccount::query()->whereIn('code', [
            '1010', '1020', '1030', '1040', '1050', '2010', '2020',
            '3010', '3020', '4010', '4020', '4030', '5010', '6010',
            '6120', '6130', '6140', '6150', '6160', '6170', '6180',
        ])->count())->toBe(21)
        ->and(AccountingAccount::query()->whereNotNull('approved_at')->count())->toBe(21)
        ->and(AccountingAccount::query()->whereNotNull('used_at')->count())->toBe(0)
        ->and(AccountingAccount::query()->pluck('code')->duplicates()->isEmpty())->toBeTrue()
        ->and(AccountingAccount::query()->pluck('name')->duplicates()->isEmpty())->toBeTrue()
        ->and(AccountingPostingMapping::query()->count())->toBe(10)
        ->and(AccountingPostingMapping::query()->whereNull('approved_at')->count())->toBe(0)
        ->and(AccountingPostingMapping::query()->where('source', 'card_clearing')->firstOrFail()->account->code)->toBe('1020');
});

test('official descriptions and normal balances match the supplied account table', function () {
    app(OfficialChartOfAccountsSeeder::class)->seed();

    $cash = AccountingAccount::query()->where('code', '1010')->firstOrFail();
    $returns = AccountingAccount::query()->where('code', '4020')->firstOrFail();
    $cogs = AccountingAccount::query()->where('code', '5010')->firstOrFail();
    $reworking = AccountingAccount::query()->where('code', '6010')->firstOrFail();
    $utilities = AccountingAccount::query()->where('code', '6130')->firstOrFail();

    $expectedNames = [
        '1010' => 'Cash on Hand',
        '1020' => 'Cash in Bank',
        '1030' => 'Input VAT',
        '1040' => 'Recovered Materials Inventory',
        '1050' => 'Cleaning and Reworking Supplies Inventory',
        '2010' => 'Accounts Payable',
        '2020' => 'Output VAT Payable',
        '3010' => 'Share Capital',
        '3020' => 'Retained Earnings',
        '4010' => 'Recovered Materials Sales Revenue',
        '4020' => 'Sales Returns and Allowances',
        '4030' => 'Sales Discounts',
        '5010' => 'Cost of Recovered Materials Sold',
        '6010' => 'Reworking Costs',
        '6120' => 'Rent Expense',
        '6130' => 'Utilities Expense',
        '6140' => 'Repairs and Maintenance Expense',
        '6150' => 'Office Supplies Expense',
        '6160' => 'Cleaning Supplies Expense',
        '6170' => 'Transportation and Delivery Expense',
        '6180' => 'Other Operating Expenses',
    ];

    expect(AccountingAccount::query()->pluck('name', 'code')->all())->toBe($expectedNames)
        ->and($cash->name)->toBe('Cash on Hand')
        ->and($cash->type)->toBe('Asset')
        ->and($cash->classification)->toBe('cash')
        ->and($cash->normal_balance)->toBe('debit')
        ->and($cash->description)->toBe('Cash received from completed POS sales paid in cash.')
        ->and($returns->name)->toBe('Sales Returns and Allowances')
        ->and($returns->classification)->toBe('contra_revenue')
        ->and($returns->normal_balance)->toBe('debit')
        ->and($returns->displayType())->toBe('Contra-Revenue')
        ->and($cogs->displayType())->toBe('Cost of Sales')
        ->and($reworking->displayType())->toBe('Cost of Sales / Expense')
        ->and($utilities->displayType())->toBe('Operating Expense')
        ->and($returns->isApprovedForPosting())->toBeTrue();
});

test('rerunning the official chart preserves accounts, mappings, and history', function () {
    $seeder = app(OfficialChartOfAccountsSeeder::class);
    $seeder->seed();

    $account = AccountingAccount::query()->where('code', '1010')->firstOrFail();
    $account->forceFill([
        'is_active' => true,
        'approved_at' => now()->subDay(),
        'used_at' => now()->subHour(),
    ])->saveQuietly();
    $before = $account->fresh()->getAttributes();
    $mapping = AccountingPostingMapping::query()->where('source', 'cash')->firstOrFail();
    $mappingBefore = $mapping->getAttributes();

    $result = $seeder->seed();

    expect($result['created'])->toBe([])
        ->and($result['updated'])->toBe([])
        ->and($result['exact_matches'])->toHaveCount(21)
        ->and($result['legacy_accounts'])->toBe([])
        ->and($account->fresh()->name)->toBe($before['name'])
        ->and($mapping->fresh()->getAttributes())->toBe($mappingBefore);
});

test('extra and outdated accounts are retired without deleting their history', function () {
    $extra = AccountingAccount::create([
        'code' => '1090',
        'name' => 'Card Settlement Clearing',
        'type' => 'Asset',
        'classification' => 'card_clearing',
        'normal_balance' => 'debit',
        'is_active' => true,
        'approved_at' => now(),
    ]);
    $outdated = AccountingAccount::create([
        'code' => '2030',
        'name' => 'Output VAT',
        'type' => 'Liability',
        'classification' => 'output_vat',
        'normal_balance' => 'credit',
        'is_active' => true,
        'approved_at' => now(),
    ]);
    $outdated->markUsed();

    $result = app(OfficialChartOfAccountsSeeder::class)->seed();

    expect($result['preserved_unrelated'])->toContain(['code' => '1090', 'name' => 'Card Settlement Clearing'])
        ->and($result['preserved_unrelated'])->toContain(['code' => '2030', 'name' => 'Output VAT'])
        ->and($extra->fresh()->is_active)->toBeFalse()
        ->and($outdated->fresh()->is_active)->toBeFalse()
        ->and($outdated->fresh()->used_at)->not->toBeNull()
        ->and(AccountingAccount::query()->where('code', '1090')->exists())->toBeTrue()
        ->and(AccountingAccount::query()->where('code', '2030')->exists())->toBeTrue()
        ->and(AccountingAccount::query()->where('is_active', true)->count())->toBe(21);
});

test('a used conflicting account is preserved under a legacy code before the official account is created', function () {
    $seeder = app(OfficialChartOfAccountsSeeder::class);
    $seeder->seed();

    $old = AccountingAccount::query()->where('code', '1050')->firstOrFail();
    $old->forceFill([
        'name' => 'Old recovered stock',
        'classification' => 'inventory',
        'normal_balance' => 'debit',
        'used_at' => now(),
    ])->saveQuietly();
    $oldId = $old->id;

    $result = $seeder->seed();

    $official = AccountingAccount::query()->where('code', '1050')->firstOrFail();
    $legacy = AccountingAccount::query()->where('id', $oldId)->firstOrFail();

    expect($result['created'])->toContain('1050')
        ->and($result['legacy_accounts'])->toContain(['original' => '1050', 'legacy' => 'LEGACY-1050'])
        ->and($official->id)->not->toBe($oldId)
        ->and($official->name)->toBe('Cleaning and Reworking Supplies Inventory')
        ->and($official->is_active)->toBeTrue()
        ->and($legacy->code)->toBe('LEGACY-1050')
        ->and($legacy->name)->toBe('Old recovered stock (Legacy)')
        ->and($legacy->is_active)->toBeFalse()
        ->and($legacy->used_at)->not->toBeNull();
});

test('the ordinary application seeder installs the official chart', function () {
    app(DatabaseSeeder::class)->run();

    expect(AccountingAccount::query()->count())->toBe(21)
        ->and(AccountingAccount::query()->where('is_active', true)->count())->toBe(21)
        ->and(AccountingPostingMapping::query()->count())->toBe(10);
});

test('the legacy proposed-chart wrapper applies the official chart on a verified demo database', function () {
    $isolation = Mockery::mock(DemoDatabaseIsolation::class);
    $isolation->shouldReceive('verify')->once();

    $result = app(ProposedChartOfAccountsSeeder::class)->seed($isolation);

    expect($result['created'])->toHaveCount(21)
        ->and(AccountingAccount::query()->count())->toBe(21);
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
        ->expectsOutputToContain('Created: 21')
        ->expectsOutputToContain('Exact matches: 0');

    expect(AccountingAccount::query()->count())->toBe(21);
});
