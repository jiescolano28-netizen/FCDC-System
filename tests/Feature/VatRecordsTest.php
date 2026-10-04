<?php

use App\Livewire\TaxCompliance\VatRecords;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createVatRecordsEmployee(): Employee
{
    return Employee::create([
        'username' => 'vat.viewer',
        'email' => 'vat@example.com',
        'password' => Hash::make('vat-password'),
    ]);
}

test('VAT records are available only through their authenticated named Livewire route', function () {
    $this->get(route('tax.vat-records'))->assertRedirect(route('login'));

    $this->actingAs(createVatRecordsEmployee())
        ->get(route('tax.vat-records'))
        ->assertOk()
        ->assertSee('VAT Records')
        ->assertSee('Tax Compliance demonstrations', false)
        ->assertSee('not evidence of taxable activity', false)
        ->assertSee(route('tax.vat-records'), false);
});

test('reference search and tax-period filter show only matching demonstration records', function () {
    $this->actingAs(createVatRecordsEmployee());

    Livewire::test(VatRecords::class)
        ->assertSee('VAT-2026-08-001')
        ->set('search', 'VAT-2026-08-002')
        ->assertSee('VAT-2026-08-002')
        ->assertDontSee('VAT-2026-08-001')
        ->set('search', 'q3 2026')
        ->assertSee('VAT-2026-08-003')
        ->assertDontSee('VAT-2026-06-001')
        ->set('search', '')
        ->set('period', 'Q2 2026')
        ->assertSee('VAT-2026-06-001')
        ->assertSee('VAT-2026-05-001')
        ->assertDontSee('VAT-2026-08-001');
});

test('transaction date and combined filters preserve usable empty results', function () {
    $this->actingAs(createVatRecordsEmployee());

    Livewire::test(VatRecords::class)
        ->set('transactionDate', '2026-08-12')
        ->assertSee('VAT-2026-08-002')
        ->assertDontSee('VAT-2026-08-001')
        ->set('period', 'Q2 2026')
        ->assertSee('No VAT records match the selected filters.')
        ->assertSee('Showing 0 of 7 placeholder records')
        ->set('transactionDate', '')
        ->assertSee('VAT-2026-06-001')
        ->assertDontSee('VAT-2026-08-002');
});

test('a known demonstration record opens and closes its detail modal', function () {
    $this->actingAs(createVatRecordsEmployee());

    Livewire::test(VatRecords::class)
        ->call('viewRecord', 'VAT-2026-08-001')
        ->assertSet('selectedRecord.reference', 'VAT-2026-08-001')
        ->assertSee('₱12,500.00')
        ->assertSee('₱1,500.00')
        ->call('closeDetails')
        ->assertSet('selectedRecord', null)
        ->assertDontSee('VAT Record Details');
});

test('unknown reference values do not open a demonstration record', function () {
    $this->actingAs(createVatRecordsEmployee());

    Livewire::test(VatRecords::class)
        ->call('viewRecord', 'VAT-UNKNOWN')
        ->assertSet('selectedRecord', null)
        ->assertDontSee('VAT Record Details');
});
