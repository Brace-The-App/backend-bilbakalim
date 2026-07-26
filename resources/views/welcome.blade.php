<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BilBakalım — Bilgi Yarışması Platformu</title>
    <meta name="description" content="Binlerce soru, turnuvalar ve gerçek zamanlı yarışmalarla bilginizi test edin. BilBakalım.">
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #07131a;
            --ink-soft: #0d2230;
            --teal: #20c9b4;
            --teal-deep: #0f9f8e;
            --gold: #f0b429;
            --sand: #e8f2f0;
            --mist: #9bb4be;
            --paper: #f4faf8;
            --line: rgba(255, 255, 255, 0.12);
            --text: #16303c;
            --muted: #5a7582;
            --radius: 14px;
            --max: 1120px;
            --font-display: "Bricolage Grotesque", system-ui, sans-serif;
            --font-body: "Figtree", system-ui, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-body);
            color: var(--text);
            background: var(--paper);
            line-height: 1.55;
            overflow-x: hidden;
        }

        a { color: inherit; text-decoration: none; }
        img { max-width: 100%; display: block; }

        .wrap {
            width: min(100% - 2.5rem, var(--max));
            margin-inline: auto;
        }

        /* ——— Nav ——— */
        .site-header {
            position: fixed;
            inset: 0 0 auto;
            z-index: 50;
            padding: 1rem 0;
            transition: background .3s ease, backdrop-filter .3s ease, border-color .3s ease;
            border-bottom: 1px solid transparent;
        }

        .site-header.is-scrolled {
            background: rgba(7, 19, 26, 0.88);
            backdrop-filter: blur(12px);
            border-bottom-color: var(--line);
        }

        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
        }

        .brand {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 1.35rem;
            letter-spacing: -0.03em;
            color: #fff;
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
        }

        .brand img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.75rem;
            list-style: none;
        }

        .nav-links a {
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.95rem;
            font-weight: 500;
            transition: color .2s;
        }

        .nav-links a:hover { color: #fff; }

        .nav-admin {
            color: var(--gold) !important;
            font-weight: 600;
        }

        .nav-toggle {
            display: none;
            width: 42px;
            height: 42px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.06);
            border-radius: 10px;
            color: #fff;
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }

        .nav-toggle span {
            display: block;
            width: 18px;
            height: 2px;
            background: #fff;
            position: relative;
        }

        .nav-toggle span::before,
        .nav-toggle span::after {
            content: '';
            position: absolute;
            left: 0;
            width: 18px;
            height: 2px;
            background: #fff;
        }

        .nav-toggle span::before { top: -6px; }
        .nav-toggle span::after { top: 6px; }

        /* ——— Hero ——— */
        .hero {
            min-height: 100vh;
            min-height: 100dvh;
            position: relative;
            display: grid;
            align-items: end;
            color: #fff;
            overflow: hidden;
            background:
                radial-gradient(ellipse 80% 60% at 70% 20%, rgba(32, 201, 180, 0.28), transparent 55%),
                radial-gradient(ellipse 50% 40% at 15% 80%, rgba(240, 180, 41, 0.18), transparent 50%),
                linear-gradient(165deg, #07131a 0%, #0d2a38 48%, #0a4a45 100%);
        }

        .hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.035) 1px, transparent 1px);
            background-size: 72px 72px;
            mask-image: radial-gradient(ellipse 70% 60% at 50% 40%, #000 20%, transparent 75%);
            animation: gridDrift 28s linear infinite;
            pointer-events: none;
        }

        @keyframes gridDrift {
            from { transform: translateY(0); }
            to { transform: translateY(72px); }
        }

        .hero-orb {
            position: absolute;
            width: min(52vw, 520px);
            aspect-ratio: 1;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(32, 201, 180, 0.35), transparent 68%);
            right: -8%;
            top: 12%;
            filter: blur(8px);
            animation: orbPulse 7s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes orbPulse {
            0%, 100% { transform: scale(1); opacity: 0.85; }
            50% { transform: scale(1.08); opacity: 1; }
        }

        .hero-inner {
            position: relative;
            z-index: 2;
            padding: 7.5rem 0 4.5rem;
            width: min(100% - 2.5rem, var(--max));
            margin-inline: auto;
        }

        .hero-brand {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: clamp(3.2rem, 9vw, 6.4rem);
            letter-spacing: -0.05em;
            line-height: 0.92;
            margin-bottom: 1.35rem;
            animation: riseIn 0.9s cubic-bezier(.22,1,.36,1) both;
        }

        .hero-brand span {
            display: block;
            background: linear-gradient(105deg, #fff 20%, var(--teal) 55%, var(--gold) 95%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hero-lead {
            max-width: 32rem;
            font-size: clamp(1.05rem, 2.2vw, 1.25rem);
            color: rgba(232, 242, 240, 0.82);
            margin-bottom: 2rem;
            animation: riseIn 0.9s cubic-bezier(.22,1,.36,1) 0.12s both;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.85rem;
            animation: riseIn 0.9s cubic-bezier(.22,1,.36,1) 0.22s both;
        }

        @keyframes riseIn {
            from { opacity: 0; transform: translateY(28px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.95rem 1.45rem;
            font-family: var(--font-body);
            font-weight: 600;
            font-size: 1rem;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            transition: transform .2s, background .2s, color .2s, box-shadow .2s;
        }

        .btn:hover { transform: translateY(-2px); }

        .btn-primary {
            background: var(--teal);
            color: var(--ink);
            box-shadow: 0 10px 28px rgba(32, 201, 180, 0.28);
        }

        .btn-primary:hover { background: #3ad9c4; }

        .btn-ghost {
            background: transparent;
            color: #fff;
            border: 1px solid rgba(255,255,255,0.28);
        }

        .btn-ghost:hover {
            background: rgba(255,255,255,0.08);
            border-color: rgba(255,255,255,0.45);
        }

        .hero-mark {
            position: absolute;
            right: max(1rem, calc((100% - var(--max)) / 2 - 1rem));
            bottom: 8%;
            width: min(42vw, 380px);
            opacity: 0.9;
            pointer-events: none;
            animation: floatMark 6s ease-in-out infinite;
            z-index: 1;
        }

        .hero-mark svg {
            width: 100%;
            height: auto;
            filter: drop-shadow(0 24px 40px rgba(0,0,0,0.35));
        }

        @keyframes floatMark {
            0%, 100% { transform: translateY(0) rotate(-2deg); }
            50% { transform: translateY(-14px) rotate(2deg); }
        }

        /* ——— Sections ——— */
        .section {
            padding: 5.5rem 0;
        }

        .section-head {
            max-width: 36rem;
            margin-bottom: 3rem;
        }

        .section-head h2 {
            font-family: var(--font-display);
            font-size: clamp(1.9rem, 4vw, 2.75rem);
            font-weight: 800;
            letter-spacing: -0.035em;
            line-height: 1.1;
            color: var(--ink);
            margin-bottom: 0.75rem;
        }

        .section-head p {
            color: var(--muted);
            font-size: 1.08rem;
        }

        .features-list {
            display: grid;
            gap: 0;
            border-top: 1px solid rgba(22, 48, 60, 0.12);
        }

        .feature-row {
            display: grid;
            grid-template-columns: 4.5rem 1fr;
            gap: 1.25rem;
            align-items: start;
            padding: 1.65rem 0;
            border-bottom: 1px solid rgba(22, 48, 60, 0.12);
            opacity: 0;
            transform: translateY(18px);
            transition: opacity .55s ease, transform .55s ease;
        }

        .feature-row.is-in {
            opacity: 1;
            transform: none;
        }

        .feature-num {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 1.35rem;
            color: var(--teal-deep);
            letter-spacing: -0.03em;
            padding-top: 0.15rem;
        }

        .feature-row h3 {
            font-family: var(--font-display);
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 0.35rem;
            color: var(--ink);
        }

        .feature-row p {
            color: var(--muted);
            max-width: 38rem;
        }

        /* ——— Atmosphere band ——— */
        .band {
            position: relative;
            padding: 5rem 0;
            color: #fff;
            background:
                radial-gradient(ellipse 60% 80% at 10% 50%, rgba(240, 180, 41, 0.2), transparent 55%),
                linear-gradient(120deg, var(--ink) 0%, #0f3d3a 55%, #0d2230 100%);
            overflow: hidden;
        }

        .band::after {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.08'/%3E%3C/svg%3E");
            opacity: 0.35;
            pointer-events: none;
        }

        .band-inner {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .band h2 {
            font-family: var(--font-display);
            font-size: clamp(1.85rem, 3.5vw, 2.6rem);
            font-weight: 800;
            letter-spacing: -0.035em;
            line-height: 1.12;
            margin-bottom: 1rem;
        }

        .band p {
            color: rgba(232, 242, 240, 0.78);
            font-size: 1.08rem;
            margin-bottom: 1.75rem;
            max-width: 28rem;
        }

        .band-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem 2rem;
        }

        .band-stats strong {
            display: block;
            font-family: var(--font-display);
            font-size: clamp(2rem, 4vw, 2.75rem);
            font-weight: 800;
            letter-spacing: -0.04em;
            color: var(--teal);
            line-height: 1;
            margin-bottom: 0.35rem;
        }

        .band-stats span {
            color: rgba(232, 242, 240, 0.7);
            font-size: 0.95rem;
        }

        /* ——— CTA ——— */
        .cta {
            padding: 5rem 0 5.5rem;
            text-align: center;
        }

        .cta h2 {
            font-family: var(--font-display);
            font-size: clamp(1.9rem, 4vw, 2.8rem);
            font-weight: 800;
            letter-spacing: -0.035em;
            color: var(--ink);
            margin-bottom: 0.85rem;
        }

        .cta p {
            color: var(--muted);
            font-size: 1.1rem;
            max-width: 30rem;
            margin: 0 auto 1.75rem;
        }

        .cta .btn-primary {
            color: #fff;
            background: var(--ink);
            box-shadow: none;
        }

        .cta .btn-primary:hover {
            background: #0d2230;
        }

        /* ——— Footer ——— */
        .site-footer {
            background: var(--ink);
            color: rgba(232, 242, 240, 0.72);
            padding: 3.5rem 0 2rem;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr;
            gap: 2.5rem;
            margin-bottom: 2.5rem;
        }

        .footer-brand {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 1.35rem;
            color: #fff;
            letter-spacing: -0.03em;
            margin-bottom: 0.75rem;
        }

        .footer-grid h3 {
            font-family: var(--font-display);
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 1rem;
            letter-spacing: -0.01em;
        }

        .footer-grid ul {
            list-style: none;
            display: grid;
            gap: 0.55rem;
        }

        .footer-grid a {
            color: rgba(232, 242, 240, 0.65);
            transition: color .2s;
            font-size: 0.95rem;
        }

        .footer-grid a:hover { color: var(--teal); }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.08);
            padding-top: 1.5rem;
            font-size: 0.9rem;
            color: rgba(232, 242, 240, 0.45);
        }

        /* ——— Mobile ——— */
        @media (max-width: 860px) {
            .nav-toggle { display: inline-flex; }

            .nav-links {
                position: absolute;
                top: calc(100% + 0.5rem);
                right: 1.25rem;
                left: 1.25rem;
                flex-direction: column;
                align-items: stretch;
                gap: 0;
                background: rgba(7, 19, 26, 0.96);
                border: 1px solid var(--line);
                border-radius: 14px;
                padding: 0.5rem;
                display: none;
            }

            .nav-links.is-open { display: flex; }

            .nav-links a {
                padding: 0.85rem 1rem;
                border-radius: 10px;
            }

            .nav-links a:hover { background: rgba(255,255,255,0.06); }

            .hero-mark {
                opacity: 0.35;
                width: min(70vw, 260px);
                right: -4%;
                bottom: 18%;
            }

            .hero-inner { padding-bottom: 5rem; }

            .band-inner {
                grid-template-columns: 1fr;
                gap: 2.5rem;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        @media (max-width: 520px) {
            .hero-brand { font-size: clamp(2.6rem, 14vw, 3.4rem); }
            .band-stats { gap: 1.5rem 1.25rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation: none !important;
                transition: none !important;
            }
        }
    </style>
</head>
<body>
    @php
        $isAdmin = auth()->check() && auth()->user()->hasAnyRole(['admin', 'personel']);
    @endphp

    <header class="site-header" id="siteHeader">
        <nav class="nav wrap">
            <a href="{{ route('welcome') }}" class="brand">
                <img src="{{ asset('assets/images/logo/logo-icon.png') }}" alt="">
                BilBakalım
            </a>

            <button class="nav-toggle" type="button" aria-label="Menüyü aç" id="navToggle">
                <span></span>
            </button>

            <ul class="nav-links" id="navLinks">
                <li><a href="#ozellikler">Özellikler</a></li>
                <li><a href="#platform">Platform</a></li>
                <li><a href="#basla">Başla</a></li>
                @if ($isAdmin)
                    <li><a class="nav-admin" href="{{ route('admin.dashboard') }}">Admin</a></li>
                @endif
            </ul>
        </nav>
    </header>

    <section class="hero" aria-label="Giriş">
        <div class="hero-grid" aria-hidden="true"></div>
        <div class="hero-orb" aria-hidden="true"></div>

        <div class="hero-mark" aria-hidden="true">
            <svg viewBox="0 0 360 360" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="180" cy="180" r="168" stroke="rgba(255,255,255,0.12)" stroke-width="2"/>
                <circle cx="180" cy="180" r="128" stroke="rgba(32,201,180,0.35)" stroke-width="2" stroke-dasharray="10 14"/>
                <rect x="78" y="110" width="204" height="140" rx="28" fill="rgba(255,255,255,0.06)" stroke="rgba(255,255,255,0.18)" stroke-width="2"/>
                <text x="180" y="198" text-anchor="middle" fill="#20c9b4" font-family="Bricolage Grotesque, sans-serif" font-size="92" font-weight="800">?</text>
                <circle cx="118" cy="92" r="10" fill="#f0b429"/>
                <circle cx="268" cy="268" r="8" fill="#20c9b4"/>
            </svg>
        </div>

        <div class="hero-inner">
            <h1 class="hero-brand"><span>BilBakalım</span></h1>
            <p class="hero-lead">Binlerce soru, canlı turnuvalar ve arkadaşlarınla gerçek zamanlı yarışma — bilgini sahneye çıkar.</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="#ozellikler">Keşfet</a>
                <a class="btn btn-ghost" href="#basla">Nasıl çalışır?</a>
            </div>
        </div>
    </section>

    <section class="section" id="ozellikler">
        <div class="wrap">
            <div class="section-head">
                <h2>Yarışmayı bir üst seviyeye taşı</h2>
                <p>Oyun modlarından ödüllere, sosyal yarışmadan ilerleme takibine — hepsi tek platformda.</p>
            </div>

            <div class="features-list">
                <article class="feature-row">
                    <div class="feature-num">01</div>
                    <div>
                        <h3>Çeşitli oyun modları</h3>
                        <p>Bireysel oyunlar, turnuvalar ve günlük yarışmalarla her oturumda yeni bir ritim.</p>
                    </div>
                </article>
                <article class="feature-row">
                    <div class="feature-num">02</div>
                    <div>
                        <h3>Ödül ve jeton sistemi</h3>
                        <p>Doğru cevaplarla jeton kazan, turnuvalarda ödül kap, liderlik tablosunda yüksel.</p>
                    </div>
                </article>
                <article class="feature-row">
                    <div class="feature-num">03</div>
                    <div>
                        <h3>Arkadaşlarınla yarış</h3>
                        <p>Davet et, birlikte oyna, skorlarını paylaş — bilgiyi sosyal hale getir.</p>
                    </div>
                </article>
                <article class="feature-row">
                    <div class="feature-num">04</div>
                    <div>
                        <h3>İlerleme takibi</h3>
                        <p>Detaylı istatistiklerle güçlü ve zayıf alanlarını gör, hedeflerine net ilerle.</p>
                    </div>
                </article>
                <article class="feature-row">
                    <div class="feature-num">05</div>
                    <div>
                        <h3>Her yerden oyna</h3>
                        <p>Mobil uyumlu deneyim ve gerçek zamanlı güncellemelerle heyecanı kaçırma.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="band" id="platform">
        <div class="wrap band-inner">
            <div>
                <h2>Bilgi yarışmasının yeni sahnesi</h2>
                <p>Canlı rakipler, büyüyen soru havuzu ve sürekli turnuvalarla her gün yeniden başlayan bir arena.</p>
                <a class="btn btn-primary" href="#basla">Hemen bak</a>
            </div>
            <div class="band-stats" aria-label="Platform özeti">
                <div>
                    <strong>10K+</strong>
                    <span>Aktif kullanıcı</span>
                </div>
                <div>
                    <strong>50K+</strong>
                    <span>Soru</span>
                </div>
                <div>
                    <strong>100+</strong>
                    <span>Günlük turnuva</span>
                </div>
                <div>
                    <strong>95%</strong>
                    <span>Memnuniyet</span>
                </div>
            </div>
        </div>
    </section>

    <section class="cta" id="basla">
        <div class="wrap">
            <h2>Sahneye çıkmaya hazır mısın?</h2>
            <p>Bilgini test et, turnuvalara katıl ve zirveye oynamaya başla.</p>
            <a class="btn btn-primary" href="#ozellikler">Özellikleri gör</a>
        </div>
    </section>

    <footer class="site-footer" id="iletisim">
        <div class="wrap">
            <div class="footer-grid">
                <div>
                    <div class="footer-brand">BilBakalım</div>
                    <p>Bilgi yarışması platformu. Daha iyi bir deneyim için sürekli gelişiyoruz.</p>
                </div>
                <div>
                    <h3>Keşfet</h3>
                    <ul>
                        <li><a href="#ozellikler">Özellikler</a></li>
                        <li><a href="#platform">Platform</a></li>
                        <li><a href="#basla">Başla</a></li>
                        @if ($isAdmin)
                            <li><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                        @endif
                    </ul>
                </div>
                <div>
                    <h3>Destek</h3>
                    <ul>
                        <li><a href="mailto:info@bil-bakalim.com">İletişim</a></li>
                        <li><a href="#">Gizlilik</a></li>
                        <li><a href="#">Kullanım şartları</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                &copy; {{ date('Y') }} BilBakalım. Tüm hakları saklıdır.
            </div>
        </div>
    </footer>

    <script>
        const header = document.getElementById('siteHeader');
        const toggle = document.getElementById('navToggle');
        const links = document.getElementById('navLinks');

        const onScroll = () => {
            header.classList.toggle('is-scrolled', window.scrollY > 24);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        toggle?.addEventListener('click', () => {
            links.classList.toggle('is-open');
        });

        links?.querySelectorAll('a').forEach((a) => {
            a.addEventListener('click', () => links.classList.remove('is-open'));
        });

        const rows = document.querySelectorAll('.feature-row');
        if ('IntersectionObserver' in window) {
            const io = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-in');
                        io.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.2, rootMargin: '0px 0px -40px 0px' });

            rows.forEach((row, i) => {
                row.style.transitionDelay = `${i * 70}ms`;
                io.observe(row);
            });
        } else {
            rows.forEach((row) => row.classList.add('is-in'));
        }
    </script>
</body>
</html>
