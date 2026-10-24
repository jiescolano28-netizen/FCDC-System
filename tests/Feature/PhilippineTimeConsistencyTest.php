<?php

use App\Livewire\ActivityLog\ActivityLogPage;
use App\Livewire\Dashboard\ReportsPage;
use App\Livewire\Inventory\OperatingCostRecordManagement;
use App\Livewire\Inventory\ReworkingCostRecordManagement;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\TaxCompliance\VatRecords;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\OperatingCostRecord;
use App\Models\PosTransaction;
use App\Models\StockMovement;
use App\Support\PhilippineTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function phTimeEmployee(): Employee
{
    static $counter = 0;
    $counter++;

    return grantEmployeeAdministratorRole(Employee::create([
        'username' => 'ph.time.'.$counter,
        'email' => 'ph.time.'.$counter.'@example.test',
        'password' => Hash::make('secret-password'),
    ]));
}

function phTimeInventory(array $overrides = []): Inventory
{
    return Inventory::create(array_merge([
        'name' => 'Pine board',
        'category' => 'Lumber',
        'qty' => 10,
        'unit' => 'piece',
        'unit_cost' => 5,
        'selling_price' => 20,
        'reorder_level' => 2,
    ], $overrides));
}

function checkOutAt($test, CarbonImmutable $instant, Inventory $inventory, string $expectedDisplay): PosTransaction
{
    $test->travelTo($instant);

    Livewire::test(PointOfSale::class)
        ->call('addToCart', $inventory->id)
        ->set('amountReceived', '22.40')
        ->call('checkout')
        ->assertHasNoErrors()
        ->assertSee($expectedDisplay)
        ->assertSee('PHT');

    return PosTransaction::query()->latest('id')->firstOrFail();
}

function phTimeAssertReport($component, string $label, int $count): void
{
    $html = preg_replace('/\s+/', ' ', $component->html());

    expect($html)->toContain("Actual completed POS transactions · {$label} · {$count} transactions");
}

test('a POS sale after Manila midnight is numbered, stored, displayed and posted on the Philippine date', function () {
    $this->actingAs(phTimeEmployee());
    $this->travelTo(CarbonImmutable::parse('2026-09-30 16:30:00', 'UTC'));
    $inventory = phTimeInventory();
    setupPosAccountingBooks([$inventory]);

    $sale = checkOutAt($this, CarbonImmutable::parse('2026-09-30 16:30:00', 'UTC'), $inventory, '2026-10-01 00:30 PHT');

    expect($sale->transaction_number)->toStartWith('POS-20261001-')
        ->and($sale->completed_at->utc()->format('Y-m-d H:i:s'))->toBe('2026-09-30 16:30:00')
        ->and(PhilippineTime::format($sale->completed_at))->toBe('2026-10-01 00:30');

    $vatRecord = $sale->vatRecord()->firstOrFail();
    expect($vatRecord->completed_at->utc()->format('Y-m-d H:i:s'))->toBe('2026-09-30 16:30:00')
        ->and(PhilippineTime::format($vatRecord->completed_at))->toBe('2026-10-01 00:30');

    $journal = AccountingJournal::query()->where('source_type', 'pos_sale')->where('source_id', (string) $sale->id)->firstOrFail();
    expect($journal->accounting_date->toDateString())->toBe('2026-10-01')
        ->and($journal->status)->toBe('posted')
        ->and($journal->posted_at->utc()->format('Y-m-d H:i:s'))->toBe('2026-09-30 16:30:00');

    $movement = StockMovement::query()->where('accounting_journal_id', $journal->id)->firstOrFail();
    expect($movement->effective_date->toDateString())->toBe('2026-10-01')
        ->and($movement->posted_at->utc()->format('Y-m-d H:i:s'))->toBe('2026-09-30 16:30:00');

    $reports = Livewire::test(ReportsPage::class)->set('reportDate', '2026-10-01');
    phTimeAssertReport($reports, 'October 1, 2026', 1);
    $reports->set('reportDate', '2026-09-30');
    phTimeAssertReport($reports, 'September 30, 2026', 0);

    Livewire::test(VatRecords::class)
        ->set('startDate', '2026-10-01')
        ->set('endDate', '2026-10-01')
        ->assertSee($sale->transaction_number);
    Livewire::test(VatRecords::class)
        ->set('startDate', '2026-09-30')
        ->set('endDate', '2026-09-30')
        ->assertDontSee($sale->transaction_number);
});

test('the Manila day boundary is half-open across reports, VAT records and accounting dates', function () {
    $this->actingAs(phTimeEmployee());
    $this->travelTo(CarbonImmutable::parse('2026-10-31 15:59:59', 'UTC'));
    $inventory = phTimeInventory();
    setupPosAccountingBooks([$inventory]);

    $octoberSale = checkOutAt($this, CarbonImmutable::parse('2026-10-31 15:59:59', 'UTC'), $inventory, '2026-10-31 23:59 PHT');
    $novemberSale = checkOutAt($this, CarbonImmutable::parse('2026-10-31 16:00:00', 'UTC'), $inventory, '2026-11-01 00:00 PHT');

    expect($octoberSale->transaction_number)->toStartWith('POS-20261031-')
        ->and($novemberSale->transaction_number)->toStartWith('POS-20261101-');

    $octoberJournal = AccountingJournal::query()->where('source_type', 'pos_sale')
        ->where('source_id', (string) $octoberSale->id)->firstOrFail();
    $novemberJournal = AccountingJournal::query()->where('source_type', 'pos_sale')
        ->where('source_id', (string) $novemberSale->id)->firstOrFail();

    expect($octoberJournal->accounting_date->toDateString())->toBe('2026-10-31')
        ->and($novemberJournal->accounting_date->toDateString())->toBe('2026-11-01');

    $reports = Livewire::test(ReportsPage::class)->set('reportDate', '2026-10-31');
    phTimeAssertReport($reports, 'October 31, 2026', 1);
    $reports->set('reportDate', '2026-11-01');
    phTimeAssertReport($reports, 'November 1, 2026', 1);
    $reports->set('reportPeriod', 'monthly')->set('reportDate', '2026-10-15');
    phTimeAssertReport($reports, 'October 2026', 1);
    $reports->set('reportDate', '2026-11-15');
    phTimeAssertReport($reports, 'November 2026', 1);

    Livewire::test(VatRecords::class)
        ->set('startDate', '2026-10-01')
        ->set('endDate', '2026-10-31')
        ->assertSee($octoberSale->transaction_number)
        ->assertDontSee($novemberSale->transaction_number);
    Livewire::test(VatRecords::class)
        ->set('startDate', '2026-11-01')
        ->set('endDate', '2026-11-30')
        ->assertSee($novemberSale->transaction_number)
        ->assertDontSee($octoberSale->transaction_number);
});

test('business dates stay date-only while their posted timestamps are shown in Manila', function () {
    $this->actingAs(phTimeEmployee());
    $this->travelTo(CarbonImmutable::parse('2026-09-30 16:30:00', 'UTC'));

    $inventory = phTimeInventory();
    $openingMovement = StockMovement::query()->where('inventory_id', $inventory->id)->firstOrFail();
    expect($openingMovement->effective_date->toDateString())->toBe('2026-10-01');

    $expense = OperatingCostRecord::create([
        'employee_id' => auth()->id(),
        'expense_date' => '2026-10-01',
        'reference_number' => 'OPEX-TZ-1',
        'expense_category' => 'Rent Expense',
        'description' => 'Timezone regression record',
        'amount' => '100.00',
        'payment_method' => 'cash',
    ]);
    expect($expense->fresh()->expense_date->toDateString())->toBe('2026-10-01');

    $period = AccountingPostingPeriod::firstOrCreate(
        ['book_key' => 'FCDC', 'fiscal_year' => 2026],
        ['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open'],
    );

    $journal = AccountingJournal::create([
        'book_key' => 'FCDC',
        'reference' => 'TZ-DATE-ONLY',
        'source_type' => 'manual',
        'source_id' => 'TZ-DATE-ONLY',
        'accounting_date' => '2026-10-01',
        'posting_period_id' => $period->id,
        'description' => 'Date-only preservation check',
        'status' => 'draft',
        'prepared_by' => auth()->id(),
    ]);
    expect($journal->fresh()->accounting_date->toDateString())->toBe('2026-10-01');

    $movement = StockMovement::create([
        'inventory_id' => $inventory->id,
        'posted_by' => auth()->id(),
        'type' => 'adjustment',
        'quantity' => '0',
        'reason_category' => 'correction',
        'effective_date' => '2026-10-01',
        'posted_at' => '2026-09-30 16:30:00',
    ]);
    expect($movement->fresh()->effective_date->toDateString())->toBe('2026-10-01')
        ->and(PhilippineTime::format($movement->fresh()->posted_at))->toBe('2026-10-01 00:30');

    expect(PhilippineTime::at(null))->toBeNull()
        ->and(PhilippineTime::format(null))->toBeNull()
        ->and(PhilippineTime::iso($movement->fresh()->posted_at))->toBe('2026-10-01T00:30:00+08:00');
});

test('default business dates and activity log timestamps follow Manila time after midnight', function () {
    $employee = phTimeEmployee();
    $this->actingAs($employee);
    $this->travelTo(CarbonImmutable::parse('2026-09-30 16:30:00', 'UTC'));

    Livewire::test(OperatingCostRecordManagement::class)
        ->assertSet('batchDate', '2026-10-01')
        ->assertSet('expenseDate', '2026-10-01');

    Livewire::test(ReworkingCostRecordManagement::class)
        ->assertSet('recordDate', '2026-10-01');

    $this->postJson(route('inventory.store'), [
        'name' => 'Timezone gravel',
        'category' => 'Aggregate',
        'qty' => 12,
        'unit' => 'bag',
        'unit_cost' => 8.5,
        'selling_price' => 10,
        'reorder_level' => 3,
    ])->assertCreated();

    $activity = Activity::query()->latest('id')->firstOrFail();
    expect($activity->created_at->utc()->format('Y-m-d H:i:s'))->toBe('2026-09-30 16:30:00');

    Livewire::test(ActivityLogPage::class)
        ->assertSee('Oct 01, 2026')
        ->assertSee('00:30:00 PHT');
});
