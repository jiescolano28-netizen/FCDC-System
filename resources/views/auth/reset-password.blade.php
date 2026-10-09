@extends('layouts.auth')

@section('title', 'Reset password')
@section('heading', $validToken ? 'Create a new password' : 'Reset link unavailable')
@section('intro', $validToken ? 'Choose a new password for your employee account.' : 'This reset link can’t be used. Request a new link and try again.')

@section('content')
    @if ($validToken)
        <form class="auth-form" action="{{ route('password.update') }}" method="POST">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="auth-field">
                <label for="email">Employee email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="username" readonly required>
            </div>
            <div class="auth-field">
                <label for="password">New password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')
                    <p class="auth-field-error" id="password-error">{{ $message }}</p>
                @enderror
            </div>
            <div class="auth-field">
                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
            </div>
            <button class="auth-submit" type="submit">Update password</button>
        </form>
    @else
        @if (!$errors->has('email'))
            <div class="auth-message auth-message-error" role="alert">This password reset link is invalid or has expired.</div>
        @endif
        <a class="auth-submit auth-button-link" href="{{ route('password.request') }}">Request a new reset link</a>
    @endif

    <a class="auth-link auth-secondary" href="{{ route('login') }}">Back to login</a>
@endsection
