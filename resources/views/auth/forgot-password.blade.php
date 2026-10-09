<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot password | Fabellon Construction</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f4f5f7; color: #292d32; font-family: Arial, sans-serif; }
        main { box-sizing: border-box; width: min(100% - 32px, 430px); padding: 36px; background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgb(0 0 0 / 10%); }
        .logo { display: block; width: 100px; height: auto; margin: 0 auto 20px; }
        h1 { margin: 0 0 10px; text-align: center; font-size: 24px; }
        p { line-height: 1.5; }
        label { display: block; margin: 22px 0 8px; font-weight: 600; }
        input { box-sizing: border-box; width: 100%; padding: 12px; border: 1px solid #c9cdd2; border-radius: 6px; font: inherit; }
        button { width: 100%; margin-top: 18px; padding: 13px; border: 0; border-radius: 6px; background: #c9a227; color: #fff; font-weight: 700; cursor: pointer; }
        .feedback { padding: 12px; border-radius: 6px; background: #edf7ed; color: #245b2a; }
        .error { color: #a12622; }
        .back { display: block; margin-top: 20px; text-align: center; color: #675315; }
    </style>
</head>
<body>
    <main>
        <img class="logo" src="{{ asset('image/company-logo.png') }}" alt="Fabellon Construction and Development Corporation">
        <h1>Forgot your password?</h1>
        <p>Enter your employee account email and, if an account exists, we’ll send you a password reset link.</p>

        @if (session('status'))
            <p class="feedback" role="status">{{ session('status') }}</p>
        @endif

        <form action="{{ route('password.email') }}" method="POST">
            @csrf
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus @error('email') aria-invalid="true" @enderror>
            @error('email') <p class="error" role="alert">{{ $message }}</p> @enderror
            <button type="submit">Send password reset link</button>
        </form>

        <a class="back" href="{{ route('login') }}">Back to login</a>
    </main>
</body>
</html>
