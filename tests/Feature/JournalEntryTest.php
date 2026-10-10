<?php

use App\Livewire\Accounting\JournalEntry;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\AccountingPostingPeriod;
use App\Models\Employee;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class)->beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function journalEmployee(array $permissions = ['accounting.view', 'accounting.create-journal-entry', 'accounting.post-journal-entry']): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'journal.employee.'.fake()->unique()->numerify('####'),
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('journal-password'),
    ]), $permissions);
}

function approvedJournalAccount(string $code, string $type, string $classification, string $normalBalance): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => $classification,
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => $normalBalance,
        'is_active' => true,
        'approved_at' => now(),
    ]);
}

function prepareJournalBook(?string $cutoverDate = null): array
{
    $cash = approvedJournalAccount('1000', 'Asset', 'cash', 'debit');
    $revenue = approvedJournalAccount('4000', 'Revenue', 'sales', 'credit');
    $period = AccountingPostingPeriod::create([
        'book_key' => 'FCDC',
        'fiscal_year' => now('Asia/Manila')->year,
        'starts_on' => now('Asia/Manila')->startOfYear()->toDateString(),
        'ends_on' => now('Asia/Manila')->endOfYear()->toDateString(),
        'status' => 'open',
    ]);
    AccountingJournal::create([
        'book_key' => 'FCDC',
        'reference' => 'OPENING-'.fake()->unique()->numerify('####'),
        'source_type' => 'opening',
        'source_id' => 'FCDC',
        'accounting_date' => $cutoverDate ?? $period->starts_on,
        'posting_period_id' => $period->id,
        'description' => 'Approved cutover',
        'status' => 'posted',
        'prepared_by' => journalEmployee(['accounting.view'])->id,
        'posted_by' => journalEmployee(['accounting.view'])->id,
        'posted_at' => now(),
    ]);

    return [$cash, $revenue, $period];
}

function journalInput(AccountingAccount $cash, AccountingAccount $revenue, array $overrides = []): array
{
    return array_merge([
        'accountingDate' => now('Asia/Manila')->toDateString(),
        'reference' => 'MANUAL-'.fake()->unique()->numerify('#####'),
        'externalReference' => '',
        'description' => 'Recognize completed event',
        'lines' => [
            ['accountId' => (string) $cash->id, 'debit' => '125.00', 'credit' => ''],
            ['accountId' => (string) $revenue->id, 'debit' => '', 'credit' => '125.00'],
        ],
    ], $overrides);
}

function fillJournal($component, array $journal)
{
    foreach ($journal as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}


test('journal page visibility and draft preparation use separate permissions', function () {
    [$cash, $revenue] = prepareJournalBook();
    $viewer = journalEmployee(['accounting.view']);
    $this->actingAs($viewer)->get(route('accounting.journal-entry'))->assertOk();
    fillJournal(Livewire::test(JournalEntry::class), journalInput($cash, $revenue, ['reference' => 'VIEWER-DRAFT']))
        ->call('saveDraft')->assertForbidden();

    $preparerWithoutView = journalEmployee(['accounting.create-journal-entry']);
    $this->actingAs($preparerWithoutView)->get(route('accounting.journal-entry'))->assertForbidden();
});
test('persistent drafts survive reload and can be edited or deleted without posting effects', function () {
    [$cash, $revenue] = prepareJournalBook();
    $preparer = journalEmployee(['accounting.view', 'accounting.create-journal-entry']);
    $this->actingAs($preparer);
    $journal = journalInput($cash, $revenue, ['reference' => 'DRAFT-001']);

    fillJournal(Livewire::test(JournalEntry::class), $journal)->call('saveDraft')->assertHasNoErrors();
    $draft = AccountingJournal::where('reference', 'DRAFT-001')->firstOrFail();
    expect($draft->status)->toBe('draft')->and($draft->lines)->toHaveCount(2);
    fillJournal(Livewire::test(JournalEntry::class), journalInput($cash, $revenue, ['reference' => 'DRAFT-001']))
        ->call('saveDraft')->assertHasErrors('reference');
    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->actingAs($preparer)->get(route('accounting.journal-entry'))->assertOk()->assertSee('DRAFT-001');
    Livewire::test(JournalEntry::class)
        ->call('editDraft', $draft->id)
        ->set('description', 'Updated draft')
        ->call('saveDraft')
        ->assertHasNoErrors();
    expect($draft->fresh()->description)->toBe('Updated draft')
        ->and(AccountingJournal::where('status', 'posted')->count())->toBe(1);

    Livewire::test(JournalEntry::class)->call('deleteDraft', $draft->id)->assertHasNoErrors();
    expect(AccountingJournal::find($draft->id))->toBeNull()
        ->and($draft->lines()->count())->toBe(0);
});

test('manual journal posting rejects unequal centavos and admits a balanced entry once', function () {
    [$cash, $revenue] = prepareJournalBook();
    $poster = journalEmployee();
    $this->actingAs($poster);
    $journal = journalInput($cash, $revenue, [
        'reference' => 'MANUAL-VALID-001',
        'lines' => [
            ['accountId' => (string) $cash->id, 'debit' => '125.00', 'credit' => ''],
            ['accountId' => (string) $revenue->id, 'debit' => '', 'credit' => '124.99'],
        ],
    ]);
    fillJournal(Livewire::test(JournalEntry::class), $journal)->call('saveDraft')->assertHasNoErrors();
    $draft = AccountingJournal::where('reference', 'MANUAL-VALID-001')->firstOrFail();
    Livewire::test(JournalEntry::class)->call('postDraft', $draft->id)->assertHasErrors('journal');
    expect($draft->fresh()->status)->toBe('draft');

    $journal['lines'][1]['credit'] = '125.00';
    Livewire::test(JournalEntry::class)->call('editDraft', $draft->id)->set('lines', $journal['lines'])
        ->call('saveDraft')->assertHasNoErrors();
    Livewire::test(JournalEntry::class)->call('postDraft', $draft->id)->assertHasNoErrors();
    expect($draft->fresh()->status)->toBe('posted')
        ->and($draft->fresh()->posted_by)->toBe($poster->id)
        ->and($draft->fresh()->source_type)->toBe('manual')
        ->and($draft->fresh()->lines->sum('debit_cents'))->toBe(12500)
        ->and($draft->fresh()->lines->sum('credit_cents'))->toBe(12500);
});

test('posting requires posting permission and approved active accounts, and refuses closed, future, pre-cutover and controlled-account entries', function () {
    [$cash, $revenue, $period] = prepareJournalBook();
    $preparer = journalEmployee(['accounting.view', 'accounting.create-journal-entry']);
    $this->actingAs($preparer);
    $journal = journalInput($cash, $revenue, ['reference' => 'NO-POST-001']);
    fillJournal(Livewire::test(JournalEntry::class), $journal)->call('saveDraft')->assertHasNoErrors();
    $draft = AccountingJournal::where('reference', 'NO-POST-001')->firstOrFail();
    Livewire::test(JournalEntry::class)->call('postDraft', $draft->id)->assertForbidden();

    $poster = journalEmployee();
    $this->actingAs($poster);
    $pending = approvedJournalAccount('1005', 'Asset', 'cash', 'debit');
    $pending->update(['approved_at' => null]);
    Livewire::test(JournalEntry::class)->call('editDraft', $draft->id)->set('lines', [
        ['accountId' => (string) $pending->id, 'debit' => '125.00', 'credit' => ''],
        ['accountId' => (string) $revenue->id, 'debit' => '', 'credit' => '125.00'],
    ])->call('saveDraft')->assertHasNoErrors();
    Livewire::test(JournalEntry::class)->call('postDraft', $draft->id)->assertHasErrors('journal');
    $inactive = approvedJournalAccount('1010', 'Asset', 'cash', 'debit');
    $inactive->update(['is_active' => false, 'approved_at' => now()]);
    Livewire::test(JournalEntry::class)->call('editDraft', $draft->id)->set('lines', [
        ['accountId' => (string) $inactive->id, 'debit' => '125.00', 'credit' => ''],
        ['accountId' => (string) $revenue->id, 'debit' => '', 'credit' => '125.00'],
    ])->call('saveDraft')->assertHasNoErrors();
    Livewire::test(JournalEntry::class)->call('postDraft', $draft->id)->assertHasErrors('journal');
    expect($draft->fresh()->status)->toBe('draft');

    Livewire::test(JournalEntry::class)->call('editDraft', $draft->id)->set('lines', [
        ['accountId' => (string) $cash->id, 'debit' => '125.00', 'credit' => ''],
        ['accountId' => (string) $revenue->id, 'debit' => '', 'credit' => '125.00'],
    ])->call('saveDraft')->assertHasNoErrors();
    $period->update(['status' => 'closed']);
    Livewire::test(JournalEntry::class)->call('postDraft', $draft->id)->assertHasErrors('journal');
    $period->update(['status' => 'open']);

    $ap = approvedJournalAccount('2000', 'Liability', 'accounts_payable', 'credit');
    Livewire::test(JournalEntry::class)->call('editDraft', $draft->id)->set('lines', [
        ['accountId' => (string) $cash->id, 'debit' => '125.00', 'credit' => ''],
        ['accountId' => (string) $ap->id, 'debit' => '', 'credit' => '125.00'],
    ])->call('saveDraft')->assertHasNoErrors();
    Livewire::test(JournalEntry::class)->call('postDraft', $draft->id)->assertHasErrors('journal');
});

test('posting rejects future and pre-cutover dates while allowing approved GL-only receivable and card-clearing accounts', function () {
    [$cash, $revenue] = prepareJournalBook(now('Asia/Manila')->toDateString());
    $this->actingAs(journalEmployee());
    foreach ([
        ['reference' => 'FUTURE-001', 'accountingDate' => now('Asia/Manila')->addDay()->toDateString()],
        ['reference' => 'PRE-CUTOVER-001', 'accountingDate' => now('Asia/Manila')->subDay()->toDateString()],
    ] as $case) {
        $journal = journalInput($cash, $revenue, $case);
        fillJournal(Livewire::test(JournalEntry::class), $journal)->call('saveDraft')->assertHasNoErrors();
        $draft = AccountingJournal::where('reference', $case['reference'])->firstOrFail();
        Livewire::test(JournalEntry::class)->call('postDraft', $draft->id)->assertHasErrors('journal');
        expect($draft->fresh()->status)->toBe('draft');
    }

    foreach ([
        ['account' => approvedJournalAccount('1100', 'Asset', 'accounts_receivable', 'debit'), 'reference' => 'GL-AR-001'],
        ['account' => approvedJournalAccount('1110', 'Asset', 'card_clearing', 'debit'), 'reference' => 'CARD-CLEARING-001'],
    ] as $case) {
        $journal = journalInput($case['account'], $revenue, ['reference' => $case['reference']]);
        fillJournal(Livewire::test(JournalEntry::class), $journal)->call('saveDraft')->assertHasNoErrors();
        $entry = AccountingJournal::where('reference', $case['reference'])->firstOrFail();
        Livewire::test(JournalEntry::class)->call('postDraft', $entry->id)->assertHasNoErrors();
        expect($entry->fresh()->status)->toBe('posted');
    }
});

test('manual journal lines reject two-sided or sub-cent amounts and block both controlled accounts', function () {
    [$cash, $revenue] = prepareJournalBook();
    $this->actingAs(journalEmployee());
    $journal = journalInput($cash, $revenue, ['reference' => 'INVALID-LINES-001']);
    $journal['lines'][0]['credit'] = '0.01';
    fillJournal(Livewire::test(JournalEntry::class), $journal)->call('saveDraft')->assertHasErrors('lines.0');

    $journal = journalInput($cash, $revenue, ['reference' => 'SUB-CENT-001']);
    $journal['lines'][0]['debit'] = '1.001';
    fillJournal(Livewire::test(JournalEntry::class), $journal)->call('saveDraft')->assertHasErrors('lines');

    $inventory = approvedJournalAccount('1200', 'Asset', 'inventory', 'debit');
    $journal = journalInput($cash, $revenue, ['reference' => 'INVENTORY-001', 'lines' => [
        ['accountId' => (string) $cash->id, 'debit' => '125.00', 'credit' => ''],
        ['accountId' => (string) $inventory->id, 'debit' => '', 'credit' => '125.00'],
    ]]);
    fillJournal(Livewire::test(JournalEntry::class), $journal)->call('saveDraft')->assertHasNoErrors();
    $entry = AccountingJournal::where('reference', 'INVENTORY-001')->firstOrFail();
    Livewire::test(JournalEntry::class)->call('postDraft', $entry->id)->assertHasErrors('journal');
    expect($entry->fresh()->status)->toBe('draft');
    $journal = journalInput($cash, $revenue, ['reference' => 'OVERFLOW-001']);
    $journal['lines'][0]['debit'] = '100000000000000000.00';
    fillJournal(Livewire::test(JournalEntry::class), $journal)->call('saveDraft')->assertHasErrors('lines');
});

test('a correction posts a linked reversal and replacement with reason and actors while preserving original history', function () {
    [$cash, $revenue] = prepareJournalBook();
    $poster = journalEmployee();
    $this->actingAs($poster);
    $original = journalInput($cash, $revenue, ['reference' => 'MANUAL-ORIGINAL-001']);
    fillJournal(Livewire::test(JournalEntry::class), $original)->call('saveDraft')->assertHasNoErrors();
    $entry = AccountingJournal::where('reference', 'MANUAL-ORIGINAL-001')->firstOrFail();
    Livewire::test(JournalEntry::class)->call('postDraft', $entry->id)->assertHasNoErrors();
    $beforeLines = $entry->fresh()->lines->map(fn ($line) => [$line->accounting_account_id, $line->debit_cents, $line->credit_cents])->all();

    Livewire::test(JournalEntry::class)
        ->call('beginCorrection', $entry->id)
        ->set('accountingDate', now('Asia/Manila')->subDay()->toDateString())
        ->set('correctionReason', 'Correct amount supported by receipt')
        ->set('correctionReference', 'CORR-REV-001')
        ->set('replacementReference', 'CORR-REPL-001')
        ->set('lines', [
            ['accountId' => (string) $cash->id, 'debit' => '120.00', 'credit' => ''],
            ['accountId' => (string) $revenue->id, 'debit' => '', 'credit' => '120.00'],
        ])
        ->call('correct', $entry->id)
        ->assertHasNoErrors();

    $reversal = AccountingJournal::where('reference', 'CORR-REV-001')->firstOrFail();
    $replacement = AccountingJournal::where('reference', 'CORR-REPL-001')->firstOrFail();
    expect($entry->fresh()->status)->toBe('posted')
        ->and($entry->fresh()->lines->map(fn ($line) => [$line->accounting_account_id, $line->debit_cents, $line->credit_cents])->all())->toBe($beforeLines)
        ->and($reversal->source_type)->toBe('reversal')
        ->and($reversal->correction_of_id)->toBe($entry->id)
        ->and($reversal->correction_reason)->toBe('Correct amount supported by receipt')
        ->and($reversal->posted_by)->toBe($poster->id)
        ->and($reversal->accounting_date->toDateString())->toBe(now('Asia/Manila')->toDateString())
        ->and($replacement->accounting_date->toDateString())->toBe(now('Asia/Manila')->toDateString())
        ->and($replacement->source_type)->toBe('replacement')
        ->and($replacement->correction_of_id)->toBe($entry->id)
        ->and($replacement->posted_by)->toBe($poster->id)
        ->and($reversal->reference)->not->toBe($replacement->reference);
});

test('posted journals cannot be edited or deleted and listing filters posted history', function () {
    [$cash, $revenue] = prepareJournalBook();
    $poster = journalEmployee();
    $this->actingAs($poster);
    $journal = journalInput($cash, $revenue, ['reference' => 'FILTERED-001', 'description' => 'Unique event text']);
    fillJournal(Livewire::test(JournalEntry::class), $journal)->call('saveDraft')->assertHasNoErrors();
    $entry = AccountingJournal::where('reference', 'FILTERED-001')->firstOrFail();
    Livewire::test(JournalEntry::class)->call('postDraft', $entry->id)->assertHasNoErrors();
    Livewire::test(JournalEntry::class)->set('search', 'FILTERED-001')->assertSee('FILTERED-001')->assertDontSee('OPENING-');
    Livewire::test(JournalEntry::class)->set('sourceFilter', 'manual')->set('dateFrom', now('Asia/Manila')->addDay()->toDateString())
        ->assertDontSee('FILTERED-001');
    expect(fn () => $entry->update(['description' => 'Rewritten']))->toThrow(DomainException::class);
    expect(fn () => $entry->fresh()->delete())->toThrow(DomainException::class);
});
