<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function showRegister()
    {
        return view('register');
    }

    public function register(Request $request)
    {
        // Validate registration form
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:employees,username',
            'email' => 'required|email|max:255|unique:employees,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Create employee account
        Employee::create([
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Redirect to login
        return redirect()
            ->route('login')
            ->with('success', 'Employee account created successfully. You can now login.');
    }
}