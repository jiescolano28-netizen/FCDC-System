<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EmployeePasswordResetController extends Controller
{
    public function create(Request $request, string $token): View
    {
        $email = $request->query('email', '');
        $broker = Password::broker('employees');
        $employee = $email !== '' ? $broker->getUser(['email' => $email]) : null;

        return view('auth.reset-password', [
            'email' => $email,
            'token' => $token,
            'validToken' => $employee && $broker->tokenExists($employee, $token),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('employees')->reset(
            $credentials,
            function (Employee $employee, string $password): void {
                $employee->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('status', 'Your password has been reset. You can now sign in.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }
}
