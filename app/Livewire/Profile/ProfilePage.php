<?php

namespace App\Livewire\Profile;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;
use Throwable;

class ProfilePage extends Component
{
    use WithPagination;

    public string $username = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPassword_confirmation = '';

    public function mount(): void
    {
        $this->username = auth()->user()->username;
    }

    public function updateUsername(): void
    {
        $employee = auth()->user();

        $validated = $this->validate([
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('employees', 'username')->ignore($employee->id),
            ],
        ]);

        $employee->username = $validated['username'];
        $employee->save();

        session()->flash('profile-status', 'Username updated.');
    }

    public function updatePassword(): void
    {
        $validated = $this->validate(
            [
                'currentPassword' => ['required', 'current_password'],
                'newPassword' => ['required', 'string', 'max:255', Password::min(12)->mixedCase()->numbers()->symbols()],
                'newPassword_confirmation' => ['required', 'same:newPassword'],
            ],
            [
                'currentPassword.required' => 'Enter your current password.',
                'currentPassword.current_password' => 'That current password doesn’t match. Check it and try again.',
                'newPassword.required' => 'Enter a new password.',
                'newPassword_confirmation.required' => 'Confirm your new password.',
                'newPassword_confirmation.same' => 'The new passwords don’t match. Enter the same password in both fields.',
            ],
            [
                'currentPassword' => 'current password',
                'newPassword' => 'new password',
                'newPassword_confirmation' => 'password confirmation',
            ],
        );

        try {
            DB::transaction(function () use ($validated): void {
                $employee = Employee::findOrFail(auth()->id());
                $employee->password = Hash::make($validated['newPassword']);
                $employee->save();

                activity()
                    ->causedBy($employee)
                    ->performedOn($employee)
                    ->log('Password changed');
            });
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('profile-error', 'We couldn’t update your password. It hasn’t been changed. Try again. If the problem continues, contact support.');

            return;
        }

        $this->reset(['currentPassword', 'newPassword', 'newPassword_confirmation']);
        session()->flash('profile-status', 'Password updated.');
    }

    public function render()
    {
        $employee = auth()->user();

        return view('livewire.profile.profile-page', [
            'activities' => Activity::query()
                ->with('subject')
                ->where('causer_type', $employee->getMorphClass())
                ->where('causer_id', $employee->getKey())
                ->orderByDesc('id')
                ->paginate(25),
        ])->layout('layouts.app', ['title' => 'My profile']);
    }
}

