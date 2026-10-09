<?php

use App\Models\Employee;
use App\Notifications\EmployeePasswordReset;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

    $activity = Activity::where('description', 'User logged in')->firstOrFail();
    expect($activity->causer)->toBeInstanceOf(Employee::class)
        ->and($activity->causer->is($employee))->toBeTrue()
        ->and($activity->subject->is($employee))->toBeTrue();
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

    $activity = Activity::where('description', 'User logged out')->firstOrFail();
    expect($activity->causer->is($employee))->toBeTrue()
        ->and($activity->subject->is($employee))->toBeTrue();
});

test('application screens and inventory endpoints require authentication', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('inventory.index'))->assertRedirect(route('login'));
    $this->post(route('inventory.store'))->assertRedirect(route('login'));
    $this->put(route('inventory.update', 1))->assertRedirect(route('login'));
    $this->delete(route('inventory.destroy', 1))->assertRedirect(route('login'));
});

test('authenticated application shell identifies the employee and offers supported navigation', function () {
    $employee = grantEmployeeTestPermissions(
        createEmployeeForAuthentication(['username' => 'shell.employee']),
        ['dashboard.view', 'inventory.view'],
    );

    $this->actingAs($employee)->get(route('dashboard'))->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Inventory')
        ->assertSee('shell.employee')
        ->assertSee(route('logout'), false)
        ->assertSee('name="_token"', false);
});

test('employees can receive roles and inherit their permissions', function () {
    $employee = createEmployeeForAuthentication();
    $permission = Permission::create([
        'name' => 'inventory.manage',
        'guard_name' => 'web',
    ]);
    $role = Role::create([
        'name' => 'inventory-manager',
        'guard_name' => 'web',
    ]);
    $role->givePermissionTo($permission);

    $employee->assignRole($role);

    expect($employee->hasRole('inventory-manager'))->toBeTrue()
        ->and($employee->can('inventory.manage'))->toBeTrue();
});

test('employees can request a private password reset link', function () {
    Notification::fake();
    $employee = createEmployeeForAuthentication();

    $this->get(route('login'))->assertOk()
        ->assertSee(route('password.request'));
    $this->get(route('password.request'))->assertOk()
        ->assertSee('Send password reset link');

    $known = $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => $employee->email])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', 'If an account exists for that email, a password reset link has been sent.');
    $unknown = $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'unknown@example.com'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', 'If an account exists for that email, a password reset link has been sent.');

    expect($known->getSession()->get('status'))->toBe($unknown->getSession()->get('status'))
        ->and(config('auth.passwords.employees.expire'))->toBe(30);

    Notification::assertSentTo($employee, EmployeePasswordReset::class, function (EmployeePasswordReset $notification) use ($employee) {
        $storedToken = DB::table('password_reset_tokens')->where('email', $employee->email)->value('token');
        $mail = $notification->toMail($employee);

        return $storedToken !== $notification->token
            && Hash::check($notification->token, $storedToken)
            && str_contains($mail->render(), url('/reset-password/'.$notification->token))
            && str_contains($mail->render(), 'Fabellon Construction');
    });

    Notification::assertCount(1);
});
