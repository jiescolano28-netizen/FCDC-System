@extends('layouts.auth')

@section('title', 'Forgot password')
@section('heading', 'Forgot your password?')
@section('intro', 'Enter your employee account email. If an account exists, we’ll send a password reset link.')

@section('content')
    <form class="auth-form" action="{{ route('password.email') }}" method="POST">
        @csrf
        <div class="auth-field">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus @error('email') aria-invalid="true" @enderror>
            @error('email')
                <p class="auth-field-error">{{ $message }}</p>
            @enderror
        </div>
        <button class="auth-submit" type="submit">Send password reset link</button>
    </form>

    <a class="auth-link auth-secondary" href="{{ route('login') }}">Back to login</a>
@endsection
