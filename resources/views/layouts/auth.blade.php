<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | Fabellion Construction</title>
    <style>
        * { box-sizing: border-box; }
        html { min-height: 100%; }
        body {
            min-height: 100vh;
            min-height: 100svh;
            margin: 0;
            padding: 28px 20px;
            display: grid;
            place-items: center;
            background:
                linear-gradient(rgba(20, 80, 35, 0.52), rgba(20, 80, 35, 0.52)),
                url("{{ asset('image/construction-bg.jpg') }}") center / cover no-repeat fixed;
            color: #222;
            font-family: Arial, Helvetica, sans-serif;
        }
        ::selection { background: #dcebdd; color: #183d1a; }
        .auth-card {
            width: min(100%, 430px);
            padding: 30px 40px 26px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
        }
        .auth-logo { display: block; width: 130px; height: 130px; margin: 0 auto 10px; object-fit: contain; }
        .auth-heading { margin: 0 0 8px; text-align: center; font-size: 25px; line-height: 1.2; }
        .auth-intro { margin: 0 0 24px; color: #595959; font-size: 14px; line-height: 1.5; }
        .auth-message { margin: 0 0 18px; padding: 11px 12px; border-radius: 6px; font-size: 13px; line-height: 1.5; }
        .auth-message p { margin: 0; }
        .auth-message p + p { margin-top: 6px; }
        .auth-message-success { background: #edf6ee; color: #245b2a; }
        .auth-message-error { background: #fff0f0; color: #a12622; }
        .auth-form { display: grid; gap: 16px; }
        .auth-field { display: grid; gap: 7px; }
        .auth-field label { color: #333; font-size: 13px; font-weight: 600; }
        .auth-field input {
            width: 100%;
            height: 46px;
            padding: 0 14px;
            border: 1px solid #d5d5d5;
            border-radius: 6px;
            background: #fff;
            color: #222;
            font: inherit;
            font-size: 14px;
            caret-color: #3f7f3d;
        }
        .auth-field input[readonly] { background: #f4f6f4; color: #4d544d; }
        .auth-field input:focus-visible { outline: 3px solid #3f7f3d; outline-offset: 2px; border-color: #3f7f3d; }
        .auth-field input[aria-invalid="true"] { border-color: #a12622; }
        .auth-field-error { margin: -2px 0 0; color: #a12622; font-size: 12px; line-height: 1.4; }
        .auth-submit {
            width: 100%;
            min-height: 46px;
            border: 0;
            border-radius: 6px;
            background: #3f7f3d;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.3px;
            cursor: pointer;
            transition: background-color 180ms ease, transform 180ms ease;
        }
        .auth-submit:hover { background: #326832; }
        .auth-submit:active { transform: translateY(1px); }
        .auth-submit:disabled { background: #6a9c68; cursor: wait; }
        .auth-submit:focus-visible, .auth-link:focus-visible { outline: 3px solid #3f7f3d; outline-offset: 3px; }
        .auth-link { color: #326832; font-size: 14px; text-underline-offset: 3px; }
        .auth-secondary { display: block; margin-top: 20px; text-align: center; }
        .auth-footer { margin: 22px 0 0; color: #595959; font-size: 11px; line-height: 1.5; text-align: center; }
        .auth-button-link { display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; }
        @media (max-width: 500px) {
            body { padding: 18px 16px; background-attachment: scroll; }
            .auth-card { padding: 26px 24px 22px; }
            .auth-logo { width: 112px; height: 112px; }
            .auth-heading { font-size: 23px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: 0.01ms !important; }
        }
    </style>
</head>
<body>
    <main class="auth-card">
        <img class="auth-logo" src="{{ asset('image/company-logo.png') }}" alt="Fabellion Construction and Development Corporation">
        <h1 class="auth-heading">@yield('heading')</h1>
        <p class="auth-intro">@yield('intro')</p>

        @if (session('status'))
            <div class="auth-message auth-message-success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="auth-message auth-message-error" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @yield('content')

        <p class="auth-footer">© 2026 Fabellion Construction and Development Corporation.<br>All rights reserved.</p>
    </main>
</body>
</html>
