<?php

declare(strict_types=1);

require __DIR__ . '/includes/content.php';

$content = get_content();
$settings = $content['settings'];

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function hero_title(array $hero): string
{
    $title = e($hero['title'] ?? '');
    $highlight = trim((string) ($hero['highlight'] ?? ''));

    if ($highlight !== '') {
        $title = preg_replace('/' . preg_quote(e($highlight), '/') . '/u', '<mark>' . e($highlight) . '</mark>', $title, 1) ?? $title;
    }

    return nl2br($title);
}

function icon(string $name, string $class = ''): string
{
    return '<span class="material-symbols-outlined ' . e($class) . '">' . e($name) . '</span>';
}

?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($settings['site_title']) ?></title>
    <meta name="description" content="<?= e($settings['meta_description']) ?>">
    <meta property="og:title" content="<?= e($settings['site_title']) ?>">
    <meta property="og:description" content="<?= e($settings['meta_description']) ?>">
    <meta property="og:type" content="website">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=block" rel="stylesheet">
    <link rel="stylesheet" href="/landing-v2/assets/app.css?v=<?= filemtime(__DIR__ . '/assets/app.css') ?>">
    <style>
        :root {
            --primary: <?= e($settings['primary']) ?>;
            --primary-soft: <?= e($settings['primary_soft']) ?>;
            --secondary: <?= e($settings['secondary']) ?>;
            --surface: <?= e($settings['surface']) ?>;
        }
    </style>
</head>
<body>
    <header class="topbar" id="topbar">
        <div class="container topbar-inner">
            <a href="#home" aria-label="Bil Bakalım ana sayfa">
                <img class="brand-logo" src="<?= e($settings['logo_url']) ?>" alt="Bil Bakalım Logo">
            </a>
            <nav class="nav-links" id="navLinks" aria-label="Ana menü">
                <?php foreach ($content['nav'] as $item): ?>
                    <a href="#<?= e($item['target']) ?>"><?= e($item['label']) ?></a>
                <?php endforeach; ?>
            </nav>
            <a class="pill pill-primary topbar-cta" href="<?= e($content['hero']['primary_url']) ?>"><?= e($content['hero']['primary_cta']) ?></a>
            <button class="mobile-toggle" type="button" id="menuToggle" aria-label="Menüyü aç">
                <?= icon('menu') ?>
            </button>
        </div>
    </header>

    <main>
        <section class="hero gradient" id="home">
            <div class="container hero-grid">
                <div>
                    <span class="eyebrow"><?= e($content['hero']['eyebrow']) ?></span>
                    <h1><?= hero_title($content['hero']) ?></h1>
                    <p><?= e($content['hero']['description']) ?></p>
                    <div class="hero-actions">
                        <a class="pill pill-light" href="<?= e($content['hero']['primary_url']) ?>"><?= e($content['hero']['primary_cta']) ?></a>
                        <a class="pill pill-ghost" href="<?= e($content['hero']['secondary_url']) ?>"<?= str_starts_with((string) ($content['hero']['secondary_url'] ?? ''), 'http') ? ' target="_blank" rel="noopener"' : '' ?>>
                            <?= icon((string) $content['hero']['secondary_icon']) ?>
                            <?= e($content['hero']['secondary_cta']) ?>
                        </a>
                    </div>
                    <?php if (!empty($content['hero']['trust_text'])): ?>
                        <div class="trust-line"><?= e($content['hero']['trust_text']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="hero-showcase">
                    <div class="hero-phone hero-phone-back">
                        <div class="phone-screen" style="background-image:url('<?= e($content['hero']['phone_image_secondary']) ?>')"></div>
                    </div>
                    <div class="hero-phone hero-phone-front">
                        <div class="phone-screen" style="background-image:url('<?= e($content['hero']['phone_image']) ?>')"></div>
                    </div>
                    <div class="hero-float-card">
                        <?= icon('emoji_events') ?>
                        <span>Canlı turnuva modu</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="section white" id="hakkinda">
            <div class="container two-col">
                <div class="about-visual">
                    <div class="about-image" style="background-image:url('<?= e($content['about']['image']) ?>')"></div>
                    <div class="about-stat">
                        <?= icon('verified') ?>
                        <span>Güncel soru havuzu</span>
                    </div>
                </div>
                <div class="copy">
                    <span class="eyebrow"><?= e($content['about']['eyebrow']) ?></span>
                    <h2><?= e($content['about']['title']) ?></h2>
                    <p><?= e($content['about']['description']) ?></p>
                    <div class="stack">
                        <?php foreach ($content['about']['bullets'] as $index => $bullet): ?>
                            <div class="inline-card">
                                <span class="icon-badge <?= $index % 2 ? '' : 'alt' ?>"><?= icon((string) $bullet['icon']) ?></span>
                                <?= e($bullet['title']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="section muted" id="nasil-oynanir">
            <div class="container">
                <div class="section-title">
                    <h2><?= e($content['steps']['title']) ?></h2>
                    <p><?= e($content['steps']['description']) ?></p>
                </div>
                <div class="steps-grid">
                    <?php foreach ($content['steps']['items'] as $index => $item): ?>
                        <article class="card step-card">
                            <div class="step-icon">
                                <?= icon((string) $item['icon'], 'text-5xl') ?>
                                <span class="step-number"><?= $index + 1 ?></span>
                            </div>
                            <h3><?= e($item['title']) ?></h3>
                            <p><?= e($item['description']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section white" id="ozellikler">
            <div class="container">
                <div class="section-title">
                    <h2><?= e($content['features']['title']) ?></h2>
                    <p><?= e($content['features']['description']) ?></p>
                </div>
                <div class="features-grid">
                    <?php foreach ($content['features']['items'] as $index => $item): ?>
                        <article class="card">
                            <span class="icon-badge <?= $index % 2 ? 'alt' : '' ?>"><?= icon((string) $item['icon']) ?></span>
                            <h3><?= e($item['title']) ?></h3>
                            <p><?= e($item['description']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section muted" id="ekranlar">
            <div class="container">
                <div class="section-title">
                    <h2><?= e($content['screenshots']['title']) ?></h2>
                    <p><?= e($content['screenshots']['description']) ?></p>
                </div>
                <div class="screens-carousel" aria-label="Uygulama ekran görüntüleri">
                    <div class="screens-row" data-carousel-track>
                        <?php foreach ([$content['screenshots']['items'], $content['screenshots']['items'], $content['screenshots']['items']] as $copyIndex => $shots): ?>
                            <?php foreach ($shots as $index => $shot): ?>
                                <div class="mini-phone" <?= $copyIndex > 0 ? 'aria-hidden="true"' : '' ?>>
                                    <div class="phone-screen" style="background-image:url('<?= e($shot['image']) ?>')"></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="section white" id="avantajlar">
            <div class="container">
                <div class="section-title">
                    <h2><?= e($content['advantages']['title']) ?></h2>
                    <p><?= e($content['advantages']['description']) ?></p>
                </div>
                <div class="advantages-grid">
                    <?php foreach ($content['advantages']['items'] as $index => $item): ?>
                        <article class="card">
                            <span class="icon-badge <?= $index % 2 ? '' : 'alt' ?>"><?= icon((string) $item['icon']) ?></span>
                            <h4><?= e($item['title']) ?></h4>
                            <p><?= e($item['description']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section muted" id="sss">
            <div class="container">
                <div class="section-title">
                    <h2><?= e($content['faq']['title']) ?></h2>
                </div>
                <div class="faq-list">
                    <?php foreach ($content['faq']['items'] as $index => $item): ?>
                        <details <?= $index === 0 ? 'open' : '' ?>>
                            <summary>
                                <?= e($item['question']) ?>
                                <?= icon('expand_more') ?>
                            </summary>
                            <div class="faq-answer"><?= nl($item['answer']) ?></div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section gradient" id="iletisim">
            <div class="container contact-grid">
                <div class="contact-copy">
                    <h2><?= e($content['contact']['title']) ?></h2>
                    <p><?= e($content['contact']['description']) ?></p>
                    <div class="inline-card" style="background:rgba(255,255,255,.1);color:#fff;margin-top:28px">
                        <span class="icon-badge alt"><?= icon('mail') ?></span>
                        <span>
                            <strong><?= e($content['contact']['email_label']) ?></strong><br>
                            <?= e($content['contact']['email']) ?>
                        </span>
                    </div>
                </div>
                <form class="contact-card" action="mailto:<?= e($content['contact']['email']) ?>" method="post" enctype="text/plain">
                    <div class="form-grid">
                        <div class="field">
                            <label><?= e($content['contact']['form_name_label']) ?></label>
                            <input type="text" name="name" placeholder="<?= e($content['contact']['form_name_label']) ?>">
                        </div>
                        <div class="field">
                            <label><?= e($content['contact']['form_email_label']) ?></label>
                            <input type="email" name="email" placeholder="ornek@mail.com">
                        </div>
                    </div>
                    <div class="field" style="margin-top:16px">
                        <label><?= e($content['contact']['form_message_label']) ?></label>
                        <textarea name="message" placeholder="Size nasıl yardımcı olabiliriz?"></textarea>
                    </div>
                    <button class="pill pill-primary" style="width:100%;margin-top:18px" type="submit"><?= e($content['contact']['form_button']) ?></button>
                </form>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container">
            <img class="footer-logo" src="<?= e($settings['footer_logo_url']) ?>" alt="Bil Bakalım Logo">
            <div class="footer-links">
                <?php foreach ($content['footer']['links'] as $link): ?>
                    <a href="<?= e($link['url']) ?>"><?= e($link['label']) ?></a>
                <?php endforeach; ?>
            </div>
            <div class="footer-socials">
                <?php foreach ($content['footer']['socials'] as $social): ?>
                    <a href="<?= e($social['url']) ?>" aria-label="<?= e($social['icon']) ?>"><?= icon((string) $social['icon']) ?></a>
                <?php endforeach; ?>
            </div>
            <p><?= e($content['footer']['copyright']) ?></p>
        </div>
    </footer>

    <script>
        const topbar = document.getElementById('topbar');
        const navLinks = document.getElementById('navLinks');
        const menuToggle = document.getElementById('menuToggle');

        window.addEventListener('scroll', () => {
            topbar.classList.toggle('is-scrolled', window.scrollY > 60);
        });

        menuToggle.addEventListener('click', () => {
            navLinks.classList.toggle('is-open');
        });

        navLinks.addEventListener('click', (event) => {
            if (event.target.matches('a')) {
                navLinks.classList.remove('is-open');
            }
        });

        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const revealGroups = [
            { selector: '.hero .eyebrow, .hero h1, .hero p, .hero-actions, .trust-line', direction: 'left' },
            { selector: '.hero-showcase', direction: 'right' },
            { selector: '.about-visual', direction: 'left' },
            { selector: '.copy .eyebrow, .copy h2, .copy p, .copy .inline-card', direction: 'right' },
            { selector: '.section-title', direction: 'down' },
            { selector: '.step-card, .features-grid .card, .advantages-grid .card, .faq-list details', direction: 'up' },
            { selector: '.screens-carousel', direction: 'up' },
            { selector: '.contact-copy h2, .contact-copy p, .contact-copy .inline-card', direction: 'left' },
            { selector: '.contact-card', direction: 'right' },
            { selector: '.footer-logo, .footer-links, .footer-socials, .site-footer p', direction: 'up' },
        ];

        if (!prefersReducedMotion) {
            document.body.classList.add('reveal-ready');

            const revealItems = [];
            revealGroups.forEach((group) => {
                document.querySelectorAll(group.selector).forEach((item, index) => {
                    item.classList.add('reveal-item', `reveal-${group.direction}`);
                    item.style.setProperty('--reveal-delay', `${Math.min(index * 85, 340)}ms`);
                    revealItems.push(item);
                });
            });

            const revealObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                });
            }, {
                threshold: 0.14,
                rootMargin: '0px 0px -8% 0px',
            });

            revealItems.forEach((item) => revealObserver.observe(item));
        }

        const carouselTrack = document.querySelector('[data-carousel-track]');
        if (carouselTrack) {
            let isDragging = false;
            let isPaused = false;
            let startX = 0;
            let startScrollLeft = 0;
            let autoTimer = null;

            const normalizeCarousel = () => {
                const segment = carouselTrack.scrollWidth / 3;
                if (segment <= 0) {
                    return;
                }

                if (carouselTrack.scrollLeft < segment * 0.5) {
                    carouselTrack.scrollLeft += segment;
                } else if (carouselTrack.scrollLeft > segment * 1.5) {
                    carouselTrack.scrollLeft -= segment;
                }
            };

            const getStep = () => {
                const item = carouselTrack.querySelector('.mini-phone');
                const gap = parseFloat(getComputedStyle(carouselTrack).columnGap || '34');
                return item ? item.offsetWidth + gap : 260;
            };

            const updateActiveSlide = () => {
                const trackRect = carouselTrack.getBoundingClientRect();
                const center = trackRect.left + trackRect.width / 2;
                let activeItem = null;
                let closestDistance = Number.POSITIVE_INFINITY;

                carouselTrack.querySelectorAll('.mini-phone').forEach((item) => {
                    const rect = item.getBoundingClientRect();
                    const itemCenter = rect.left + rect.width / 2;
                    const distance = Math.abs(center - itemCenter);

                    if (distance < closestDistance) {
                        closestDistance = distance;
                        activeItem = item;
                    }

                    item.classList.remove('is-active');
                });

                if (activeItem) {
                    activeItem.classList.add('is-active');
                }
            };

            const startAuto = () => {
                clearInterval(autoTimer);
                autoTimer = setInterval(() => {
                    if (isPaused || isDragging) {
                        return;
                    }
                    normalizeCarousel();
                    carouselTrack.scrollBy({ left: getStep(), behavior: 'smooth' });
                    window.setTimeout(updateActiveSlide, 420);
                }, 2600);
            };

            const setInitialCarouselPosition = () => {
                carouselTrack.scrollLeft = carouselTrack.scrollWidth / 3;
                updateActiveSlide();
            };

            carouselTrack.addEventListener('pointerdown', (event) => {
                isDragging = true;
                isPaused = true;
                startX = event.clientX;
                startScrollLeft = carouselTrack.scrollLeft;
                carouselTrack.classList.add('is-dragging');
                carouselTrack.setPointerCapture(event.pointerId);
            });

            carouselTrack.addEventListener('pointermove', (event) => {
                if (!isDragging) {
                    return;
                }
                event.preventDefault();
                carouselTrack.scrollLeft = startScrollLeft - (event.clientX - startX);
                updateActiveSlide();
            });

            const stopDrag = (event) => {
                if (!isDragging) {
                    return;
                }
                isDragging = false;
                carouselTrack.classList.remove('is-dragging');
                if (carouselTrack.hasPointerCapture(event.pointerId)) {
                    carouselTrack.releasePointerCapture(event.pointerId);
                }
                window.setTimeout(() => {
                    isPaused = false;
                }, 900);
            };

            carouselTrack.addEventListener('pointerup', stopDrag);
            carouselTrack.addEventListener('pointercancel', stopDrag);
            carouselTrack.addEventListener('mouseenter', () => {
                isPaused = true;
            });
            carouselTrack.addEventListener('mouseleave', () => {
                if (!isDragging) {
                    isPaused = false;
                }
            });
            carouselTrack.addEventListener('scroll', () => {
                normalizeCarousel();
                window.requestAnimationFrame(updateActiveSlide);
            }, { passive: true });
            window.requestAnimationFrame(setInitialCarouselPosition);
            startAuto();
        }
    </script>
</body>
</html>
