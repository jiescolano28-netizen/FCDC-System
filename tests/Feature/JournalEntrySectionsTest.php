<?php

use App\Livewire\Accounting\JournalEntry;
use App\Models\AccountingAccount;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function sectionJournalEmployee(array $permissions): Employee
{
    static $counter = 0;
    $counter++;

    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'journal.section.'.$counter,
        'email' => 'journal.section'.$counter.'@example.com',
        'password' => Hash::make('journal-section-password'),
    ]), $permissions);
}

function sectionJournalAccount(string $code, string $classification, string $type, string $normalBalance): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => ucfirst(str_replace('_', ' ', $classification)),
        'type' => $type,
        'classification' => $classification,
        'normal_balance' => $normalBalance,
        'is_active' => true,
        'approved_at' => now(),
    ]);
}

test('the journal entry page presents the three workflows in the approved order', function () {
    $admin = sectionJournalEmployee([
        'accounting.view',
        'accounting.create-journal-entry',
        'accounting.approve-journal-entry',
        'accounting.post-journal-entry',
        'accounting.maintain-opening-books',
        'accounting.approve-opening-books',
    ]);

    $this->actingAs($admin)
        ->get(route('accounting.journal-entry'))
        ->assertOk()
        ->assertSeeInOrder([
            'Regular Journal Entries',
            'Opening Balance / Cutover Journal',
            'Source-Generated Journals',
        ]);
});

test('view-only accounting users see every workflow with read-only guidance and no privileged actions', function () {
    sectionJournalAccount('1000', 'cash', 'Asset', 'debit');
    sectionJournalAccount('4000', 'sales', 'Revenue', 'credit');

    $viewer = sectionJournalEmployee(['accounting.view']);
    $this->actingAs($viewer);

    Livewire::test(JournalEntry::class)
        ->assertSeeInOrder([
            'Regular Journal Entries',
            'Opening Balance / Cutover Journal',
            'Source-Generated Journals',
        ])
        ->assertSee('View-only access')
        ->assertSee('Manual journal drafts (read-only)')
        ->assertDontSee('Save draft')
        ->assertDontSee('Save opening journal')
        ->assertDontSee('Generate journal');
});

test('preparers and posters keep their workflow actions in the reorganized sections', function () {
    sectionJournalAccount('1000', 'cash', 'Asset', 'debit');
    sectionJournalAccount('4000', 'sales', 'Revenue', 'credit');

    $preparer = sectionJournalEmployee(['accounting.view', 'accounting.create-journal-entry']);
    Livewire::actingAs($preparer)->test(JournalEntry::class)
        ->assertSee('Regular Journal Entries')
        ->assertSee('New Manual Journal Draft')
        ->assertDontSee('Save opening journal')
        ->assertDontSee('Generate journal');

    $poster = sectionJournalEmployee(['accounting.view', 'accounting.post-journal-entry']);
    Livewire::actingAs($poster)->test(JournalEntry::class)
        ->assertSee('Source-Generated Journals')
        ->assertSee('One journal per source record')
        ->assertDontSee('New Manual Journal Draft');
});
