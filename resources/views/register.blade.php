<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login Portal</title>
  <style>
    /* =========================================
       DEEP GREEN ROLEX CONCEPT PALETTE
       ========================================= */
:root {
    --rlx-green-dark: #003b22;
    --rlx-green-main: #006039;
    --rlx-green-bright: #008751;


    --rlx-gold: #c5a059;
    --rlx-gold-light: #e2c786;

    --text-light: #f4f6f5;
    --text-muted: #a3b8b0;

    --input-bg: rgba(0, 40, 24, 0.65);
    --border-gold: rgba(197, 160, 89, 0.35);

    --font-serif: 'Cinzel', 'Playfair Display', Georgia, serif;
    --font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* =========================================
   RESET
   ========================================= */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

/* =========================================
   BODY
   ========================================= */

body {
    background:
        radial-gradient(
            circle at center,
            var(--rlx-green-main) 0%,
            var(--rlx-green-dark) 85%
        );

    color: var(--text-light);
    font-family: var(--font-sans);

    min-height: 100vh;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 30px 20px;

    overflow-x: hidden;
}

/* =========================================
   MAIN CONTAINER
   ========================================= */

body > div {
    width: 100%;
    display: flex;
    justify-content: center;
}

/* =========================================
   REGISTRATION CARD
   ========================================= */

.login-card {
    width: 100%;
    max-width: 460px;

    background: rgba(0, 50, 30, 0.88);

    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);

    padding: 2.2rem 2.4rem;

    border-radius: 6px;

    border: 1px solid var(--border-gold);

    box-shadow:
        0 20px 50px rgba(0, 0, 0, 0.5),
        inset 0 0 1px 1px rgba(197, 160, 89, 0.2);

    /* IMPORTANT:
       Do NOT use fixed height here */
    height: auto;

    margin: 0;
}

/* =========================================
   HEADER
   ========================================= */

.brand-header {
    text-align: center;
    margin-bottom: 1.5rem;
}

.crown-icon {
    width: 38px;
    height: 38px;

    margin-bottom: 0.45rem;

    filter:
        drop-shadow(
            0 2px 4px rgba(0, 0, 0, 0.3)
        );
}

.brand-header h1 {
    font-family: var(--font-serif);

    font-size: 1.55rem;

    font-weight: 700;

    color: var(--rlx-gold);

    letter-spacing: 3px;

    text-transform: uppercase;

    margin-bottom: 0.35rem;
}

.brand-header p {
    font-size: 0.68rem;

    color: var(--text-muted);

    letter-spacing: 1.7px;

    line-height: 1.5;

    text-transform: uppercase;
}

/* =========================================
   ERROR MESSAGE
   ========================================= */

.error-message {
    background: rgba(150, 0, 0, 0.20);

    border: 1px solid rgba(255, 100, 100, 0.45);

    color: #ffb3b3;

    padding: 10px 12px;

    margin-bottom: 18px;

    border-radius: 3px;

    font-size: 0.75rem;

    line-height: 1.4;
}

.error-message p {
    margin: 2px 0;
}

/* =========================================
   FORM
   ========================================= */

form {
    width: 100%;
}

/* =========================================
   FORM GROUP
   ========================================= */

.form-group {
    width: 100%;

    margin-bottom: 1rem;
}

.form-group label {
    display: block;

    font-size: 0.7rem;

    font-weight: 600;

    color: var(--rlx-gold-light);

    text-transform: uppercase;

    letter-spacing: 1.2px;

    margin-bottom: 0.4rem;
}

/* =========================================
   INPUT
   ========================================= */

.form-group input {
    display: block;

    width: 100%;

    height: 43px;

    padding: 0.7rem 0.85rem;

    background: var(--input-bg);

    border: 1px solid var(--border-gold);

    border-radius: 3px;

    font-family: var(--font-sans);

    font-size: 0.85rem;

    color: var(--text-light);

    transition:
        border-color 0.25s ease,
        background 0.25s ease,
        box-shadow 0.25s ease;
}

.form-group input::placeholder {
    color: rgba(163, 184, 176, 0.45);
}

.form-group input:focus {
    outline: none;

    border-color: var(--rlx-gold);

    background: rgba(0, 60, 35, 0.9);

    box-shadow:
        0 0 8px rgba(197, 160, 89, 0.3);
}

/* =========================================
   AUTOFILL
   ========================================= */

.form-group input:-webkit-autofill {
    -webkit-box-shadow:
        0 0 0 30px #002818 inset !important;

    -webkit-text-fill-color:
        var(--text-light) !important;
}

/* =========================================
   REGISTER BUTTON
   ========================================= */

.btn-submit {
    display: block;

    width: 100%;

    height: 45px;

    margin-top: 1.25rem;

    padding: 0.75rem;

    background:
        linear-gradient(
            135deg,
            var(--rlx-gold) 0%,
            #a28038 100%
        );

    color: #002012;

    border: 1px solid var(--rlx-gold-light);

    border-radius: 3px;

    font-family: var(--font-sans);

    font-size: 0.8rem;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 2px;

    cursor: pointer;

    transition: all 0.25s ease;

    box-shadow:
        0 4px 15px rgba(0, 0, 0, 0.3);
}

.btn-submit:hover {
    background:
        linear-gradient(
            135deg,
            var(--rlx-gold-light) 0%,
            var(--rlx-gold) 100%
        );

    box-shadow:
        0 6px 20px rgba(197, 160, 89, 0.25);

    transform: translateY(-1px);
}

.btn-submit:active {
    transform: translateY(1px);
}

/* =========================================
   MOBILE RESPONSIVE
   ========================================= */

@media (max-width: 500px) {

    body {
        padding: 20px 12px;
    }

    .login-card {
        max-width: 100%;

        padding: 1.8rem 1.4rem;

        border-radius: 5px;
    }

    .brand-header {
        margin-bottom: 1.3rem;
    }

    .brand-header h1 {
        font-size: 1.35rem;

        letter-spacing: 2px;
    }

    .brand-header p {
        font-size: 0.6rem;

        letter-spacing: 1.2px;
    }

    .form-group {
        margin-bottom: 0.9rem;
    }

    .form-group input {
        height: 42px;
    }
}
  </style>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&display=swap" rel="stylesheet">
</head>
<body>

  <div>
    <div class="login-card">
      <div class="brand-header">
        <!-- Gold Crown Icon SVG -->
        <svg class="crown-icon" viewBox="0 0 24 24" fill="none" stroke="#c5a059" stroke-width="1.5">
          <path d="M2 19h20M3 15l3-9 4 5 2-7 2 7 4-5 3 9H3z" fill="rgba(197, 160, 89, 0.15)"/>
        </svg>
      
      @auth
      
      @else   
      @endauth

        <h1>Company Portal</h1>
        <p>FABELLON CONSTRUCTION AND DEVELOPMENT CORPORATION</p>
      </div>
      
      <!-- ERROR MESSAGE --> 
      @if ($errors->any())
    <div class="error-message">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif
      
    <!-- REGISTRATION FORM -->
      <form action="{{ route('register.submit') }}" method="post">
    @csrf

    <div class="form-group">
        <label for="username">Username</label>
        <input 
            type="text" 
            id="username" 
            name="username" 
            placeholder="Enter your username" 
            required 
            autocomplete="username"
        />
    </div>

    <div class="form-group">
        <label for="email">Email</label>
        <input 
            type="email" 
            id="email" 
            name="email" 
            placeholder="Enter your email" 
            required 
            autocomplete="email"
        />
    </div>

    <div class="form-group">
        <label for="password">Password</label>
        <input 
            type="password" 
            id="password" 
            name="password" 
            placeholder="Enter your password" 
            required 
            autocomplete="new-password"
        />
    </div>

    <div class="form-group">
        <label for="password_confirmation">Confirm Password</label>
        <input 
            type="password" 
            id="password_confirmation" 
            name="password_confirmation" 
            placeholder="Confirm your password" 
            required 
            autocomplete="new-password"
        />
    </div>

    <button type="submit" class="btn-submit">
        Register
    </button>
</form>
    </div>

    
  </div>

</body>
</html>