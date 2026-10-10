<?php

use App\Livewire\TaxCompliance\VatReturnPreparation;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createVatReturnPreparationEmployee(): Employee
{
    return grantEmployeeTestPermissions(Employee::create([
        'username' => 'vat.return.viewer',
        'email' => 'vat.return@example.com',
        'password' => Hash::make('vat-password'),
    ]), ['tax.view']);
}

test('VAT return preparation is available only through its authenticated named route', function () {
    $this->get(route('tax.vat-return-preparation'))->assertRedirect(route('login'));

    $this->actingAs(createVatReturnPreparationEmployee())
        ->get(route('tax.vat-return-preparation'))
        ->assertOk()
        ->assertSee('VAT Return Preparation')
        ->assertSee('Tax Compliance', false)
        ->assertSee('VAT Summary')
        ->assertSee('href="'.route('tax.vat-return-preparation').'"', false)
        ->assertSee('Illustrative preparation interface', false)
        ->assertSee('does not replace eFPS, eBIRForms', false);
});

test('selected reporting period and company name update the illustrative preparation document', function () {
    $this->actingAs(createVatReturnPreparationEmployee());

    Livewire::test(VatReturnPreparation::class)
        ->assertSet('selectedPeriod', 'Q3 2026')
        ->assertSee('Fabellon Construction and Development Corporation')
        ->assertSee('₱54,800.00')
        ->assertSee('₱6,576.00')
        ->assertSee('₱61,376.00')
        ->set('selectedPeriod', 'Q2 2026')
        ->assertSee('Q2 2026')
        ->assertSee('₱22,000.00')
        ->assertSee('₱2,640.00')
        ->assertSee('₱24,640.00')
        ->set('companyName', 'Acme Demonstration Co.')
        ->assertSee('Acme Demonstration Co.')
        ->assertDontSee('Fabellon Construction and Development Corporation')
        ->assertSee('not a complete or official Form 2550Q', false)
        ->assertSee('not a filing or submission', false);
});
