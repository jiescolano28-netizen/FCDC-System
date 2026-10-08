<?php

use App\Livewire\Settings\SettingsPage;
use App\Models\Employee;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class)->beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function createSettingsEmployee(array $attributes = []): Employee
{
    return grantEmployeeTestPermissions(Employee::create(array_merge([
        'username' => 'settings.employee',
        'email' => 'settings@example.com',
        'password' => Hash::make('settings-password'),
    ], $attributes)), ['settings.view', 'settings.update', 'dashboard.view', 'inventory.view']);
}

test('settings is an authenticated named page using the shared shell', function () {
    $this->get(route('settings'))->assertRedirect(route('login'));

    $employee = createSettingsEmployee();

    $this->actingAs($employee)->get(route('settings'))->assertOk()
        ->assertSee('Settings')
        ->assertSee('settings.employee')
        ->assertSee(route('dashboard'), false)
        ->assertSee(route('inventory.management'), false)
        ->assertSee(route('settings'), false)
        ->assertSee('Temporary demo values', false)
        ->assertSee('Team members');
});

test('settings edits survive navigation and a fresh page request in the employee session', function () {
    $this->actingAs(createSettingsEmployee());

    Livewire::test(SettingsPage::class)
        ->set('companyName', 'North Shore Materials')
        ->set('address', '18 Harbor Road')
        ->set('phone', '555-0142')
        ->set('taxRate', '8.5')
        ->set('currency', 'PHP')
        ->assertHasNoErrors();

    $this->get(route('inventory.management'))->assertOk();

    $this->get(route('settings'))->assertOk()
        ->assertSee('North Shore Materials')
        ->assertSee('18 Harbor Road')
        ->assertSee('555-0142')
        ->assertSee('8.5')
        ->assertSee('PHP')
        ->assertSee('not persisted', false);
});

test('logout clears temporary company settings before another employee signs in', function () {
    $firstEmployee = createSettingsEmployee();
    $secondEmployee = createSettingsEmployee([
        'username' => 'other.employee',
        'email' => 'other@example.com',
    ]);

    $this->actingAs($firstEmployee);

    Livewire::test(SettingsPage::class)
        ->set('companyName', 'Private first employee value')
        ->set('currency', 'EUR');

    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->post(route('login.submit'), [
        'email' => $secondEmployee->email,
        'password' => 'settings-password',
    ])->assertRedirect(route('dashboard'));

    $this->get(route('settings'))->assertOk()
        ->assertSee('Fabellion Construction and Development Corp.')
        ->assertSee('PHP')
        ->assertDontSee('Private first employee value')
        ->assertSee('other.employee');
});

test('inventory management uses the shared shell without changing its named route', function () {
    $employee = createSettingsEmployee();

    $this->actingAs($employee)->get(route('inventory.management'))->assertOk()
        ->assertSee('Inventory management')
        ->assertSee('settings.employee')
        ->assertSee(route('settings'), false)
        ->assertSee('aria-current="page"', false);
});

test('invalid tax rates are not retained when another setting changes', function () {
    $this->actingAs(createSettingsEmployee());

    Livewire::test(SettingsPage::class)
        ->set('taxRate', '120')
        ->assertHasErrors('taxRate')
        ->set('companyName', 'Corrected company name')
        ->assertHasErrors('taxRate');

    $this->get(route('settings'))->assertOk()
        ->assertSee('Fabellion Construction and Development Corp.')
        ->assertSee('12');
});
