<?php

use App\Livewire\Accounting\SalesInventoryProfitability;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\PosTransaction;
use App\Models\PosTransactionLine;
use App\Models\ReworkingCostRecord;
use App\Services\Accounting\SalesInventoryProfitabilityReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function profitabilityEmployee(array $permissions = ['accounting.view']): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'profitability.'.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => Hash::make('secret-password'),
    ]), $permissions);
}

function profitabilityInventory(string $name, int $carryingValueCents = 0): Inventory
{
    $inventory = Inventory::create([
        'name' => $name,
        'category' => 'Recovered Materials',
        'qty' => '10.00',
        'unit' => 'piece',
        'unit_cost' => '50.00',
        'selling_price' => '100.00',
        'reorder_level' => '0',
    ]);

    $inventory->forceFill(['carrying_value_cents' => $carryingValueCents])->saveQuietly();

    return $inventory;
}

function profitabilitySale(Employee $employee, string $completedAtManila, array $lines): PosTransaction
{
    $subtotalCents = 0;
    $vatCents = 0;

    foreach ($lines as $line) {
        $subtotalCents += (int) round((float) $line['line_subtotal'] * 100);
        $vatCents += (int) round((float) $line['vat_amount'] * 100);
    }

    $transaction = PosTransaction::create([
        'transaction_number' => 'POS-TEST-'.strtoupper(uniqid()),
        'employee_id' => $employee->id,
        'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
        'vat_amount' => number_format($vatCents / 100, 2, '.', ''),
        'total' => number_format(($subtotalCents + $vatCents) / 100, 2, '.', ''),
        'payment_method' => 'cash',
        'amount_received' => number_format(($subtotalCents + $vatCents) / 100, 2, '.', ''),
        'change_due' => '0.00',
        'completed_at' => CarbonImmutable::parse($completedAtManila, 'Asia/Manila')->utc(),
    ]);

    foreach ($lines as $line) {
        PosTransactionLine::create([
            'pos_transaction_id' => $transaction->id,
            'inventory_id' => $line['inventory']?->id,
            'inventory_code' => $line['inventory_code'],
            'item_name' => $line['item_name'],
            'category' => $line['category'] ?? 'Recovered Materials',
            'unit' => $line['unit'] ?? 'piece',
            'quantity' => $line['quantity'],
            'selling_price' => $line['selling_price'],
            'unit_cost' => $line['unit_cost'],
            'line_cost_cents' => $line['line_cost_cents'],
            'line_subtotal' => $line['line_subtotal'],
            'vat_amount' => $line['vat_amount'],
            'line_total' => $line['line_total'],
        ]);
    }

    return $transaction;
}

function profitabilityReworking(Employee $employee, int $sourceId, string $date, string $cost, string $inputVat): ReworkingCostRecord
{
    return ReworkingCostRecord::create([
        'employee_id' => $employee->id,
        'source_record_id' => $sourceId,
        'project_name' => 'Recovered Materials Project',
        'batch_number' => 'BATCH-'.$sourceId,
        'record_date' => $date,
        'description' => 'Cleaning and preparing recovered materials',
        'total_reworking_cost' => $cost,
        'input_vat' => $inputVat,
    ]);
}

function profitabilityAccountingMenuLinks(string $html): array
{
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query(
        '//*[@id="accounting-menu"]//a[contains(concat(" ", normalize-space(@class), " "), " nav-child ")]',
    );

    $links = [];
    foreach ($nodes as $node) {
        $label = $xpath->query(
            './/span[contains(concat(" ", normalize-space(@class), " "), " nav-label ")]',
            $node,
        )->item(0);

        $links[] = [
            'label' => trim($label?->textContent ?? ''),
            'href' => $node->getAttribute('href'),
            'has_icon' => $xpath->query(
                './/span[contains(concat(" ", normalize-space(@class), " "), " nav-icon ")]',
                $node,
            )->length > 0,
        ];
    }

    return $links;
}

test('profitability report uses completed POS sales, frozen inventory cost, and persisted reworking costs', function () {
    $employee = profitabilityEmployee();
    $inventory = profitabilityInventory('Recovered Lumber', 77700);

    profitabilitySale($employee, '2026-10-05 10:00:00', [
        [
            'inventory' => $inventory,
            'inventory_code' => $inventory->code,
            'item_name' => $inventory->name,
            'quantity' => '2.00',
            'selling_price' => '100.00',
            'unit_cost' => '60.00',
            'line_cost_cents' => 12000,
            'line_subtotal' => '200.00',
            'vat_amount' => '24.00',
            'line_total' => '224.00',
        ],
        [
            'inventory' => null,
            'inventory_code' => 'INV-OTHER',
            'item_name' => 'Recovered Board',
            'quantity' => '1.00',
            'selling_price' => '50.00',
            'unit_cost' => '30.00',
            'line_cost_cents' => 3000,
            'line_subtotal' => '50.00',
            'vat_amount' => '6.00',
            'line_total' => '56.00',
        ],
    ]);

    profitabilitySale($employee, '2026-10-15 14:30:00', [
        [
            'inventory' => $inventory,
            'inventory_code' => $inventory->code,
            'item_name' => $inventory->name,
            'quantity' => '1.00',
            'selling_price' => '100.00',
            'unit_cost' => '40.00',
            'line_cost_cents' => 4000,
            'line_subtotal' => '100.00',
            'vat_amount' => '12.00',
            'line_total' => '112.00',
        ],
    ]);

    // Outside the selected period: must not affect the report.
    profitabilitySale($employee, '2026-09-30 09:00:00', [
        [
            'inventory' => null,
            'inventory_code' => 'INV-OLD',
            'item_name' => 'Out of range item',
            'quantity' => '1.00',
            'selling_price' => '9999.00',
            'unit_cost' => '9999.00',
            'line_cost_cents' => 999900,
            'line_subtotal' => '9999.00',
            'vat_amount' => '1199.88',
            'line_total' => '11198.88',
        ],
    ]);

    profitabilityReworking($employee, 1, '2026-10-10', '50.00', '6.00');
    profitabilityReworking($employee, 2, '2026-10-20', '25.00', '3.00');
    profitabilityReworking($employee, 3, '2026-09-30', '9999.00', '1199.88');

    $report = app(SalesInventoryProfitabilityReport::class)->build('2026-10-01', '2026-10-31');

    expect($report['available'])->toBeTrue()
        ->and($report['sale_count'])->toBe(2)
        ->and($report['sales_revenue_cents'])->toBe(35000)
        ->and($report['output_vat_cents'])->toBe(4200)
        ->and($report['total_collected_cents'])->toBe(39200)
        ->and($report['cogs_cents'])->toBe(19000)
        ->and($report['gross_profit_cents'])->toBe(16000)
        ->and($report['gross_margin_percent'])->toBe(45.71)
        ->and($report['reworking_record_count'])->toBe(2)
        ->and($report['reworking_cost_cents'])->toBe(7500)
        ->and($report['reworking_input_vat_cents'])->toBe(900)
        ->and($report['profit_after_reworking_cents'])->toBe(8500)
        ->and($report['profit_margin_percent'])->toBe(24.29)
        ->and($report['current_inventory_cost_cents'])->toBe(77700)
        ->and($report['total_period_cost_cents'])->toBe(26500)
        ->and($report['fallback_cost_line_count'])->toBe(0)
        ->and($report['sale_rows'])->toHaveCount(2)
        ->and($report['item_rows'])->toHaveCount(2);
});

test('reworking input VAT is excluded and reworking cost is deducted exactly once', function () {
    $employee = profitabilityEmployee();

    profitabilitySale($employee, '2026-10-05 10:00:00', [
        [
            'inventory' => null,
            'inventory_code' => 'INV-001',
            'item_name' => 'Recovered item',
            'quantity' => '1.00',
            'selling_price' => '100.00',
            'unit_cost' => '60.00',
            'line_cost_cents' => 6000,
            'line_subtotal' => '100.00',
            'vat_amount' => '12.00',
            'line_total' => '112.00',
        ],
    ]);

    profitabilityReworking($employee, 1, '2026-10-10', '40.00', '4.80');

    $report = app(SalesInventoryProfitabilityReport::class)->build('2026-10-01', '2026-10-31');

    expect($report['sales_revenue_cents'])->toBe(10000)
        ->and($report['cogs_cents'])->toBe(6000)
        ->and($report['gross_profit_cents'])->toBe(4000)
        ->and($report['reworking_cost_cents'])->toBe(4000)
        ->and($report['reworking_input_vat_cents'])->toBe(480)
        ->and($report['profit_after_reworking_cents'])->toBe(0)
        ->and($report['total_period_cost_cents'])->toBe(10000)
        // The report must not add input VAT to reworking cost or count the record twice.
        ->and($report['reworking_cost_cents'])->not->toBe(4480)
        ->and($report['total_period_cost_cents'])->not->toBe(10480);
});

test('sold lines without a frozen cost snapshot fall back to saved unit cost times quantity', function () {
    $employee = profitabilityEmployee();

    profitabilitySale($employee, '2026-10-05 10:00:00', [
        [
            'inventory' => null,
            'inventory_code' => 'INV-LEGACY',
            'item_name' => 'Legacy line',
            'quantity' => '2.00',
            'selling_price' => '30.00',
            'unit_cost' => '10.00',
            'line_cost_cents' => null,
            'line_subtotal' => '60.00',
            'vat_amount' => '7.20',
            'line_total' => '67.20',
        ],
    ]);

    $report = app(SalesInventoryProfitabilityReport::class)->build('2026-10-01', '2026-10-31');

    expect($report['cogs_cents'])->toBe(2000)
        ->and($report['fallback_cost_line_count'])->toBe(1)
        ->and($report['gross_profit_cents'])->toBe(4000);
});

test('profitability report renders actual figures, print control, and the no-double-count basis', function () {
    $employee = profitabilityEmployee();
    $inventory = profitabilityInventory('Recovered Lumber', 77700);

    profitabilitySale($employee, '2026-10-05 10:00:00', [
        [
            'inventory' => $inventory,
            'inventory_code' => $inventory->code,
            'item_name' => $inventory->name,
            'quantity' => '1.00',
            'selling_price' => '100.00',
            'unit_cost' => '60.00',
            'line_cost_cents' => 6000,
            'line_subtotal' => '100.00',
            'vat_amount' => '12.00',
            'line_total' => '112.00',
        ],
    ]);
    profitabilityReworking($employee, 1, '2026-10-10', '25.00', '3.00');

    $this->actingAs($employee);

    Livewire::test(SalesInventoryProfitability::class)
        ->set('fromDate', '2026-10-01')
        ->set('toDate', '2026-10-31')
        ->assertSee('Sales and Inventory Profitability Report')
        ->assertSee('Print report')
        ->assertSee('₱100.00')
        ->assertSee('₱60.00')
        ->assertSee('₱40.00')
        ->assertSee('₱25.00')
        ->assertSee('₱15.00')
        ->assertSee('₱777.00')
        ->assertSee('Input VAT is excluded')
        ->assertSee('not added again to COGS or inventory cost')
        ->assertDontSee('9,999.00');
});

test('profitability report route is protected by authentication and accounting.view', function () {
    $this->get(route('accounting.sales-inventory-profitability'))->assertRedirect(route('login'));

    $this->actingAs(profitabilityEmployee())
        ->get(route('accounting.sales-inventory-profitability'))
        ->assertOk()
        ->assertSee('Sales and Inventory Profitability Report')
        ->assertSee('Print report');

    $this->actingAs(profitabilityEmployee([]))
        ->get(route('accounting.sales-inventory-profitability'))
        ->assertForbidden();
});

test('profitability report rejects invalid or reversed periods', function () {
    $service = app(SalesInventoryProfitabilityReport::class);

    expect($service->build('2026-10-31', '2026-10-01')['available'])->toBeFalse()
        ->and($service->build('2026-10-31', '2026-10-01')['error'])->toContain('start date')
        ->and($service->build('not-a-date', '2026-10-31')['available'])->toBeFalse();
});

test('accounting sidebar shows exactly the five requested links in order with icons', function () {
    $response = $this->actingAs(profitabilityEmployee())->get(route('accounting.chart-of-accounts'));
    $response->assertOk();

    $links = profitabilityAccountingMenuLinks($response->getContent());

    expect(array_column($links, 'label'))->toBe([
        'Chart of Accounts',
        'Journal Entry',
        'General Ledger',
        'Trial Balance',
        'Sales and Inventory Profitability Report',
    ])->and(array_column($links, 'href'))->toBe([
        route('accounting.chart-of-accounts'),
        route('accounting.journal-entry'),
        route('accounting.general-ledger'),
        route('accounting.trial-balance'),
        route('accounting.sales-inventory-profitability'),
    ])->and(array_filter($links, fn (array $link): bool => ! $link['has_icon']))->toBe([])
        ->and(array_column($links, 'label'))->not->toContain('Financial Statements')
        ->and(array_column($links, 'label'))->not->toContain('Notes to Financial Statements / Disclosure');
});
