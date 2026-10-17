<?php

use App\Livewire\Accounting\NotesToFinancialStatements;
use App\Models\AccountingAccount;
use App\Models\AccountingDisclosureNote;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
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

function disclosureEmployee(array $permissions = ['accounting.view']): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'disclosure.'.fake()->unique()->numerify('####'),
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('disclosure-password'),
    ]), $permissions);
}

function disclosureAccount(string $code, string $classification, string $type, string $normalBalance): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => 'Account '.$code,
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => $normalBalance,
        'is_active' => true,
        'approved_at' => now(),
    ]);
}

function disclosurePeriod(int $year): AccountingPostingPeriod
{
    return AccountingPostingPeriod::create([
        'book_key' => 'FCDC',
        'fiscal_year' => $year,
        'starts_on' => "$year-01-01",
        'ends_on' => "$year-12-31",
        'status' => 'open',
    ]);
}

function disclosureJournal(AccountingPostingPeriod $period, Employee $actor, string $date, string $source, array $lines, string $reference, string $entryType = 'standard'): AccountingJournal
{
    $journal = AccountingJournal::create([
        'book_key' => 'FCDC',
        'reference' => $reference,
        'source_type' => $source,
        'entry_type' => $entryType,
        'source_id' => $source === 'opening' ? 'FCDC' : $reference,
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
    $journal->forceFill(['status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now()])->save();

    return $journal;
}

function seedDisclosureBooks(Employee $actor): void
{
    $period = disclosurePeriod(2026);
    $cash = disclosureAccount('1000', 'cash', 'Asset', 'debit');
    $inventory = disclosureAccount('1200', 'inventory', 'Asset', 'debit');
    $payable = disclosureAccount('2000', 'accounts_payable', 'Liability', 'credit');
    $vat = disclosureAccount('2200', 'output_vat', 'Liability', 'credit');
    $capital = disclosureAccount('3000', 'capital', 'Equity', 'credit');
    $sales = disclosureAccount('4000', 'sales', 'Revenue', 'credit');
    $expense = disclosureAccount('6100', 'operating_expense', 'Expense', 'debit');

    disclosureJournal($period, $actor, '2026-01-01', 'opening', [
        [$cash, 100000, 0], [$inventory, 20000, 0], [$capital, 0, 120000],
    ], 'DISC-OPEN');
    disclosureJournal($period, $actor, '2026-03-01', 'sale', [
        [$cash, 5600, 0], [$sales, 0, 5000], [$vat, 0, 600],
    ], 'DISC-SALE');
    disclosureJournal($period, $actor, '2026-03-02', 'purchase', [
        [$inventory, 3000, 0], [$payable, 0, 3000],
    ], 'DISC-PURCHASE');
    disclosureJournal($period, $actor, '2026-03-31', 'adjustment', [
        [$expense, 500, 0], [$cash, 0, 500],
    ], 'DISC-ADJUST', 'adjusting');
}

test('disclosure notes page is authenticated and accounting.view protects report access', function () {
    $this->get(route('accounting.notes-to-financial-statements'))->assertRedirect(route('login'));

    $this->actingAs(disclosureEmployee(['accounting.view']))
        ->get(route('accounting.notes-to-financial-statements'))
        ->assertOk()
        ->assertSee('Notes to Financial Statements / Disclosure')
        ->assertSee('Management-prepared disclosures')
        ->assertSee('not audited')
        ->assertSee('Generated figures')
        ->assertSee('Disclosure notes');

    $denied = disclosureEmployee([]);
    $this->actingAs($denied)->get(route('accounting.notes-to-financial-statements'))->assertForbidden();
});

test('generated figures derive from the adjusted trial balance including adjusting entries', function () {
    $manager = disclosureEmployee(['accounting.view', 'accounting.manage-disclosures']);
    seedDisclosureBooks($manager);

    Livewire::actingAs($manager)->test(NotesToFinancialStatements::class)
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-03-31')
        ->assertSee('Generated figures')
        ->assertSee('Cash and cash equivalents')
        ->assertSee('Accounts receivable')
        ->assertSee('Inventory')
        ->assertSee('Accounts payable')
        ->assertSee('Revenue')
        ->assertSee('1,051.00')
        ->assertSee('230.00')
        ->assertSee('30.00')
        ->assertSee('50.00')
        ->call('addGeneratedNote', 'cash_and_bank')
        ->call('addGeneratedNote', 'accounts_payable')
        ->call('addGeneratedNote', 'revenue')
        ->assertHasNoErrors();

    $note = AccountingDisclosureNote::where('source_metric', 'cash_and_bank')->firstOrFail();
    expect($note->disclosure_type)->toBe(AccountingDisclosureNote::TYPE_GENERATED)
        ->and($note->status)->toBe(AccountingDisclosureNote::STATUS_DRAFT)
        ->and($note->note_reference)->toBe('Note 1')
        ->and($note->title)->toBe('Cash and cash equivalents')
        ->and($note->generated_amount_cents)->toBe(105100)
        ->and($note->reporting_period_end->toDateString())->toBe('2026-03-31')
        ->and($note->body)->toContain('adjusted trial balance');
    expect(AccountingDisclosureNote::where('source_metric', 'accounts_payable')->firstOrFail()->generated_amount_cents)->toBe(3000)
        ->and(AccountingDisclosureNote::where('source_metric', 'revenue')->firstOrFail()->generated_amount_cents)->toBe(5000);

    Livewire::actingAs($manager)->test(NotesToFinancialStatements::class)
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-03-31')
        ->assertSee('1,051.00')
        ->assertSee('Generated');
});

test('manual disclosure notes follow review approval and protected finalization', function () {
    $preparer = disclosureEmployee(['accounting.view', 'accounting.manage-disclosures', 'accounting.approve-disclosures']);
    $approver = disclosureEmployee(['accounting.view', 'accounting.approve-disclosures']);

    Livewire::actingAs($preparer)->test(NotesToFinancialStatements::class)
        ->set('fromDate', '2026-01-01')
        ->set('toDate', '2026-03-31')
        ->set('noteReference', 'Note 1')
        ->set('title', 'Basis of preparation')
        ->set('body', 'The financial statements are prepared on the accrual basis in Philippine pesos.')
        ->call('saveNote')
        ->assertHasNoErrors();

    $note = AccountingDisclosureNote::where('note_reference', 'Note 1')->firstOrFail();
    expect($note->disclosure_type)->toBe(AccountingDisclosureNote::TYPE_MANUAL)
        ->and($note->status)->toBe(AccountingDisclosureNote::STATUS_DRAFT)
        ->and($note->generated_amount_cents)->toBeNull();

    Livewire::actingAs($preparer)->test(NotesToFinancialStatements::class)
        ->call('submitNote', $note->id)->assertHasNoErrors();
    expect($note->fresh()->status)->toBe(AccountingDisclosureNote::STATUS_PENDING_REVIEW);

    Livewire::actingAs($preparer)->test(NotesToFinancialStatements::class)
        ->call('approveNote', $note->id)
        ->assertHasErrors('disclosure')
        ->assertSee('A different authorized employee must approve this disclosure note.');
    expect($note->fresh()->status)->toBe(AccountingDisclosureNote::STATUS_PENDING_REVIEW)
        ->and($note->fresh()->approved_by)->toBeNull();

    Livewire::actingAs($approver)->test(NotesToFinancialStatements::class)
        ->call('returnNote', $note->id)->assertHasNoErrors();
    expect($note->fresh()->status)->toBe(AccountingDisclosureNote::STATUS_DRAFT);
    Livewire::actingAs($preparer)->test(NotesToFinancialStatements::class)
        ->call('submitNote', $note->id)->assertHasNoErrors();

    Livewire::actingAs($approver)->test(NotesToFinancialStatements::class)
        ->call('approveNote', $note->id)->assertHasNoErrors();
    expect($note->fresh()->status)->toBe(AccountingDisclosureNote::STATUS_APPROVED)
        ->and($note->fresh()->approved_by)->toBe($approver->id);
    expect(fn () => $note->fresh()->update(['body' => 'Changed after approval']))
        ->toThrow(DomainException::class);

    Livewire::actingAs($approver)->test(NotesToFinancialStatements::class)
        ->call('finalizeNote', $note->id)->assertHasNoErrors();
    expect($note->fresh()->status)->toBe(AccountingDisclosureNote::STATUS_FINALIZED)
        ->and($note->fresh()->finalized_by)->toBe($approver->id)
        ->and($note->fresh()->finalized_at)->not->toBeNull();
    expect(fn () => $note->fresh()->update(['title' => 'Rewritten']))
        ->toThrow(DomainException::class);
    expect(fn () => $note->fresh()->delete())->toThrow(DomainException::class);

    Livewire::actingAs($approver)->test(NotesToFinancialStatements::class)
        ->assertSee('Protected');
});

test('disclosure actions require their dedicated permissions', function () {
    $viewer = disclosureEmployee(['accounting.view']);
    Livewire::actingAs($viewer)->test(NotesToFinancialStatements::class)
        ->set('noteReference', 'Note 1')->set('title', 'Title')->set('body', 'Body')
        ->call('saveNote')->assertForbidden();
    Livewire::actingAs($viewer)->test(NotesToFinancialStatements::class)
        ->call('addGeneratedNote', 'cash_and_bank')->assertForbidden();
    Livewire::actingAs($viewer)->test(NotesToFinancialStatements::class)
        ->assertDontSee('Save draft')
        ->assertDontSee('Create note');

    $manager = disclosureEmployee(['accounting.view', 'accounting.manage-disclosures']);
    Livewire::actingAs($manager)->test(NotesToFinancialStatements::class)
        ->set('noteReference', 'Note 1')->set('title', 'Title')->set('body', 'Body')
        ->set('fromDate', '2026-01-01')->set('toDate', '2026-03-31')
        ->call('saveNote')->assertHasNoErrors();
    $note = AccountingDisclosureNote::where('note_reference', 'Note 1')->firstOrFail();
    Livewire::actingAs($manager)->test(NotesToFinancialStatements::class)
        ->call('submitNote', $note->id)->assertHasNoErrors();
    Livewire::actingAs($manager)->test(NotesToFinancialStatements::class)
        ->call('approveNote', $note->id)->assertForbidden();
    Livewire::actingAs($manager)->test(NotesToFinancialStatements::class)
        ->call('finalizeNote', $note->id)->assertForbidden();
});

test('disclosure note references stay unique within a reporting period', function () {
    $manager = disclosureEmployee(['accounting.view', 'accounting.manage-disclosures']);
    $period = disclosurePeriod(2026);
    disclosureJournal($period, $manager, '2026-01-01', 'opening', [
        [$cash = disclosureAccount('1000', 'cash', 'Asset', 'debit'), 100, 0],
        [disclosureAccount('3000', 'capital', 'Equity', 'credit'), 0, 100],
    ], 'DISC-OPEN-2');

    Livewire::actingAs($manager)->test(NotesToFinancialStatements::class)
        ->set('fromDate', '2026-01-01')->set('toDate', '2026-01-31')
        ->set('noteReference', 'Note 1')->set('title', 'First')->set('body', 'First body')
        ->call('saveNote')->assertHasNoErrors();
    Livewire::actingAs($manager)->test(NotesToFinancialStatements::class)
        ->set('fromDate', '2026-01-01')->set('toDate', '2026-01-31')
        ->set('noteReference', 'Note 1')->set('title', 'Duplicate')->set('body', 'Duplicate body')
        ->call('saveNote')->assertHasErrors('noteReference');
    expect(AccountingDisclosureNote::where('note_reference', 'Note 1')->count())->toBe(1);
});

test('disclosure figures are unavailable before approved accounting coverage exists', function () {
    $readiness = Mockery::mock(OpeningBooksService::class);
    $readiness->shouldReceive('readiness')->andReturn([
        'production_activated' => false,
        'production' => 'Production accounting is not activated.',
    ]);
    app()->instance(OpeningBooksService::class, $readiness);

    Livewire::actingAs(disclosureEmployee(['accounting.view', 'accounting.manage-disclosures']))
        ->test(NotesToFinancialStatements::class)
        ->assertSee('Generated figures unavailable')
        ->assertSee('Production accounting is not activated.')
        ->call('addGeneratedNote', 'cash_and_bank')
        ->assertHasErrors('generated');
});
