<?php

use App\Livewire\TaxCompliance\VatSummary;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createVatSummaryEmployee(): Employee
{
    return Employee::create([
        'username' => 'vat.summary.viewer',
        'email' => 'vat.summary@example.com',
        'password' => Hash::make('vat-password'),
    ]);
}

test('VAT summary is available only through its authenticated named Livewire route', function () {
    $this->get(route('tax.vat-summary'))->assertRedirect(route('login'));

    $this->actingAs(createVatSummaryEmployee())
        ->get(route('tax.vat-summary'))
        ->assertOk()
        ->assertSee('VAT Summary')
        ->assertSee('Tax Compliance demonstrations', false)
        ->assertSee('VAT Records')
        ->assertSee('not evidence of actual taxable activity', false)
        ->assertSee(route('tax.vat-records'), false);
});

test('monthly and quarterly selections show the matching illustrative summary', function () {
    $this->actingAs(createVatSummaryEmployee());

    Livewire::test(VatSummary::class)
        ->assertSet('periodType', 'monthly')
        ->assertSet('selectedPeriod', 'August 2026')
        ->assertSee('₱40,600.00')
        ->assertSee('₱4,872.00')
        ->assertSee('₱45,472.00')
        ->set('selectedPeriod', 'July 2026')
        ->assertSee('₱14,200.00')
        ->assertSee('₱1,704.00')
        ->assertSee('₱15,904.00')
        ->set('periodType', 'quarterly')
        ->assertSet('selectedPeriod', 'Q3 2026')
        ->assertSee('Q2 2026')
        ->assertSee('₱54,800.00')
        ->assertSee('₱6,576.00')
        ->assertSee('₱61,376.00')
        ->set('selectedPeriod', 'Q2 2026')
        ->assertSee('₱22,000.00')
        ->assertSee('₱2,640.00')
        ->assertSee('₱24,640.00');
});
