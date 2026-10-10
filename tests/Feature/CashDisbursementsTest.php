<?php

use App\Livewire\Accounting\CashDisbursements;
use App\Livewire\Accounting\ChartOfAccounts;
use App\Models\AccountingAccount;
use App\Models\AccountingJournalLine;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    $disbursement = \App\Models\CashDisbursement::query()->firstOrFail();
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
    $draft = \App\Models\CashDisbursement::firstOrFail();

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
    expect(\App\Models\CashDisbursement::find($draft->id))->toBeNull()
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
    $disbursement = \App\Models\CashDisbursement::firstOrFail();
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
    $draftId = \App\Models\CashDisbursement::firstOrFail()->id;
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
    $disbursement = \App\Models\CashDisbursement::firstOrFail();
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
    $disbursement = \App\Models\CashDisbursement::firstOrFail();
    $event = 'eloquent.creating: '.AccountingJournalLine::class;
    \Illuminate\Support\Facades\Event::listen($event, function (AccountingJournalLine $line): void {
        if ($line->journal?->source_type === 'cash_disbursement') {
            throw new RuntimeException('Simulated journal line persistence failure.');
        }
    });

    try {
        expect(fn () => Livewire::test(CashDisbursements::class)->call('postDisbursement', $disbursement->id))
            ->toThrow(RuntimeException::class, 'Simulated journal line persistence failure.');
    } finally {
        \Illuminate\Support\Facades\Event::forget($event);
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
    $disbursement = \App\Models\CashDisbursement::firstOrFail();
    $period = AccountingPostingPeriod::findOrFail($disbursement->posting_period_id);
    $period->update(['status' => 'closed']);

    Livewire::test(CashDisbursements::class)->call('postDisbursement', $disbursement->id)->assertHasErrors();
    expect($disbursement->fresh()->status)->toBe('draft')
        ->and(AccountingJournal::where('source_type', 'cash_disbursement')->count())->toBe(0);
});
