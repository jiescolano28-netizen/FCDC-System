<?php

use App\Livewire\Profile\ProfilePage;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function profileTestEmployee(string $username, string $password = 'current-password'): Employee
{
    return Employee::create([
        'username' => $username,
        'email' => $username.'@example.test',
        'password' => Hash::make($password),
    ]);
}

test('profile is available to every authenticated employee and has no other employee profile route', function () {
    $this->get(route('profile'))->assertRedirect(route('login'));

    $employee = profileTestEmployee('profile-owner');
    $other = profileTestEmployee('different-employee');

    $this->actingAs($employee)
        ->get(route('profile'))
        ->assertOk()
        ->assertSee('My profile')
        ->assertSee('Use at least 12 characters with uppercase and lowercase letters, a number, and a symbol.')
        ->assertSee('profile-owner')
        ->assertSee('View profile for profile-owner')
        ->assertDontSee('<span class="nav-label">My profile</span>', false)
        ->assertDontSee('different-employee@example.test');

    $this->actingAs($employee)
        ->get('/profile/'.$other->id)
        ->assertNotFound();

    expect(route('profile'))->toBe(url('/profile'));
});

test('employee can update only their own unique username', function () {
    $employee = profileTestEmployee('profile-before');
    profileTestEmployee('profile-taken');

    Livewire::actingAs($employee)
        ->test(ProfilePage::class)
        ->set('username', 'profile-after')
        ->call('updateUsername')
        ->assertHasNoErrors()
        ->assertSee('Username updated.');

    expect($employee->fresh()->username)->toBe('profile-after');

    Livewire::actingAs($employee)
        ->test(ProfilePage::class)
        ->set('username', 'profile-taken')
        ->call('updateUsername')
        ->assertHasErrors(['username' => 'unique']);

    expect($employee->fresh()->username)->toBe('profile-after');
});

test('password updates require the current password and log only a safe event', function () {
    $employee = profileTestEmployee('password-owner');
    $this->actingAs($employee);

    Livewire::test(ProfilePage::class)
        ->set('currentPassword', 'wrong-password')
        ->set('newPassword', 'a-new-private-password')
        ->set('newPassword_confirmation', 'a-new-private-password')
        ->call('updatePassword')
        ->assertHasErrors('currentPassword')
        ->assertSee('That current password doesn’t match. Check it and try again.');

    expect(Hash::check('current-password', $employee->fresh()->password))->toBeTrue()
        ->and(Activity::query()->where('description', 'Password changed')->exists())->toBeFalse();
    Livewire::test(ProfilePage::class)
        ->set('currentPassword', 'current-password')
        ->set('newPassword', 'Abcdef123!XY')
        ->set('newPassword_confirmation', 'Abcdef123!XZ')
        ->call('updatePassword')
        ->assertHasErrors('newPassword_confirmation')
        ->assertSee('The new passwords don’t match. Enter the same password in both fields.');

    expect(Hash::check('current-password', $employee->fresh()->password))->toBeTrue()
        ->and(Activity::query()->where('description', 'Password changed')->exists())->toBeFalse();

    Livewire::test(ProfilePage::class)
        ->set('currentPassword', 'current-password')
        ->set('newPassword', 'Abcdef123!XY')
        ->set('newPassword_confirmation', 'Abcdef123!XY')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertSee('Password updated.');

    $employee->refresh();
    $activity = Activity::query()->where('description', 'Password changed')->firstOrFail();

    $this->assertAuthenticatedAs($employee);
    expect(Hash::check('Abcdef123!XY', $employee->password))->toBeTrue()
        ->and($activity->causer_id)->toBe($employee->id)
        ->and($activity->subject_id)->toBe($employee->id)
        ->and(json_encode($activity->properties))->not->toContain('Abcdef123!XY')
        ->and(json_encode($activity->properties))->not->toContain('current-password');
});

test('password save failures explain that the password was not changed', function () {
    $employee = profileTestEmployee('password-save-failure-owner');
    $this->actingAs($employee);

    DB::shouldReceive('transaction')
        ->once()
        ->andThrow(new RuntimeException('Database unavailable.'));

    Livewire::test(ProfilePage::class)
        ->set('currentPassword', 'current-password')
        ->set('newPassword', 'Abcdef123!XY')
        ->set('newPassword_confirmation', 'Abcdef123!XY')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertSee('We couldn’t update your password. It hasn’t been changed. Try again. If the problem continues, contact support.')
        ->assertSee('role="alert"', false);

    expect(Hash::check('current-password', $employee->fresh()->password))->toBeTrue()
        ->and(Activity::query()->where('description', 'Password changed')->exists())->toBeFalse();
});
test('profile password updates reject passwords missing any required character type', function () {
    $employee = profileTestEmployee('password-policy-owner');
    $this->actingAs($employee);

    foreach (['short', 'Abcde123!XY', 'alllowercase123!', 'ALLUPPERCASE123!', 'NoNumbersHere!!', 'NoSymbolsHere123'] as $weakPassword) {
        Livewire::test(ProfilePage::class)
            ->set('currentPassword', 'current-password')
            ->set('newPassword', $weakPassword)
            ->set('newPassword_confirmation', $weakPassword)
            ->call('updatePassword')
            ->assertHasErrors('newPassword');
    }

    expect(Hash::check('current-password', $employee->fresh()->password))->toBeTrue()
        ->and(Activity::query()->where('description', 'Password changed')->exists())->toBeFalse();
});

test('personal activity lists only events performed by the signed-in employee', function () {
    $employee = profileTestEmployee('activity-owner');
    $other = profileTestEmployee('activity-other');

    activity()->causedBy($employee)->performedOn($employee)->log('Action performed by me');
    activity()->causedBy($other)->performedOn($employee)->log('Action performed by another employee');

    Livewire::actingAs($employee)
        ->test(ProfilePage::class)
        ->assertSee('Action performed by me')
        ->assertDontSee('Action performed by another employee');

    expect(Activity::query()->where('description', 'Action performed by me')->firstOrFail()->causer_id)
        ->toBe($employee->id);
});
