<?php

use App\Livewire\Accounting\JournalEntry;
use App\Models\Employee;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class)->beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function createJournalEntryEmployee(array $overrides = []): Employee
{
    return Employee::create(array_merge([
        'username' => 'journal.employee',
        'email' => 'journal@example.com',
        'password' => Hash::make('journal-password'),
    ], $overrides));
}

function validDemoJournal(): array
{
    return [
        'date' => '2026-10-04',
        'reference' => 'DEMO-JE-001',
        'source' => 'Manual',
        'description' => 'Illustrative journal entry',
        'lines' => [
            ['accountCode' => '1010', 'description' => 'Cash received', 'debit' => '125.00', 'credit' => ''],
            ['accountCode' => '4010', 'description' => 'Illustrative revenue', 'debit' => '', 'credit' => '125.00'],
        ],
    ];
}

function journalEntryComponentWith(array $journal)
{
    return Livewire::test(JournalEntry::class)
        ->set('date', $journal['date'])
        ->set('reference', $journal['reference'])
        ->set('source', $journal['source'])
        ->set('description', $journal['description'])
        ->set('lines', $journal['lines']);
}

test('journal entry is an authenticated named Livewire page in accounting navigation', function () {
    $this->get(route('accounting.journal-entry'))->assertRedirect(route('login'));

    $this->actingAs(createJournalEntryEmployee())
        ->get(route('accounting.journal-entry'))
        ->assertOk()
        ->assertSee('Journal Entry')
        ->assertSee('Demonstration only')
        ->assertSee(route('accounting.journal-entry'), false)
        ->assertSee(route('accounting.chart-of-accounts'), false);
});

test('journal entry rejects missing headers, conflicting line amounts, and unbalanced or zero totals', function () {
    $this->actingAs(createJournalEntryEmployee());

    Livewire::test(JournalEntry::class)
        ->set('date', '')
        ->call('save')
        ->assertHasErrors(['date', 'reference', 'source', 'description']);

    $journal = validDemoJournal();
    $journal['lines'][0]['credit'] = '20.00';
    journalEntryComponentWith($journal)
        ->call('save')
        ->assertHasErrors('lines.0');

    $journal = validDemoJournal();
    $journal['lines'][1]['credit'] = '124.00';
    journalEntryComponentWith($journal)
        ->call('save')
        ->assertHasErrors('lines');

    $journal['lines'][0]['debit'] = '';
    $journal['lines'][1]['credit'] = '';
    journalEntryComponentWith($journal)
        ->call('save')
        ->assertHasErrors('lines');
});

test('a corrected journal can be saved after a line conflict is rejected', function () {
    $this->actingAs(createJournalEntryEmployee());
    $journal = validDemoJournal();
    $journal['lines'][0]['credit'] = '5.00';

    journalEntryComponentWith($journal)
        ->call('save')
        ->assertHasErrors('lines.0')
        ->set('lines.0.credit', '')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Demonstration entry saved. Not posted.');
});

test('a balanced journal remains a session demonstration across navigation and refresh', function () {
    $employee = createJournalEntryEmployee();
    $this->actingAs($employee);

    journalEntryComponentWith([
        'date' => '2026-10-04',
        'reference' => 'DEMO-JE-001',
        'source' => 'Manual',
        'description' => 'Illustrative journal entry',
        'lines' => validDemoJournal()['lines'],
    ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Demonstration entry saved')
        ->assertSee('Not posted');

    $this->get(route('accounting.overview'))->assertOk()->assertSee('No posted accounting activity');
    $this->get(route('accounting.journal-entry'))->assertOk()
        ->assertSee('DEMO-JE-001')
        ->assertSee('Illustrative journal entry')
        ->assertSee('125.00');

    expect(session()->get('demo.journals.employee.'.$employee->id.'.history'))->toHaveCount(1);
    expect(session()->get('demo.journals.employee.'.$employee->id.'.history.0.demo'))->toBeTrue();
});

test('journal draft and history are employee-scoped, clearable, and removed at logout', function () {
    $first = createJournalEntryEmployee();
    $second = createJournalEntryEmployee(['username' => 'journal.second', 'email' => 'second-journal@example.com']);
    $this->actingAs($first);

    journalEntryComponentWith([
        'date' => '2026-10-04',
        'reference' => 'DEMO-JE-001',
        'source' => 'Manual',
        'description' => 'First demonstration',
        'lines' => validDemoJournal()['lines'],
    ])
        ->call('save')
        ->assertHasNoErrors();

    journalEntryComponentWith([
        'date' => '2026-10-04',
        'reference' => 'DRAFT-001',
        'source' => 'Manual',
        'description' => 'Draft stays temporary',
        'lines' => validDemoJournal()['lines'],
    ]);

    $this->get(route('accounting.overview'))->assertOk();
    $this->get(route('accounting.journal-entry'))->assertOk()
        ->assertSee('DRAFT-001')
        ->assertSee('DEMO-JE-001');

    Livewire::test(JournalEntry::class)->call('clear')->assertHasNoErrors();
    $this->get(route('accounting.journal-entry'))->assertOk()
        ->assertDontSee('DRAFT-001')
        ->assertSee('DEMO-JE-001');

    journalEntryComponentWith([
        'date' => '2026-10-04',
        'reference' => 'DEMO-JE-002',
        'source' => 'Manual',
        'description' => 'Second demo entry',
        'lines' => validDemoJournal()['lines'],
    ])
        ->call('save');

    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->actingAs($second)->get(route('accounting.journal-entry'))->assertOk()
        ->assertDontSee('DEMO-JE-002');
    expect(session()->get('demo.journals.employee.'.$first->id.'.history', []))->toBe([]);

    $this->post(route('logout'));
    $this->actingAs($first)->get(route('accounting.journal-entry'))->assertOk()
        ->assertDontSee('DEMO-JE-002');
});

test('journal line operations stay within their supported bounds', function () {
    $this->actingAs(createJournalEntryEmployee());
    $component = Livewire::test(JournalEntry::class);

    expect(count($component->get('lines')))->toBe(2);
    $component->call('removeLine', 0);
    expect(count($component->get('lines')))->toBe(2);

    for ($index = 0; $index < 10; $index++) {
        $component->call('addLine');
    }
    expect(count($component->get('lines')))->toBe(10);

    $component->call('addLine');
    expect(count($component->get('lines')))->toBe(10);

    $component->call('removeLine', 0);
    expect(count($component->get('lines')))->toBe(9);
});
