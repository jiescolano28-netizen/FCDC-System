<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">


    <title>Login | Fabellon Construction</title>


    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;


            /* Green background */
            background:
                linear-gradient(rgba(20, 80, 35, 0.45),
                    rgba(20, 80, 35, 0.45)),
                url("{{ asset('image/construction-bg.jpg') }}");


            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;


            display: flex;
            justify-content: center;
            align-items: center;


            padding: 30px;
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }

        button:focus-visible,
        input:focus-visible,
        a:focus-visible {
            outline: 3px solid #3f7f3d;
            outline-offset: 2px;
        }


        /* Main login card */
        .login-card {
            width: 100%;
            max-width: 430px;


            background: #ffffff;


            padding: 35px 40px;


            border-radius: 12px;


            box-shadow:
                0 15px 40px rgba(0, 0, 0, 0.25);

            opacity: 0;
            transform: translateY(18px);
            animation: cardIn 0.55s ease forwards;
        }

        @keyframes cardIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }


        /* Company logo */
        .logo-container {
            text-align: center;
            margin-bottom: 15px;
        }


        .company-logo {
            width: 190px;
            height: 190px;
            object-fit: contain;
        }


        /* Welcome text */
        .welcome {
            text-align: center;
            margin-bottom: 28px;
        }


        .welcome h1 {
            font-size: 25px;
            color: #222;
            margin-bottom: 7px;
        }


        .welcome p {
            font-size: 14px;
            color: #777;
        }


        /* Error message */
        .error-message {
            background: #ffe9e9;
            border: 1px solid #ffbcbc;
            color: #b00020;
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 18px;
            font-size: 13px;
            animation: shake 0.4s ease;
        }

        .success-message {
            margin-bottom: 18px;
            padding: 10px 12px;
            border: 1px solid #b9d7bb;
            border-radius: 6px;
            background: #edf6ee;
            color: #245b2a;
            font-size: 13px;
            line-height: 1.5;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            20% {
                transform: translateX(-6px);
            }

            40% {
                transform: translateX(6px);
            }

            60% {
                transform: translateX(-4px);
            }

            80% {
                transform: translateX(4px);
            }
        }


        .error-message p {
            margin: 3px 0;
        }

        .field-error {
            color: #b00020;
            font-size: 12px;
            margin-top: 5px;
        }


        /* Form */
        .form-group {
            margin-bottom: 20px;
        }


        .form-group label {
            display: block;


            font-size: 13px;
            font-weight: 600;


            color: #333;


            margin-bottom: 7px;

            transition: color 0.2s ease;
        }

        .form-group:focus-within label {
            color: #3f7f3d;
        }


        .input-wrapper {
            position: relative;
        }


        .form-group input {
            width: 100%;
            height: 46px;


            padding: 0 14px;


            border: 1px solid #d5d5d5;
            border-radius: 6px;


            font-size: 14px;


            outline: none;


            transition: 0.2s;
        }


        .form-group input:focus {
            border-color: #3f7f3d;


            box-shadow:
                0 0 0 2px rgba(63, 127, 61, 0.12);
        }


        /* Password eye button */
        .password-input {
            padding-right: 45px !important;
        }


        .show-password {
            position: absolute;


            right: 12px;
            top: 50%;


            transform: translateY(-50%);


            border: none;
            background: none;


            cursor: pointer;


            font-size: 17px;


            color: #777;

            display: flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 5px;
            transition: color 0.15s ease, background 0.15s ease;
        }

        .show-password:hover {
            color: #3f7f3d;
            background: rgba(63, 127, 61, 0.08);
        }

        .show-password svg {
            width: 18px;
            height: 18px;
        }

        .show-password .icon-off {
            display: none;
        }

        .show-password[aria-pressed="true"] .icon-on {
            display: none;
        }

        .show-password[aria-pressed="true"] .icon-off {
            display: block;
        }


        /* Options */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;


            margin-bottom: 25px;


            font-size: 12px;
        }


        .remember-me {
            display: flex;
            align-items: center;
            gap: 6px;


            color: #555;


            cursor: pointer;
        }


        .remember-me input {
            accent-color: #3f7f3d;
        }


        .forgot-link {
            color: #3f7f3d;


            text-decoration: none;


            font-weight: 500;
        }


        .forgot-link:hover {
            text-decoration: underline;
        }


        /* Login button */
        .btn-submit {
            width: 100%;
            height: 46px;


            border: none;
            border-radius: 6px;


            background: #3f7f3d;
            color: white;


            font-size: 14px;
            font-weight: bold;


            letter-spacing: 0.5px;


            cursor: pointer;


            transition: 0.2s;

            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }


        .btn-submit:hover {
            background: #326832;
        }


        .btn-submit:active {
            transform: scale(0.99);
        }

        .btn-submit:disabled {
            background: #6a9c68;
            cursor: default;
        }

        .btn-spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.5);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        .btn-submit.is-loading .btn-spinner {
            display: inline-block;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }


        /* Footer */
        .login-footer {
            text-align: center;


            margin-top: 25px;


            font-size: 11px;


            color: #777;


            line-height: 1.5;
        }


        /* Mobile */
        @media (max-width: 500px) {


            body {
                padding: 20px;
            }


            .login-card {
                padding: 30px 25px;
            }


            .company-logo {
                width: 130px;
                height: 130px;
            }
        }

        /* =================================
   MOBILE RESPONSIVE SIDEBAR
   ================================= */

        @media (max-width: 768px) {

            .sidebar {
                width: 72px !important;
                min-width: 72px !important;
                max-width: 72px !important;
            }

            .brand {
                justify-content: center;
                padding: 14px 8px;
            }

            .brand-text {
                display: none !important;
            }

            .company-logo {
                width: 42px;
                height: 42px;
            }

            .sidebar-toggle {
                display: none;
            }

            .nav {
                padding: 12px 8px;
            }

            .nav-item {
                justify-content: center;
                padding: 10px 8px;
            }

            .nav-label,
            .nav-arrow {
                display: none !important;
            }

            .nav-icon {
                width: auto;
                font-size: 17px;
            }

            .nav-dropdown {
                padding-left: 0;
            }

            .nav-child {
                justify-content: center;
            }

            .sidebar-footer {
                padding: 12px 6px;
                text-align: center;
            }

            .sidebar-footer {
                font-size: 0;
            }

            .sidebar-footer strong {
                display: none;
            }

            main {
                flex: 1 1 0% !important;
                width: auto !important;
                min-width: 0 !important;
                max-width: none !important;
                padding: 16px !important;
            }

            .grid.grid-2,
            .grid.grid-3 {
                grid-template-columns: 1fr !important;
                width: 100% !important;
            }

            .card {
                width: 100% !important;
                min-width: 0 !important;
            }
        }
    </style>
</head>


<body>


    <main class="login-card">


        <!-- COMPANY LOGO -->
        <div class="logo-container">
            <img src="{{ asset('image/company-logo.png') }}" class="company-logo"
                alt="Fabellon Construction and Development Corporation Logo">
        </div>




        <!-- WELCOME -->
        <div class="welcome">
            <h1>Welcome Back!</h1>


            <p>Please sign in to continue</p>
        </div>




        @if (session('status'))
            <div class="success-message" role="status">{{ session('status') }}</div>
        @endif

        <!-- ERROR MESSAGE -->
        @if ($errors->any())
            <div class="error-message">


                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach


            </div>
        @endif




        <!-- LOGIN FORM -->
        <form action="{{ route('login.submit') }}" method="POST" id="loginForm">


            @csrf




            <!-- EMAIL -->
            <div class="form-group">


                <label for="email">
                    Email
                </label>


                <input type="email" id="email" name="email" placeholder="Enter your email"
                    value="{{ old('email') }}" required autocomplete="email"
                    @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')
                    <p class="field-error" id="email-error">{{ $message }}</p>
                @enderror


            </div>




            <!-- PASSWORD -->
            <div class="form-group">


                <label for="password">
                    Password
                </label>


                <div class="input-wrapper">


                    <input type="password" id="password" name="password" class="password-input"
                        placeholder="Enter your password" required autocomplete="current-password">


                    <button type="button" class="show-password" aria-pressed="false" aria-label="Show password"
                        onclick="togglePassword(this)">
                        <svg class="icon-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            aria-hidden="true">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" stroke-linecap="round"
                                stroke-linejoin="round" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg class="icon-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            aria-hidden="true">
                            <path
                                d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a20.3 20.3 0 0 1 5.06-6.06M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a20.3 20.3 0 0 1-3.15 4.44"
                                stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24" stroke-linecap="round" stroke-linejoin="round" />
                            <line x1="1" y1="1" x2="23" y2="23" stroke-linecap="round" />
                        </svg>
                    </button>


                </div>


            </div>




            <!-- REMEMBER ME -->
            <div class="form-options">


                <label class="remember-me">


                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>


                    <span>Remember Me</span>


                </label>
                <a class="forgot-link" href="{{ route('password.request') }}">Forgot password?</a>
            </div>




            <!-- LOGIN BUTTON -->
            <button type="submit" class="btn-submit" id="submitBtn">
                <span class="btn-spinner" aria-hidden="true"></span>
                <span class="btn-label">LOGIN</span>
            </button>




        </form>




        <!-- FOOTER -->
        <div class="login-footer">


            © 2026 Fabellon Construction and Development Corporation.<br>
            All rights reserved.


        </div>


    </main>




    <!-- PASSWORD SHOW/HIDE + SUBMIT FEEDBACK -->
    <script>
        function togglePassword(btn) {


            const password =
                document.getElementById('password');


            if (password.type === 'password') {


                password.type = 'text';
                btn.setAttribute('aria-pressed', 'true');
                btn.setAttribute('aria-label', 'Hide password');


            } else {


                password.type = 'password';
                btn.setAttribute('aria-pressed', 'false');
                btn.setAttribute('aria-label', 'Show password');


            }


        }

        // Show a loading state on the button while the form submits,
        // so a slow connection still gives clear feedback.
        (function() {
            var form = document.getElementById('loginForm');
            var submitBtn = document.getElementById('submitBtn');
            var label = submitBtn.querySelector('.btn-label');

            form.addEventListener('submit', function() {
                if (submitBtn.disabled) return;
                submitBtn.disabled = true;
                submitBtn.classList.add('is-loading');
                label.textContent = 'SIGNING IN...';
            });
        })();
    </script>


</body>

</html>
