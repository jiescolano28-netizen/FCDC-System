<?php

namespace App\Livewire\Profile;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

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
        $validated = $this->validate([
            'currentPassword' => ['required', 'current_password'],
            'newPassword' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        DB::transaction(function () use ($validated): void {
            $employee = Employee::findOrFail(auth()->id());
            $employee->password = Hash::make($validated['newPassword']);
            $employee->save();

            activity()
                ->causedBy($employee)
                ->performedOn($employee)
                ->log('Password changed');
        });

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
