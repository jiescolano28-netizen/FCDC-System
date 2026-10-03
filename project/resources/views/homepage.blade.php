<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Fabellon Construction and Development Corporation</title>

    <style>
        :root {
            --green: #1F5B2C;
            --green-dark: #163f1f;
            --gold: #C9A227;
            --gold-dark: #a9860f;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
        }

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid var(--gold);
            outline-offset: 2px;
        }

        /* NAVBAR */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 20px 60px;
            background: var(--green);
            color: white;
            transition: padding 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
        }

        .navbar.is-scrolled {
            padding: 12px 60px;
            background: var(--green-dark);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
        }

        .navbar-brand {
            display: flex;
            align-items: baseline;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-links {
            display: flex;
            gap: 26px;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 15px;
            position: relative;
            padding-bottom: 4px;
        }

        .nav-links a::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: 0;
            width: 0%;
            height: 2px;
            background: var(--gold);
            transition: width 0.25s ease;
        }

        .nav-links a:hover::after,
        .nav-links a:focus-visible::after {
            width: 100%;
        }

        .nav-toggle {
            display: none;
            background: none;
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 6px;
            color: white;
            padding: 8px 12px;
            cursor: pointer;
        }

        /* HERO */
        .hero {
            min-height: 500px;
            padding: 80px 10%;
            display: flex;
            align-items: center;
            background:
                linear-gradient(rgba(31, 91, 44, 0.75), rgba(31, 91, 44, 0.75)),
                url('/image/construction-bg.jpg') center/cover no-repeat;
            color: white;
            overflow: hidden;
        }

        .hero-content {
            max-width: 700px;
        }

        .hero-content > * {
            opacity: 0;
            transform: translateY(16px);
            animation: rise 0.7s ease forwards;
        }

        .hero-content h1 { animation-delay: 0.05s; }
        .hero-content p { animation-delay: 0.25s; }
        .hero-content .login-button { animation-delay: 0.45s; }

        @keyframes rise {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero h1 {
            font-size: 45px;
            margin-bottom: 20px;
        }

        .hero p {
            font-size: 18px;
            line-height: 1.7;
        }

        .section {
            padding: 70px 10%;
            background: white;
        }

        .section h2 {
            color: var(--green);
            margin-bottom: 20px;
        }

        .section p {
            line-height: 1.8;
        }

        /* MATERIAL CARDS (click to expand) */
        .materials {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        .material-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            background: #fafafa;
            overflow: hidden;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .material-card:hover {
            border-color: var(--gold);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
        }

        .material-toggle {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: none;
            border: none;
            text-align: left;
            padding: 25px;
            cursor: pointer;
            font: inherit;
            color: inherit;
        }

        .material-toggle h3 {
            color: var(--green);
            margin: 0 0 6px 0;
        }

        .material-toggle p {
            margin: 0;
            line-height: 1.6;
        }

        .material-chevron {
            flex: 0 0 auto;
            width: 22px;
            height: 22px;
            transition: transform 0.25s ease;
            color: var(--gold-dark);
        }

        .material-card[data-open="true"] .material-chevron {
            transform: rotate(180deg);
        }

        .material-detail {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows 0.3s ease;
        }

        .material-card[data-open="true"] .material-detail {
            grid-template-rows: 1fr;
        }

        .material-detail-inner {
            overflow: hidden;
        }

        .material-detail p {
            margin: 0 25px 25px;
            padding-top: 12px;
            border-top: 1px dashed #ddd;
            color: #555;
            line-height: 1.7;
        }

        .login-button {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 25px;
            background: var(--gold);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: transform 0.15s ease, background 0.2s ease;
        }

        .login-button:hover {
            background: var(--gold-dark);
        }

        .login-button:active {
            transform: scale(0.96);
        }

        footer {
            padding: 25px;
            text-align: center;
            background: var(--green);
            color: white;
        }

        /* BACK TO TOP */
        .back-to-top {
            position: fixed;
            right: 24px;
            bottom: 24px;
            width: 46px;
            height: 46px;
            border-radius: 50%;
            border: none;
            background: var(--gold);
            color: white;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            opacity: 0;
            transform: translateY(12px);
            pointer-events: none;
            transition: opacity 0.25s ease, transform 0.25s ease, background 0.2s ease;
            z-index: 90;
        }

        .back-to-top.is-visible {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .back-to-top:hover {
            background: var(--gold-dark);
        }

        @media (max-width: 700px) {
            .navbar {
                padding: 16px 20px;
                flex-wrap: wrap;
            }

            .navbar.is-scrolled {
                padding: 10px 20px;
            }

            .nav-toggle {
                display: inline-flex;
            }

            .nav-links {
                flex-basis: 100%;
                display: none;
                flex-direction: column;
                gap: 14px;
                padding-top: 12px;
            }

            .nav-links.is-open {
                display: flex;
            }

            .hero {
                padding: 60px 25px;
            }

            .hero h1 {
                font-size: 32px;
            }

            .materials {
                grid-template-columns: 1fr;
            }

            .section {
                padding: 50px 25px;
            }
        }
    </style>
</head>

<body>

    <!-- NAVIGATION -->
    <nav class="navbar" id="navbar">
        <div class="navbar-brand">
            <strong>FABELLON</strong>
            <span>Construction and Development Corporation</span>
        </div>

        <button class="nav-toggle" id="navToggle" aria-expanded="false" aria-controls="navLinks">
            Menu
        </button>

        <ul class="nav-links" id="navLinks">
            <li><a href="#about">About</a></li>
            <li><a href="#materials">Materials</a></li>
            <li><a href="#system">System</a></li>
        </ul>
    </nav>


    <!-- HERO / BACKGROUND -->
    <section class="hero">
        <div class="hero-content">

            <h1>
                Fabellon Construction and Development Corporation
            </h1>

            <p>
                Welcome to Fabellon Construction and Development Corporation (FCDC),
                a medium-sized enterprise in the construction industry.
            </p>

            <a href="{{ route('login') }}" class="login-button">
                Login
            </a>

        </div>
    </section>


    <!-- ABOUT THE COMPANY -->
    <section class="section" id="about">

        <h2>About Fabellon Construction and Development Corporation</h2>

        <p>
            Fabellon Construction and Development Corporation (FCDC) is a
            medium-sized enterprise operating in the construction industry.
            In addition to its construction activities, the company also sells
            second-hand construction materials.
        </p>

        <p>
            The company provides various types of second-hand construction
            materials that may be used for construction, building, and
            renovation projects.
        </p>

    </section>


    <!-- CONSTRUCTION MATERIALS -->
    <section class="section" id="materials">

        <h2>Common Types of Second-hand Construction Materials</h2>

        <p>Tap a card for more detail on each material type.</p>

        <div class="materials">

            <div class="material-card" data-open="false">
                <button class="material-toggle" aria-expanded="false">
                    <span>
                        <h3>Scaffolding and Shoring</h3>
                        <p>H-frames, cross braces, shoring jacks, base jacks, and U-heads.</p>
                    </span>
                    <svg class="material-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="material-detail">
                    <div class="material-detail-inner">
                        <p>Sold as full sets or individual pieces, inspected for straightness and load-bearing condition before resale.</p>
                    </div>
                </div>
            </div>

            <div class="material-card" data-open="false">
                <button class="material-toggle" aria-expanded="false">
                    <span>
                        <h3>Structural Steel</h3>
                        <p>GI pipes, angle bars, C-purlins, I-beams, and steel matting.</p>
                    </span>
                    <svg class="material-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="material-detail">
                    <div class="material-detail-inner">
                        <p>Suited for framing, roofing support, and reinforcement work where standard structural sizes are needed.</p>
                    </div>
                </div>
            </div>

            <div class="material-card" data-open="false">
                <button class="material-toggle" aria-expanded="false">
                    <span>
                        <h3>Lumber and Wood</h3>
                        <p>Used usable wood planks and posts such as Tanguile, Apitong, and Yakal.</p>
                    </span>
                    <svg class="material-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="material-detail">
                    <div class="material-detail-inner">
                        <p>Reclaimed from prior projects and sorted by species and condition for framing, formwork, or finishing use.</p>
                    </div>
                </div>
            </div>

            <div class="material-card" data-open="false">
                <button class="material-toggle" aria-expanded="false">
                    <span>
                        <h3>Fixtures</h3>
                        <p>Corrugated GI sheets (yero), doors, and aluminum frames.</p>
                    </span>
                    <svg class="material-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="material-detail">
                    <div class="material-detail-inner">
                        <p>Checked for rust, warping, and hardware condition, ready for roofing, partitioning, or opening replacement.</p>
                    </div>
                </div>
            </div>

        </div>

    </section>


    <!-- SYSTEM INTRODUCTION -->
    <section class="section" id="system">

        <h2>Integrated Accounting Information System</h2>

        <p>
            This system is designed to support the company's business and
            accounting processes by providing an integrated platform for
            managing transactions, inventory, sales, accounting records,
            and tax compliance.
        </p>

        <a href="{{ route('login') }}" class="login-button">
            Access System
        </a>

    </section>


    <!-- FOOTER -->
    <footer>
        <p>
            Fabellon Construction and Development Corporation (FCDC)
        </p>

        <p>
            Integrated Accounting Information System
        </p>
    </footer>

    <!-- BACK TO TOP -->
    <button class="back-to-top" id="backToTop" aria-label="Back to top">&uarr;</button>

    <script>
        // Sticky navbar shrink on scroll + back-to-top visibility
        (function () {
            var navbar = document.getElementById('navbar');
            var backToTop = document.getElementById('backToTop');

            function onScroll() {
                var scrolled = window.scrollY > 40;
                navbar.classList.toggle('is-scrolled', scrolled);
                backToTop.classList.toggle('is-visible', window.scrollY > 400);
            }

            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();

            backToTop.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        })();

        // Mobile nav toggle
        (function () {
            var toggle = document.getElementById('navToggle');
            var links = document.getElementById('navLinks');

            toggle.addEventListener('click', function () {
                var isOpen = links.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });

            links.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    links.classList.remove('is-open');
                    toggle.setAttribute('aria-expanded', 'false');
                });
            });
        })();

        // Smooth scroll for in-page nav links
        document.querySelectorAll('.nav-links a[href^="#"]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                var target = document.querySelector(link.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Expand/collapse material cards
        document.querySelectorAll('.material-card').forEach(function (card) {
            var button = card.querySelector('.material-toggle');
            button.addEventListener('click', function () {
                var isOpen = card.getAttribute('data-open') === 'true';
                card.setAttribute('data-open', isOpen ? 'false' : 'true');
                button.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            });
        });
    </script>

</body>
</html>