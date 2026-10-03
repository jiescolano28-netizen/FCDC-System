<?php

use App\Models\Employee;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class)->beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

function createEmployeeForAuthentication(array $attributes = []): Employee
{
    return Employee::create(array_merge([
        'username' => 'field.employee',
        'email' => 'employee@example.com',
        'password' => Hash::make('correct-horse-battery'),
    ], $attributes));
}

test('employees with provisioned accounts can sign in', function () {
    $employee = createEmployeeForAuthentication();

    $this->post(route('login.submit'), [
        'email' => $employee->email,
        'password' => 'correct-horse-battery',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($employee);
});

test('public employee registration is unavailable', function () {
    $this->get('/register')->assertNotFound();

    $this->get(route('login'))->assertOk()
        ->assertDontSee('Register Account');
});

test('login honors remember me and failed credentials do not authenticate', function () {
    $employee = createEmployeeForAuthentication();

    $this->from(route('login'))->post(route('login.submit'), [
        'email' => $employee->email,
        'password' => 'incorrect-password',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $sessionId = session()->getId();

    $this->post(route('login.submit'), [
        'email' => $employee->email,
        'password' => 'correct-horse-battery',
        'remember' => '1',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($employee);
    expect(session()->getId())->not->toBe($sessionId)
        ->and($employee->fresh()->getRememberToken())->not->toBeNull();
});

test('logout invalidates the authenticated employee session', function () {
    $employee = createEmployeeForAuthentication();
    $this->actingAs($employee);

    $sessionId = session()->getId();

    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
    expect(session()->getId())->not->toBe($sessionId);
});

test('application screens and inventory endpoints require authentication', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('inventory.index'))->assertRedirect(route('login'));
    $this->post(route('inventory.store'))->assertRedirect(route('login'));
    $this->put(route('inventory.update', 1))->assertRedirect(route('login'));
    $this->delete(route('inventory.destroy', 1))->assertRedirect(route('login'));
});

test('authenticated application shell identifies the employee and offers supported navigation', function () {
    $employee = createEmployeeForAuthentication(['username' => 'shell.employee']);

    $this->actingAs($employee)->get(route('dashboard'))->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Inventory')
        ->assertSee('shell.employee')
        ->assertSee(route('logout'), false)
        ->assertSee('name="_token"', false);
});
