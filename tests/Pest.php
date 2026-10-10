<?php

use App\Models\AccountingAccount;
use App\Models\AccountingPostingMapping;
use App\Models\Employee;
use App\Models\Inventory;
use App\Services\Accounting\OpeningBooksService;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function grantEmployeeTestPermissions(Employee $employee, array $permissions): Employee
{
    $role = Role::create([
        'name' => 'test-permissions-'.$employee->getKey(),
        'guard_name' => 'web',
    ]);
    $role->syncPermissions(array_map(
        fn (string $name) => Permission::findOrCreate($name, 'web'),
        $permissions,
    ));
    $employee->assignRole($role);

    return $employee;
}

function setupPosAccountingBooks(array $items, array $openingValues = []): array
{
    $reviewer = grantEmployeeTestPermissions(Employee::create([
        'username' => 'pos.books.'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => Hash::make('secret-password'),
    ]), ['accounting.maintain-opening-books', 'accounting.approve-opening-books']);
    $accounts = [];
    foreach ([
        ['inventory', 'Asset', 'debit'],
        ['capital', 'Equity', 'credit'],
        ['cash', 'Asset', 'debit'],
        ['bank', 'Asset', 'debit'],
        ['card_clearing', 'Asset', 'debit'],
        ['sales', 'Revenue', 'credit'],
        ['output_vat', 'Liability', 'credit'],
        ['cost_of_goods_sold', 'Expense', 'debit'],
    ] as $index => [$classification, $type, $normalBalance]) {
        $accounts[$classification] = AccountingAccount::create([
            'code' => (string) (1200 + $index),
            'name' => ucfirst(str_replace('_', ' ', $classification)),
            'type' => $type,
            'classification' => $classification,
            'normal_balance' => $normalBalance,
            'is_active' => true,
            'approved_at' => now(),
            'approved_by' => $reviewer->id,
        ]);
    }
    $inventoryLines = [];
    $totalValueCents = 0;
    foreach ($items as $item) {
        if (! $item instanceof Inventory) {
            throw new InvalidArgumentException('POS accounting books require inventory records.');
        }
        $valueCents = array_key_exists($item->id, $openingValues)
            ? (int) round((float) $openingValues[$item->id] * 100)
            : (int) round((float) $item->qty * (float) $item->unit_cost * 100);
        $totalValueCents += $valueCents;
        $inventoryLines[] = [
            'inventoryId' => (string) $item->id,
            'quantity' => (string) $item->qty,
            'value' => number_format($valueCents / 100, 2, '.', ''),
        ];
    }
    $amount = number_format($totalValueCents / 100, 2, '.', '');
    $date = now('Asia/Manila')->toDateString();
    $books = app(OpeningBooksService::class);
    $books->saveInventoryValuation($date, 'POS test opening count', $inventoryLines, $reviewer->id);
    $books->saveOpening($date, [
        ['accountId' => $accounts['inventory']->id, 'debit' => $amount, 'credit' => ''],
        ['accountId' => $accounts['capital']->id, 'debit' => '', 'credit' => $amount],
    ], $reviewer->id);
    $books->approveInventoryValuation($reviewer->id);
    $books->approveOpening($reviewer->id);
    foreach (['cash', 'bank', 'card_clearing', 'sales', 'output_vat'] as $source) {
        AccountingPostingMapping::create([
            'source' => $source,
            'accounting_account_id' => $accounts[$source]->id,
            'approved_at' => now(),
            'approved_by' => $reviewer->id,
        ]);
    }
    foreach (['cogs' => 'cost_of_goods_sold', 'inventory' => 'inventory'] as $source => $accountKey) {
        AccountingPostingMapping::create([
            'source' => $source,
            'accounting_account_id' => $accounts[$accountKey]->id,
            'approved_at' => now(),
            'approved_by' => $reviewer->id,
        ]);
    }

    $accounts['cogs'] = $accounts['cost_of_goods_sold'];

    return $accounts;

}

function something()
{
    // ..
}
