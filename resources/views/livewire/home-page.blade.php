<div>
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
</div>
