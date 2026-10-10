<?php

use App\Livewire\Accounting\CashDisbursements;
use App\Livewire\Accounting\ChartOfAccounts;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingJournalLine;
use App\Models\AccountingPostingMapping;
use App\Models\AccountingPostingPeriod;
use App\Models\CashDisbursement;
use App\Models\Employee;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Services\Accounting\CashDisbursementService;
use App\Services\Accounting\OpeningBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function disbursementEmployee(array $permissions = ['accounting.view', 'accounting.prepare-disbursements', 'accounting.post-disbursements']): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'cash.'.fake()->unique()->numerify('####'),
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('cash-password'),
    ]), $permissions);
}

function approvedDisbursementAccount(string $code, string $name, string $type, string $classification): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => $name,
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => 'debit',
        'is_active' => true,
        'approved_at' => now(),
        'approved_by' => disbursementEmployee(['accounting.view'])->id,
    ]);
}

function prepareDisbursementBooks(): array
{
    $cash = approvedDisbursementAccount('1000', 'Operating Cash', 'Asset', 'cash');
    $bank = approvedDisbursementAccount('1010', 'Operating Bank', 'Asset', 'bank');
    $expense = approvedDisbursementAccount('6200', 'Utilities Expense', 'Expense', 'operating_expense');
    $period = AccountingPostingPeriod::create([
        'book_key' => 'FCDC', 'fiscal_year' => now('Asia/Manila')->year,
        'starts_on' => now('Asia/Manila')->startOfYear()->toDateString(),
        'ends_on' => now('Asia/Manila')->endOfYear()->toDateString(), 'status' => 'open',
    ]);
    AccountingJournal::create([
        'book_key' => 'FCDC', 'reference' => 'OPEN-DISBURSEMENT-BOOKS', 'source_type' => 'opening',
        'source_id' => 'FCDC', 'accounting_date' => $period->starts_on, 'posting_period_id' => $period->id,
        'description' => 'Approved cutover', 'status' => 'posted',
        'prepared_by' => disbursementEmployee(['accounting.view'])->id,
        'posted_by' => disbursementEmployee(['accounting.view'])->id, 'posted_at' => now(),
    ]);

    return [$cash, $bank, $expense, $period];
}

function disbursementInput(AccountingAccount $account, AccountingAccount $debit, array $overrides = []): array
{
    return array_replace([
        'payee' => 'City Water Services', 'paymentDate' => now('Asia/Manila')->toDateString(),
        'method' => 'Bank Transfer', 'moneyAccountId' => (string) $account->id,
        'reference' => 'WT-2026-0042', 'checkNumber' => '', 'description' => 'Monthly utility service',
        'evidenceReference' => 'Invoice UW-9942', 'amount' => '2000.00',
        'allocations' => [['accounting_account_id' => (string) $debit->id, 'description' => 'Water service', 'amount' => '2000.00']],
    ], $overrides);
}

test('a PHP 2,000 direct utility disbursement remains effect-free as a draft and posts to the selected bank account', function () {
    [$cash, $bank, $utilities] = prepareDisbursementBooks();
    $this->actingAs(disbursementEmployee());

    $component = Livewire::test(CashDisbursements::class);
    foreach (disbursementInput($bank, $utilities) as $field => $value) {
        $component->set($field, $value);
    }
    $component->call('saveDraft')->assertHasNoErrors()->assertSee('draft');

    $disbursement = CashDisbursement::query()->firstOrFail();
    expect($disbursement->status)->toBe('draft')
        ->and(AccountingJournal::where('source_type', 'cash_disbursement')->count())->toBe(0)
        ->and($disbursement->amount_cents)->toBe(200000);

    Livewire::test(CashDisbursements::class)->call('postDisbursement', $disbursement->id)->assertHasNoErrors();
    $disbursement->refresh();
    $journal = AccountingJournal::where('source_type', 'cash_disbursement')->firstOrFail();
    expect($disbursement->status)->toBe('posted')
        ->and($journal->lines()->where('accounting_account_id', $utilities->id)->value('debit_cents'))->toBe(200000)
        ->and($journal->lines()->where('accounting_account_id', $bank->id)->value('credit_cents'))->toBe(200000)
        ->and($journal->lines()->where('accounting_account_id', $cash->id)->exists())->toBeFalse();
    Livewire::test(CashDisbursements::class)
        ->set('search', 'WT-2026-0042')
        ->set('status', 'Posted')
        ->set('methodFilter', 'Bank Transfer')
        ->call('showDisbursement', $disbursement->id)
        ->assertSee('City Water Services')
        ->assertSee('Invoice UW-9942')
        ->assertSee('CD-'.$disbursement->id);

});

test('direct payment drafts can be edited and deleted without creating a journal', function () {
    [, $bank, $utilities] = prepareDisbursementBooks();
    $this->actingAs(disbursementEmployee());
    $component = Livewire::test(CashDisbursements::class);
    foreach (disbursementInput($bank, $utilities) as $field => $value) {
        $component->set($field, $value);
    }
    $component->call('saveDraft')->assertHasNoErrors();
    $draft = CashDisbursement::firstOrFail();

    Livewire::test(CashDisbursements::class)
        ->call('editDraft', $draft->id)
        ->assertSet('amount', '2000.00')
        ->set('amount', '2500.00')
        ->set('allocations.0.amount', '2500.00')
        ->call('saveDraft')
        ->assertHasNoErrors();
    expect($draft->fresh()->amount_cents)->toBe(250000)
        ->and($draft->fresh()->status)->toBe('draft')
        ->and(AccountingJournal::where('source_type', 'cash_disbursement')->exists())->toBeFalse();

    Livewire::test(CashDisbursements::class)->call('deleteDraft', $draft->id)->assertHasNoErrors();
    expect(CashDisbursement::find($draft->id))->toBeNull()
        ->and(AccountingJournal::where('source_type', 'cash_disbursement')->exists())->toBeFalse();
});

test('a released check reversal is reasoned, linked, and leaves the original disbursement and journal intact', function () {
    [, $bank, $utilities] = prepareDisbursementBooks();
    $this->actingAs(disbursementEmployee());
    $input = disbursementInput($bank, $utilities, [
        'method' => 'Check', 'reference' => 'CHECK-REV-5', 'checkNumber' => '005',
    ]);
    $component = Livewire::test(CashDisbursements::class);
    foreach ($input as $field => $value) {
        $component->set($field, $value);
    }
    $component->call('saveDraft')->assertHasNoErrors();
    $disbursement = CashDisbursement::firstOrFail();
    Livewire::test(CashDisbursements::class)->call('postDisbursement', $disbursement->id)->assertHasNoErrors();

    Livewire::test(CashDisbursements::class)
        ->set('reversalReason', 'Returned by payee; bank confirmed void')
        ->call('reverseDisbursement', $disbursement->id)
        ->assertHasNoErrors();

    $reversal = $disbursement->fresh()->reversals()->firstOrFail();
    expect($disbursement->fresh()->status)->toBe('posted')
        ->and($reversal->reversal_of_id)->toBe($disbursement->id)
        ->and($reversal->correction_reason)->toBe('Returned by payee; bank confirmed void')
        ->and(AccountingJournal::where('source_type', 'cash_disbursement_reversal')->count())->toBe(1);
});

test('allocation inequality, missing evidence and references, and unauthorized actions are rejected', function () {
    [, $bank, $utilities] = prepareDisbursementBooks();
    $preparer = disbursementEmployee(['accounting.view', 'accounting.prepare-disbursements']);
    $this->actingAs($preparer);
    $component = Livewire::test(CashDisbursements::class);
    foreach (disbursementInput($bank, $utilities, [
        'allocations' => [['accounting_account_id' => (string) $utilities->id, 'description' => 'Water service', 'amount' => '1999.99']],
    ]) as $field => $value) {
        $component->set($field, $value);
    }
    $component->call('saveDraft')->assertHasErrors();
    $validDraft = Livewire::test(CashDisbursements::class);
    foreach (disbursementInput($bank, $utilities) as $field => $value) {
        $validDraft->set($field, $value);
    }
    $validDraft->call('saveDraft')->assertHasNoErrors();
    $draftId = CashDisbursement::firstOrFail()->id;
    $this->actingAs(disbursementEmployee(['accounting.view']));
    Livewire::test(CashDisbursements::class)->call('deleteDraft', 1)->assertForbidden();

    Livewire::test(CashDisbursements::class)->call('postDisbursement', $draftId)->assertForbidden();
    $this->actingAs($preparer);
    $component = Livewire::test(CashDisbursements::class);
    foreach (disbursementInput($bank, $utilities, ['evidenceReference' => '', 'reference' => '']) as $field => $value) {
        $component->set($field, $value);
    }
    $component->call('saveDraft')->assertHasErrors();
    $checkDraft = Livewire::test(CashDisbursements::class);
    foreach (disbursementInput($bank, $utilities, ['method' => 'Check', 'checkNumber' => '']) as $field => $value) {
        $checkDraft->set($field, $value);
    }
    $checkDraft->call('saveDraft')->assertHasErrors('checkNumber');
});

test('only an approved additional payment method is accepted and its mapping does not select the credited account', function () {
    [$cash, $bank, $utilities] = prepareDisbursementBooks();
    $this->actingAs(disbursementEmployee(['accounting.view', 'accounting.maintain-accounts']));
    Livewire::test(ChartOfAccounts::class)
        ->set('mappingAccounts.disbursement_method:Other', (string) $bank->id)
        ->call('saveMapping', 'disbursement_method:Other')
        ->assertHasNoErrors();
    $this->actingAs(disbursementEmployee());
    $input = disbursementInput($cash, $utilities, ['method' => 'Other']);
    $component = Livewire::test(CashDisbursements::class);
    foreach ($input as $field => $value) {
        $component->set($field, $value);
    }
    $component->call('saveDraft')->assertHasErrors('method');

    $this->actingAs(disbursementEmployee(['accounting.view', 'accounting.approve-accounts']));
    Livewire::test(ChartOfAccounts::class)
        ->call('approveMapping', 'disbursement_method:Other')
        ->assertHasNoErrors();
    $this->actingAs(disbursementEmployee());
    $component = Livewire::test(CashDisbursements::class);
    foreach ($input as $field => $value) {
        $component->set($field, $value);
    }
    $component->call('saveDraft')->assertHasNoErrors();
    $disbursement = CashDisbursement::firstOrFail();
    Livewire::test(CashDisbursements::class)->call('postDisbursement', $disbursement->id)->assertHasNoErrors();
    expect($disbursement->fresh()->journal->lines()->where('accounting_account_id', $cash->id)->value('credit_cents'))->toBe(200000)
        ->and($disbursement->fresh()->journal->lines()->where('accounting_account_id', $bank->id)->exists())->toBeFalse();
});

test('journal-line persistence failure rolls back a prepared disbursement and newly created journal', function () {
    [, $bank, $utilities] = prepareDisbursementBooks();
    $this->actingAs(disbursementEmployee());
    $component = Livewire::test(CashDisbursements::class);
    foreach (disbursementInput($bank, $utilities) as $field => $value) {
        $component->set($field, $value);
    }
    $component->call('saveDraft')->assertHasNoErrors();
    $disbursement = CashDisbursement::firstOrFail();
    $event = 'eloquent.creating: '.AccountingJournalLine::class;
    Event::listen($event, function (AccountingJournalLine $line): void {
        if ($line->journal?->source_type === 'cash_disbursement') {
            throw new RuntimeException('Simulated journal line persistence failure.');
        }
    });

    try {
        expect(fn () => Livewire::test(CashDisbursements::class)->call('postDisbursement', $disbursement->id))
            ->toThrow(RuntimeException::class, 'Simulated journal line persistence failure.');
    } finally {
        Event::forget($event);
    }
    expect($disbursement->fresh()->status)->toBe('draft')
        ->and(AccountingJournal::where('source_type', 'cash_disbursement')->count())->toBe(0);
});

test('closed periods reject a disbursement without posting it', function () {
    [, $bank, $utilities] = prepareDisbursementBooks();
    $this->actingAs(disbursementEmployee());
    $component = Livewire::test(CashDisbursements::class);
    foreach (disbursementInput($bank, $utilities) as $field => $value) {
        $component->set($field, $value);
    }
    $component->call('saveDraft')->assertHasNoErrors();
    $disbursement = CashDisbursement::firstOrFail();
    $period = AccountingPostingPeriod::findOrFail($disbursement->posting_period_id);
    $period->update(['status' => 'closed']);

    Livewire::test(CashDisbursements::class)->call('postDisbursement', $disbursement->id)->assertHasErrors();
    expect($disbursement->fresh()->status)->toBe('draft')
        ->and(AccountingJournal::where('source_type', 'cash_disbursement')->count())->toBe(0);
});
test('direct inventory payments post the gross receipt, mixed debit allocations, and bank credit once', function () {
    $employee = disbursementEmployee([
        'accounting.view',
        'accounting.prepare-disbursements',
        'accounting.post-disbursements',
        'accounting.approve-opening-books',
        'inventory.movements.record',
        'inventory.valuation.approve',
    ]);
    $this->actingAs($employee);
    $cash = approvedDisbursementAccount('1000', 'Operating Cash', 'Asset', 'cash');
    $bank = approvedDisbursementAccount('1010', 'Operating Bank', 'Asset', 'bank');
    $inventoryAccount = approvedDisbursementAccount('1200', 'Inventory', 'Asset', 'inventory');
    $equity = approvedDisbursementAccount('3000', 'Capital', 'Equity', 'capital');
    $utilities = approvedDisbursementAccount('6200', 'Utilities Expense', 'Expense', 'operating_expense');
    $item = Inventory::create([
        'name' => 'Timber', 'category' => 'Lumber', 'qty' => '10.00',
        'unit' => 'piece', 'unit_cost' => '100.00', 'reorder_level' => '0',
    ]);
    $date = now('Asia/Manila')->toDateString();
    $period = AccountingPostingPeriod::create([
        'book_key' => 'FCDC', 'fiscal_year' => now('Asia/Manila')->year,
        'starts_on' => now('Asia/Manila')->startOfYear()->toDateString(),
        'ends_on' => now('Asia/Manila')->endOfYear()->toDateString(), 'status' => 'open',
    ]);
    $books = app(OpeningBooksService::class);
    $books->saveInventoryValuation($date, 'Approved physical opening count', [[
        'inventoryId' => (string) $item->id, 'quantity' => '10.00', 'value' => '1000.00',
    ]], $employee->id);
    $books->saveOpening($date, [
        ['accountId' => $inventoryAccount->id, 'debit' => '1000.00', 'credit' => ''],
        ['accountId' => $equity->id, 'debit' => '', 'credit' => '1000.00'],
    ], $employee->id);
    $books->approveInventoryValuation($employee->id);
    $books->approveOpening($employee->id);
    AccountingPostingMapping::create([
        'source' => 'inventory', 'accounting_account_id' => $inventoryAccount->id,
        'approved_at' => now(), 'approved_by' => $employee->id,
    ]);

    $component = Livewire::test(CashDisbursements::class)
        ->set('payee', 'Timber Supply')
        ->set('paymentDate', $date)
        ->set('method', 'Bank Transfer')
        ->set('moneyAccountId', (string) $bank->id)
        ->set('reference', 'PAY-TIMBER-1')
        ->set('description', 'Timber purchase')
        ->set('evidenceReference', 'Supplier invoice TIM-1')
        ->set('amount', '2500.00')
        ->set('receiptConfirmed', false)
        ->set('allocations', [
            ['allocation_type' => 'inventory', 'inventory_id' => (string) $item->id, 'quantity' => '10.00', 'description' => 'Timber received', 'amount' => '2000.00'],
            ['allocation_type' => 'account', 'accounting_account_id' => (string) $utilities->id, 'description' => 'Delivery', 'amount' => '500.00'],
        ])
        ->call('saveDraft')
        ->assertHasNoErrors();

    $payment = CashDisbursement::query()->firstOrFail();
    expect($payment->payment_date->toDateString())->toBe($date)
        ->and(AccountingJournal::query()->where('source_type', 'opening')->where('source_id', 'FCDC')->value('accounting_date')->toDateString())->toBe($date);
    expect($payment->receipt_confirmed)->toBeFalse()
        ->and($payment->lines()->whereNotNull('inventory_id')->count())->toBe(1);
    $movementCount = StockMovement::query()->count();
    $component->call('postDisbursement', $payment->id)->assertHasErrors('receiptConfirmed');
    expect($payment->fresh()->status)->toBe('draft')
        ->and($item->fresh()->qty)->toBe('10.00')
        ->and(StockMovement::query()->count())->toBe($movementCount)
        ->and(AccountingJournal::query()->where('source_type', 'cash_disbursement')->count())->toBe(0);
    $payment->update(['receipt_confirmed' => true]);
    $event = 'eloquent.creating: '.AccountingJournalLine::class;
    Event::listen($event, function (AccountingJournalLine $line): void {
        if ($line->journal?->source_type === 'cash_disbursement') {
            throw new RuntimeException('Simulated direct purchase journal failure.');
        }
    });
    try {
        expect(fn () => app(CashDisbursementService::class)->post($payment->id, $employee->id))
            ->toThrow(RuntimeException::class, 'Simulated direct purchase journal failure.');
    } finally {
        Event::forget($event);
    }
    expect($payment->fresh()->status)->toBe('draft')
        ->and($item->fresh()->qty)->toBe('10.00')
        ->and(StockMovement::query()->count())->toBe($movementCount)
        ->and(AccountingJournal::query()->where('source_type', 'cash_disbursement')->count())->toBe(0);
    Livewire::test(CashDisbursements::class)->call('postDisbursement', $payment->id)->assertHasNoErrors();
    $payment->refresh();
    $journal = AccountingJournal::query()->where('source_type', 'cash_disbursement')->firstOrFail();
    $receipt = StockMovement::query()->where('cash_disbursement_line_id', $payment->lines()->whereNotNull('inventory_id')->value('id'))->firstOrFail();
    expect($payment->status)->toBe('posted')
        ->and($payment->receipt_confirmed)->toBeTrue()
        ->and($item->fresh()->qty)->toBe('20.00')
        ->and(StockMovement::query()->count())->toBe($movementCount + 1)
        ->and($item->fresh()->carrying_value_cents)->toBe(300000)
        ->and($receipt->value_cents)->toBe(200000)
        ->and($receipt->accounting_journal_id)->toBe($journal->id)
        ->and($journal->lines()->where('accounting_account_id', $inventoryAccount->id)->value('debit_cents'))->toBe(200000)
        ->and($journal->lines()->where('accounting_account_id', $utilities->id)->value('debit_cents'))->toBe(50000)
        ->and($journal->lines()->where('accounting_account_id', $bank->id)->value('credit_cents'))->toBe(250000)
        ->and(AccountingJournal::query()->where('source_type', 'stock_movement')->count())->toBe(0)
        ->and(AccountingJournal::query()->where('source_type', 'supplier_purchase')->count())->toBe(0);
    expect(fn () => $payment->forceFill(['reference' => 'CHANGED'])->save())
        ->toThrow(LogicException::class, 'Posted disbursements are immutable.');
    $purchaseLine = $payment->lines()->whereNotNull('inventory_id')->firstOrFail();
    expect(fn () => $purchaseLine->forceFill(['description' => 'Changed receipt'])->save())
        ->toThrow(LogicException::class, 'Posted disbursement allocations are immutable.');
    Livewire::test(CashDisbursements::class)->call('showDisbursement', $payment->id)
        ->assertSee('Timber received')
        ->assertSee('PURCHASE-'.$payment->lines()->whereNotNull('inventory_id')->first()->id);
});
