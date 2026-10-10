<?php

use App\Livewire\Accounting\GeneralLedger;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use App\Models\PosTransaction;
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

function createGeneralLedgerEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'ledger.viewer',
        'email' => 'ledger@example.com',
        'password' => Hash::make('ledger-password'),
    ]), ['accounting.view']);
}

function createLedgerAccount(string $code, string $normalBalance = 'debit', bool $active = true): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => "Account {$code}",
        'type' => $normalBalance === 'debit' ? 'Asset' : 'Liability',
        'classification' => $normalBalance === 'debit' ? 'cash' : 'other_liability',
        'normal_balance' => $normalBalance,
        'is_active' => $active,
        'approved_at' => now(),
    ]);
}

function postLedgerJournal(string $reference, string $source, string $date, string $description, array $lines, ?int $correctionOfId = null, ?string $sourceId = null): AccountingJournal
{
    $period = AccountingPostingPeriod::firstOrCreate(
        ['book_key' => 'FCDC', 'fiscal_year' => (int) substr($date, 0, 4)],
        ['starts_on' => substr($date, 0, 4).'-01-01', 'ends_on' => substr($date, 0, 4).'-12-31', 'status' => 'open'],
    );
    $actor = Employee::query()->first() ?? createGeneralLedgerEmployee();
    $journal = AccountingJournal::create([
        'book_key' => 'FCDC',
        'reference' => $reference,
        'source_type' => $source,
        'source_id' => $sourceId ?? ($source === 'opening' ? 'FCDC' : $reference),
        'accounting_date' => $date,
        'posting_period_id' => $period->id,
        'description' => $description,
        'correction_of_id' => $correctionOfId,
        'status' => 'draft',
        'prepared_by' => $actor->id,
        'posted_by' => $actor->id,
        'posted_at' => now(),
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

test('general ledger shows supported posted account activity with opening and deterministic running balances', function () {
    $viewer = createGeneralLedgerEmployee();
    $cash = createLedgerAccount('1000');
    $equity = createLedgerAccount('3000', 'credit');
    $inactive = createLedgerAccount('1010', 'debit', false);
    $contra = createLedgerAccount('1090', 'credit');
    $contraOffset = createLedgerAccount('3090');
    postLedgerJournal('OPENING-2026', 'opening', '2026-01-01', 'Opening balances', [[$cash, 100000, 0], [$equity, 0, 100000], [$contra, 0, 5000], [$contraOffset, 5000, 0]]);
    $first = postLedgerJournal('MANUAL-001', 'manual', '2026-02-01', 'Cash received', [[$cash, 25000, 0], [$equity, 0, 25000]]);
    postLedgerJournal('MANUAL-001B', 'manual', '2026-02-01', 'Additional cash received', [[$cash, 5000, 0], [$equity, 0, 5000]]);
    postLedgerJournal('CONTRA-001', 'manual', '2026-02-15', 'Contra balance activity', [[$contra, 7500, 0], [$contraOffset, 0, 7500]]);
    $second = postLedgerJournal('MANUAL-002', 'reversal', '2026-02-28', 'Cash returned', [[$cash, 0, 10000], [$equity, 10000, 0]], $first->id);
    postLedgerJournal('MANUAL-003', 'manual', '2026-03-01', 'Outside selected period', [[$cash, 90000, 0], [$equity, 0, 90000]]);
    postLedgerJournal('INACTIVE-001', 'manual', '2026-02-15', 'Inactive account activity', [[$inactive, 5000, 0], [$equity, 0, 5000]]);

    $this->actingAs($viewer)->get(route('accounting.general-ledger'))
        ->assertOk()->assertSee('Select an approved account')->assertDontSee('General Ledger reporting is not available yet.');

    Livewire::actingAs($viewer)->test(GeneralLedger::class)
        ->set('accountId', (string) $cash->id)
        ->set('fromDate', '2026-02-01')
        ->set('toDate', '2026-02-28')
        ->assertSeeInOrder(['MANUAL-001', '1,250.00', 'MANUAL-001B', '1,300.00', 'MANUAL-002', '1,200.00'])
        ->assertSee('Cash received')
        ->assertSee('Correction of MANUAL-001')
        ->assertDontSee('MANUAL-003')
        ->assertSee(route('accounting.journal-entry').'?journal='.$first->id, false)
        ->assertSee(route('accounting.journal-entry').'?journal='.$second->id, false);
    $this->actingAs($viewer)->get(route('accounting.journal-entry', ['journal' => $first->id]))
        ->assertOk()->assertSee('Journal detail: MANUAL-001');

    Livewire::actingAs($viewer)->test(GeneralLedger::class)
        ->set('accountId', (string) $cash->id)->set('fromDate', '2026-01-15')->set('toDate', '2026-01-31')
        ->assertSee('1,000.00')->assertSee('No posted activity in this period');

    Livewire::actingAs($viewer)->test(GeneralLedger::class)
        ->set('accountId', (string) $inactive->id)->set('fromDate', '2026-02-01')->set('toDate', '2026-02-28')
        ->assertSee('Inactive')->assertSee('50.00');

    Livewire::actingAs($viewer)->test(GeneralLedger::class)
        ->set('accountId', (string) $contra->id)->set('fromDate', '2026-02-01')->set('toDate', '2026-02-28')
        ->assertSee('50.00 Credit')->assertSee('25.00 Debit');
});

test('general ledger links existing operational sources to their detail pages', function () {
    $viewer = grantEmployeeTestPermissions(Employee::create([
        'username' => 'ledger.pos.viewer', 'email' => 'ledger.pos@example.com', 'password' => Hash::make('password'),
    ]), ['accounting.view', 'pos.view', 'inventory.view']);
    $cash = createLedgerAccount('1000');
    $equity = createLedgerAccount('3000', 'credit');
    postLedgerJournal('OPENING-SOURCE', 'opening', '2026-01-01', 'Opening balances', [[$cash, 10000, 0], [$equity, 0, 10000]]);
    $transaction = PosTransaction::create([
        'transaction_number' => 'TX-GL-001',
        'subtotal' => '100.00',
        'vat_rate' => '0.1200',
        'vat_amount' => '12.00',
        'total' => '112.00',
        'payment_method' => 'cash',
        'amount_received' => '112.00',
        'change_due' => '0.00',
        'status' => 'completed',
        'completed_at' => '2026-02-15 08:00:00',
    ]);
    postLedgerJournal('POS-GL-001', 'pos_sale', '2026-02-15', 'Completed sale', [[$cash, 11200, 0], [$equity, 0, 11200]], null, (string) $transaction->id);
    $item = \App\Models\Inventory::create([
        'name' => 'Corrected GL stock', 'category' => 'Other', 'qty' => '0.00',
        'unit' => 'piece', 'unit_cost' => '0.00', 'reorder_level' => '0',
    ]);
    $movement = \App\Models\StockMovement::create([
        'inventory_id' => $item->id, 'posted_by' => $viewer->id, 'type' => 'valuation_correction',
        'quantity' => '0.00', 'reason_category' => 'valuation_correction', 'reference' => 'STK-CORR-1',
        'effective_date' => '2026-02-15', 'posted_at' => now(), 'value_cents' => -100,
        'carrying_value_after_cents' => 0,
    ]);
    postLedgerJournal('STK-CORR-1', 'stock_valuation_correction', '2026-02-15', 'Stock value correction', [[$cash, 100, 0], [$equity, 0, 100]], null, (string) $movement->id);
    postLedgerJournal('REC-CORR-1', 'recovered_material_correction', '2026-02-15', 'Recovery reassessment', [[$cash, 100, 0], [$equity, 0, 100]], null, (string) $movement->id);


    Livewire::actingAs($viewer)->test(GeneralLedger::class)
        ->set('accountId', (string) $cash->id)->set('fromDate', '2026-02-01')->set('toDate', '2026-02-28')
        ->assertSee(route('pos', ['receipt' => $transaction->id]), false)
        ->assertSee(route('inventory.management', ['item' => $item->id]), false);
    $this->actingAs($viewer)->get(route('inventory.management', ['item' => $item->id]))
        ->assertOk()->assertSee('Corrected GL stock');
    $this->actingAs($viewer)->get(route('pos', ['receipt' => $transaction->id]))
        ->assertOk()->assertSee('TX-GL-001');
});

test('general ledger requires accounting view permission and rejects unsupported pre-cutover ranges', function () {
    $this->actingAs(grantEmployeeTestPermissions(Employee::create([
        'username' => 'ledger.denied', 'email' => 'ledger.denied@example.com', 'password' => Hash::make('password'),
    ]), []))->get(route('accounting.general-ledger'))->assertForbidden();

    $viewer = createGeneralLedgerEmployee();
    $cash = createLedgerAccount('1000');
    $equity = createLedgerAccount('3000', 'credit');
    postLedgerJournal('OPENING-COVERAGE', 'opening', '2026-01-01', 'Opening balances', [[$cash, 100, 0], [$equity, 0, 100]]);
    Livewire::actingAs($viewer)->test(GeneralLedger::class)
        ->set('accountId', (string) $cash->id)->set('fromDate', '2025-12-31')->set('toDate', '2026-01-31')
        ->assertSee('before approved accounting cutover coverage');
});
