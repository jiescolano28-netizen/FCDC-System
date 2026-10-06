<div>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: Arial, sans-serif;
            color: #333;
            background: #fff;

        /* =========================
           NAVIGATION
        ========================= */

        .navbar {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100px;
            padding: 0 7%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 10;

            background: rgba(15, 55, 27, 0.95);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .logo img {
            width: 130px;
            height: auto;
        }

        .company-name {
            color: #ffffff;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 20px;
            font-weight: 600;
            white-space: nowrap;
            letter-spacing: 0.3px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 34px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            transition: 0.2s ease;
        }

        .nav-links a:hover {
            color: #C9A227;
        }

        .login-btn {
            background: #C9A227;
            color: white !important;
            padding: 11px 23px;
            border-radius: 22px;
            transition: 0.4s ease;
            cursor: pointer;
        }

        .login-btn:hover {
            background: #a9871d;
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(0, 0, 0, 0.18);
        }


        /* =========================
           HERO
        ========================= */

        .hero {
            min-height: 620px;
            position: relative;
            display: flex;
            align-items: center;

            background:
                linear-gradient(90deg,
                    rgba(12, 55, 29, 0.95) 0%,
                    rgba(12, 55, 29, 0.82) 30%,
                    rgba(12, 55, 29, 0.40) 55%,
                    rgba(12, 55, 29, 0.10) 100%),
                url('/image/construction-bg.jpg');

            background-size: cover;
            background-position: center;
        }


        /* =========================
           HERO CONTENT
        ========================= */

        .hero-content {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 100px 7% 40px;
        }

        .welcome {
            color: #C9A227;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 2px;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        .hero h1 {
            max-width: 650px;
            color: white;
            font-family: Georgia, serif;
            font-size: 54px;
            line-height: 1.08;
            margin-bottom: 25px;
        }

        .hero-description {
            max-width: 570px;
            color: #f5f5f5;
            font-size: 18px;
            line-height: 1.65;
            margin-bottom: 30px;
        }


        /* =========================
           HERO BUTTONS
        ========================= */

        .hero-buttons {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 13px 25px;
            border-radius: 6px;
            background: #C9A227;
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: 0.4s ease;
            cursor: pointer;
        }

        .btn-primary:hover {
            background: #a9871d;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.18);
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 25px;
            border-radius: 6px;

            border: 1px solid #C9A227;
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;

            transition: 0.2s ease;
        }

        .btn-secondary:hover {
            background: rgba(201, 162, 39, 0.15);
        }


        /* =========================
           ABOUT SECTION
        ========================= */

        .about {
            padding: 80px 10%;
            background: #fff;
        }

        .about-container {
            max-width: 1100px;
            margin: auto;
        }

        .section-label {
            color: #C9A227;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .about h2 {
            color: #1F5B2C;
            font-family: Georgia, serif;
            font-size: 34px;
            margin-bottom: 20px;
        }

        .about p {
            max-width: 900px;
            font-size: 16px;
            line-height: 1.8;
            color: #555;
            margin-bottom: 15px;
        }


        /* =========================
           MATERIALS
        ========================= */

        .materials {
            padding: 80px 10%;
            background: #F2F2F2;
        }

        .materials-container {
            max-width: 1100px;
            margin: auto;
        }

        .materials h2 {
            color: #1F5B2C;
            font-family: Georgia, serif;
            font-size: 34px;
            margin-bottom: 35px;
        }

        .material-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .material-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            border: 1px solid #e3e3e3;
        }

        .material-card h3 {
            color: #1F5B2C;
            margin-bottom: 10px;
        }

        .material-card p {
            color: #666;
            line-height: 1.6;
        }


        /* =========================
           FOOTER
        ========================= */

        footer {
            padding: 30px;
            text-align: center;
            background: #1F5B2C;
            color: white;
            font-size: 14px;
        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 900px) {

            .navbar {
                padding: 0 5%;
            }

            .nav-links {
                gap: 18px;
            }

            .hero {
                min-height: 600px;
            }

            .hero-content {
                padding-left: 7%;
            }

            .hero h1 {
                font-size: 45px;
            }
        }


        @media (max-width: 700px) {

            .navbar {
                height: auto;
                min-height: 75px;
                padding: 15px 20px;
            }

            .logo img {
                width: 145px;
            }

            .nav-links a:not(.login-btn) {
                display: none;
            }

            .nav-links {
                gap: 0;
            }

            .login-btn {
                padding: 9px 18px;
            }

            .hero {
                min-height: 650px;
                background-position: center;
            }

            .hero-content {
                padding: 120px 25px 50px;
            }

            .welcome {
                font-size: 12px;
            }

            .hero h1 {
                font-size: 38px;
                line-height: 1.1;
            }

            .hero-description {
                font-size: 16px;
            }

            .hero-buttons {
                flex-wrap: wrap;
            }

            .material-grid {
                grid-template-columns: 1fr;
            }

            .about,
            .materials {
                padding: 60px 25px;
            }

            .about h2,
            .materials h2 {
                font-size: 29px;
            }
        }
    </style>
    <style id="dualpip-entry-btn-styles">
        /* Control-bar button */
        .dualpip-entry-btn-container {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: relative !important;
            vertical-align: middle !important;
            pointer-events: auto !important;
            background: transparent !important;
        }

        .dualpip-entry-btn {
            all: unset !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 5px 7px !important;
            cursor: pointer !important;
            border-radius: 4px !important;
            transition: opacity 0.15s, transform 0.15s !important;
            opacity: 0.85 !important;
            box-sizing: border-box !important;
            background: transparent !important;
            border: none !important;
            outline: none !important;
            position: relative !important;
            box-shadow: none !important;
        }

        .dualpip-entry-btn:hover {
            opacity: 1 !important;
            transform: scale(1.1) !important;
        }

        .dualpip-entry-btn:active {
            transform: scale(0.95) !important;
        }

        .dualpip-entry-btn-icon {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 22px !important;
            height: 22px !important;
            overflow: visible !important;
        }

        .dualpip-entry-btn-icon svg {
            width: 22px !important;
            height: 22px !important;
            min-width: 22px !important;
            min-height: 22px !important;
            display: block !important;
            overflow: visible !important;
            position: static !important;
        }

        /* Generic table-layout compatibility: When inserted into a display: table or table-row container */
        .dualpip-entry-btn-container.dualpip-table-cell {
            display: table-cell !important;
            width: 36px !important;
            min-width: 36px !important;
            height: 100% !important;
            white-space: nowrap !important;
            vertical-align: middle !important;
            text-align: center !important;
            box-sizing: border-box !important;
        }

        @media (max-width: 500px) {
            .dualpip-entry-btn-container.dualpip-table-cell {
                width: 28px !important;
                min-width: 28px !important;
            }
        }

        .dualpip-entry-btn-container.dualpip-table-cell .dualpip-entry-btn {
            margin: 0 auto !important;
        }

        /* Netflix: match native 42×42 button size */
        .dualpip-entry-btn-container.pip-netflix-btn {
            width: 52px !important;
            height: 52px !important;
            margin-left: 3rem !important;
            margin-top: -6px !important;
            cursor: pointer !important;
        }

        .pip-netflix-btn .dualpip-entry-btn {
            width: 42px !important;
            height: 42px !important;
            padding: 0 !important;
            transition: all 0.2s ease-in-out !important;
            background: transparent !important;
            border-radius: 10px !important;
        }

        .pip-netflix-btn .dualpip-entry-btn:hover {
            transform: scale(1.2) !important;
        }

        .pip-netflix-btn .dualpip-entry-btn:active {
            transform: scale(0.9) !important;
        }

        .pip-netflix-btn .dualpip-entry-btn-icon {
            width: 42px !important;
            height: 42px !important;
        }

        .pip-netflix-btn .dualpip-entry-btn-icon svg {
            width: 42px !important;
            height: 42px !important;
        }

        /* Prime Video: spacing to match native controls */
        .dualpip-entry-btn-container.pip-primevideo-btn {
            margin-left: 20px !important;
        }

        /* TED: spacing and flex alignment */
        .dualpip-entry-btn-container.pip-ted-btn {
            margin-left: 20px !important;
            width: auto !important;
            flex-shrink: 0 !important;
            display: flex !important;
            align-items: center !important;
        }

        /* Crunchyroll: match native 44×44 icon button geometry and hover effect */
        .dualpip-entry-btn-container.pip-crunchyroll-btn {
            width: 44px !important;
            height: 44px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: relative !important;
        }

        .pip-crunchyroll-btn .dualpip-entry-btn {
            width: 44px !important;
            height: 44px !important;
            padding: 6px !important;
            border-radius: 9999px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            box-sizing: border-box !important;
            transition: opacity 0.2s linear, background-color 0.2s linear !important;
            opacity: 0.75 !important;
        }

        .pip-crunchyroll-btn .dualpip-entry-btn:hover {
            opacity: 1 !important;
            background-color: rgb(43, 45, 54) !important;
            transform: none !important;
        }

        .pip-crunchyroll-btn .dualpip-entry-btn-icon {
            width: 24px !important;
            height: 24px !important;
        }

        .pip-crunchyroll-btn .dualpip-entry-btn-icon svg {
            width: 24px !important;
            height: 24px !important;
            min-width: 24px !important;
            min-height: 24px !important;
        }

        /* xHamster: match native 40px control bar button height and vertical alignment */
        .dualpip-entry-btn-container.pip-xhamster-btn {
            height: 40px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            vertical-align: middle !important;
        }

        /* === Cam site button styles === */

        /* ImLive: match native 32×32 vertical button bar */
        .dualpip-entry-btn-container.pip-imlive-btn {
            width: 32px !important;
            height: 32px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .pip-imlive-btn .dualpip-entry-btn {
            width: 32px !important;
            height: 32px !important;
            padding: 4px !important;
        }

        .pip-imlive-btn .dualpip-entry-btn-icon {
            width: 20px !important;
            height: 20px !important;
        }

        .pip-imlive-btn .dualpip-entry-btn-icon svg {
            width: 20px !important;
            height: 20px !important;
            min-width: 20px !important;
            min-height: 20px !important;
        }

        /* LiveJasmin: match native 30×30 vertical button stack */
        .dualpip-entry-btn-container.pip-livejasmin-btn {
            width: 30px !important;
            height: 30px !important;
            display: block !important;
        }

        .pip-livejasmin-btn .dualpip-entry-btn {
            width: 30px !important;
            height: 30px !important;
            padding: 4px !important;
        }

        .pip-livejasmin-btn .dualpip-entry-btn-icon {
            width: 18px !important;
            height: 18px !important;
        }

        .pip-livejasmin-btn .dualpip-entry-btn-icon svg {
            width: 18px !important;
            height: 18px !important;
            min-width: 18px !important;
            min-height: 18px !important;
        }

        /* BongaCams: match native 44×32 toolbar buttons */
        .dualpip-entry-btn-container.pip-bongacams-btn {
            width: 44px !important;
            height: 32px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .pip-bongacams-btn .dualpip-entry-btn {
            width: 44px !important;
            height: 32px !important;
            padding: 4px !important;
        }

        /* SWAG: match native 40×40 round control buttons */
        .dualpip-entry-btn-container.pip-swag-btn {
            width: 40px !important;
            height: 40px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .pip-swag-btn .dualpip-entry-btn {
            width: 40px !important;
            height: 40px !important;
            padding: 8px !important;
            border-radius: 50% !important;
            background: rgba(25, 25, 25, 0.2) !important;
        }

        .pip-swag-btn .dualpip-entry-btn:hover {
            background: rgba(25, 25, 25, 0.4) !important;
            transform: none !important;
        }

        /* StripChat: match native 36×36 rounded control buttons */
        .dualpip-entry-btn-container.pip-stripchat-btn {
            width: 36px !important;
            height: 36px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .pip-stripchat-btn .dualpip-entry-btn {
            width: 36px !important;
            height: 36px !important;
            padding: 6px !important;
            border-radius: 32px !important;
        }

        /* CamSoda: match native 32px height buttons */
        .dualpip-entry-btn-container.pip-camsoda-btn {
            height: 32px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .pip-camsoda-btn .dualpip-entry-btn {
            height: 32px !important;
            padding: 4px !important;
            border-radius: 4px !important;
        }

        /* SinParty: match native sincam settings buttons */
        .dualpip-entry-btn-container.pip-sinparty-btn {
            height: 100% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* Streamate: match native control buttons */
        .dualpip-entry-btn-container.pip-streamate-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* XloveCam: match native action buttons */
        .dualpip-entry-btn-container.pip-xlovecam-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* Cams.com: match native control buttons */
        .dualpip-entry-btn-container.pip-cams-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* Flirt4Free/Camster: match native audio control buttons */
        .dualpip-entry-btn-container.pip-f4f-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* MyFreeCams: match native Video.js buttons */
        .dualpip-entry-btn-container.pip-mfc-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* Cam4: match native control buttons */
        .dualpip-entry-btn-container.pip-cam4-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* Xcams: match native chat control buttons */
        .dualpip-entry-btn-container.pip-xcams-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* Amateur TV / SugarCams: match native Video.js buttons */
        .dualpip-entry-btn-container.pip-amateurtv-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* SecretFriends: match native volume wrapper buttons */
        .dualpip-entry-btn-container.pip-sf-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        /* Flowplayer / Fluid Player: float right to match native button layout */
        .dualpip-entry-btn-container.pip-fp-btn {
            float: right !important;
            position: relative !important;
            width: 24px !important;
            height: 24px !important;
            margin: 0 10px 0 0 !important;
            cursor: pointer !important;
        }

        .dualpip-entry-btn-container.pip-fp-btn .dualpip-entry-btn {
            padding: 1px !important;
            width: 24px !important;
            height: 24px !important;
            cursor: pointer !important;
        }

        .dualpip-entry-btn-container.pip-fp-btn .dualpip-entry-btn-icon svg {
            width: 20px !important;
            height: 20px !important;
        }

        /* SpankBang: match Video.js CSS order-based layout */
        .dualpip-entry-btn-container.pip-sb-btn {
            order: 5 !important;
        }

        /* Buttons that sit in a Video.js bar but do not wear vjs-control
   (site-specific skins such as SpankBang) stretch to the bar height.
   Entries that use buttonClass vjs-control keep the skin's own flex and height. */
        .vjs-control-bar>.dualpip-entry-btn-container:not(.vjs-control),
        .vjs-control-bar .dualpip-entry-btn-container:not(.vjs-control) {
            align-self: center !important;
            flex: none !important;
        }

        /* HBO/Max: match native 54×54 button size */
        .dualpip-entry-btn-container.pip-hbo-btn {
            width: 54px !important;
            height: 54px !important;
        }

        /* Twitch: match native 32×32 button size */
        .dualpip-twitch-btn .dualpip-entry-btn {
            height: 32px;
            padding: 0 5px;
        }

        /* Rumble: exactly match native float-right buttons (content-box, 18px+6px padding) */
        .dualpip-rumble-btn {
            float: right;
            height: auto;
            position: relative;
        }

        .dualpip-rumble-btn .dualpip-entry-btn {
            box-sizing: content-box;
            height: 18px;
            min-height: 18px;
            padding: 6px 9px;
            position: relative;
        }

        .dualpip-rumble-btn .dualpip-entry-btn-icon {
            width: 18px;
            height: 18px;
        }

        .dualpip-rumble-btn .dualpip-entry-btn-icon svg {
            width: 18px;
            height: 18px;
        }

        /* Disney+: spacing to match native controls */
        .dualpip-entry-btn-container.pip-disneyplus-btn {
            margin-left: 10px !important;
        }

        /* Panopto: match transport controls table layout */
        .dualpip-entry-btn-container.pip-panopto-btn {
            display: table-cell !important;
        }

        /* Naver pzp player: hide button when controls are hidden */
        .pzp-pc .dualpip-entry-btn-container {
            opacity: 0;
            transition: opacity 0.2s;
        }

        .pzp-pc.pzp-pc--controls .dualpip-entry-btn-container {
            opacity: 1;
        }

        /* TikTok overlay: sync button visibility with native controls */
        [class*='DivMediaCardOverlay'] .dualpip-entry-btn-container:not(.dualpip-per-video-btn) {
            opacity: 0 !important;
            transition: opacity 0.15s !important;
            pointer-events: none !important;
        }

        [class*='DivMediaCardOverlay']:hover .dualpip-entry-btn-container:not(.dualpip-per-video-btn) {
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        /* Per-video corner button */
        .dualpip-per-video-btn {
            position: absolute !important;
            z-index: 2147483646;
            opacity: 0;
            transition: opacity 0.2s;
            pointer-events: auto;
            /* Prevent host-page CSS from stretching the button container
     (e.g. Prime Video's ".fk9ydtn div { width:100%; height:100% }") */
            width: fit-content !important;
            height: fit-content !important;
        }

        .dualpip-per-video-btn[data-position="top-right"] {
            top: 8px;
            right: 8px;
            left: auto !important;
            bottom: auto !important;
        }

        .dualpip-per-video-btn[data-position="top-left"] {
            top: 8px;
            left: 8px;
            right: auto !important;
            bottom: auto !important;
        }

        .dualpip-per-video-btn.dualpip-video-hover,
        *:hover>.dualpip-per-video-btn,
        .dualpip-per-video-btn:hover {
            opacity: 1;
        }

        /* Touch-only devices: keep per-video button visible at reduced opacity
   since CSS :hover is unreliable without a pointing device. Users can still
   tap the button directly. On hover-capable devices, the button
   hides fully and only appears on parent hover. */
        @media (hover: none) {
            .dualpip-per-video-btn {
                opacity: 0.5;
            }

            .dualpip-per-video-btn:active {
                opacity: 1;
            }
        }

        .dualpip-per-video-btn .dualpip-entry-btn {
            width: 24px;
            height: 24px;
            opacity: 1;
            background: transparent;
            border-radius: 6px;
            padding: 4px;
            backdrop-filter: blur(4px);
        }

        .dualpip-per-video-btn .dualpip-entry-btn:hover {
            transform: scale(1.15);
        }

        .dualpip-per-video-btn .dualpip-entry-btn-icon {
            width: 20px;
            height: 20px;
        }

        .dualpip-per-video-btn .dualpip-entry-btn-icon svg {
            width: 20px;
            height: 20px;
            filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.6));
        }

        /* Per-video context menu */
        .dualpip-per-video-menu {
            background: rgba(30, 30, 30, 0.95);
            backdrop-filter: blur(12px);
            border-radius: 8px;
            padding: 4px 0;
            min-width: 160px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
            pointer-events: auto;
        }

        .dualpip-per-video-menu-item {
            padding: 8px 14px;
            font-size: 13px;
            color: #e0e0e0;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.15s;
        }

        .dualpip-per-video-menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Dailymotion sizes the right-hand bar for three slots and pins CC / fullscreen
   with position:absolute. Sit one slot left of CC, or two when CaptionGo is
   also injected (--cg-dm-slot). */
        .controls_vod:not([data-vertical-player-ui="true"]) .controls_bottom_right:has(.pip-dm-btn) {
            --pip-dm-slot: calc(var(--controls-icon-button-size, 2.5rem) + var(--vod-bottom-row-gap, 0.25rem));
            width: calc(var(--controls-bottom-right-width, 10.5rem) + var(--cg-dm-slot, 0px) + var(--pip-dm-slot, 0px)) !important;
        }

        .controls_vod:not([data-vertical-player-ui="true"]) .dualpip-entry-btn-container.pip-dm-btn {
            position: absolute !important;
            right: calc(var(--controls-subtitles-toggle-right, 2.9rem) + var(--cg-dm-slot, 0px) + var(--pip-dm-slot, 3rem)) !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            width: var(--controls-icon-button-size, 2.5rem) !important;
            height: var(--controls-icon-button-size, 2.5rem) !important;
            margin: 0 !important;
            z-index: 3 !important;
        }

        .controls_vod:not([data-vertical-player-ui="true"]) .dualpip-entry-btn-container.pip-dm-btn .dualpip-entry-btn {
            width: 100% !important;
            height: 100% !important;
            padding: 0 !important;
        }
    </style>
</head>

<body>

    <!-- =========================
         NAVIGATION
    ========================= -->

    <nav class="navbar">

        <div class="logo">
            <img src="{{ asset('image/company-logo.png') }}"
                alt="Fabellon Construction and Development Corporation">

            <span class="company-name">
                Fabellon Construction and Development Corporation (FCDC)
            </span>
        </div>

        <div class="nav-links">
            <a href="#home">Home</a>
            <a href="#about">About</a>
            <a href="#materials">Features</a>
            <a href="#contact">Contact</a>

            <a href="{{ route('login') }}" class="login-btn">
                Login
            </a>
        </div>

    </nav>


    <!-- =========================
         HERO
    ========================= -->

    <section class="hero" id="home">

        <div class="hero-content">

            <div class="welcome">
                Welcome to Fabellon
            </div>

            <h1>
                Integrated Accounting
                Information System
            </h1>

            <p class="hero-description">
                A centralized system for managing sales, inventory,
                accounting, and tax compliance — built for a more
                efficient and organized business.
            </p>

            <div class="hero-buttons">

                <a href="{{ route('login') }}" class="btn-primary">
                    Get Started&nbsp; →
                </a>

                <a href="#about" class="btn-secondary">
                    Learn More
                </a>

            </div>

        </div>

    </section>


    <!-- =========================
         ABOUT
    ========================= -->

    <section class="about" id="about">

        <div class="about-container">

            <div class="section-label">
                About Fabellon
            </div>

            <h2>
                Fabellon Construction and Development Corporation
            </h2>

            <p>
                Fabellon Construction and Development Corporation (FCDC)
                is a medium-sized enterprise in the construction industry.
            </p>

            <p>
                In addition to its construction activities, the company
                also sells second-hand construction materials that may
                be used for various construction, building, and
                renovation projects.
            </p>

        </div>

    </section>


    <!-- =========================
         MATERIALS
    ========================= -->

    <section class="materials" id="materials">

        <div class="materials-container">

            <div class="section-label">
                Construction Materials
            </div>

            <h2>
                Common Types of Second-hand Materials
            </h2>

            <div class="material-grid">

                <div class="material-card">
                    <h3>Scaffolding and Shoring</h3>

                    <p>
                        H-frames, cross braces, shoring jacks,
                        base jacks, and U-heads.
                    </p>
                </div>


                <div class="material-card">
                    <h3>Structural Steel</h3>

                    <p>
                        GI pipes, angle bars, C-purlins,
                        I-beams, and steel matting.
                    </p>
                </div>


                <div class="material-card">
                    <h3>Lumber and Wood</h3>

                    <p>
                        Used usable wood planks and posts such as
                        Tanguile, Apitong, and Yakal.
                    </p>
                </div>


                <div class="material-card">
                    <h3>Fixtures</h3>

                    <p>
                        Corrugated GI sheets (yero), doors,
                        and aluminum frames.
                    </p>
                </div>

            </div>

        </div>

    </section>


    <!-- =========================
         FOOTER
    ========================= -->

    <footer id="contact">

        <p>
            Fabellon Construction and Development Corporation (FCDC)
        </p>

        <p>
            Integrated Accounting Information System
        </p>

    </footer>




    <div id="booster_root"></div>
</div>
