<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BilBakalım - Bilgi Yarışması Platformu</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #333;
            overflow-x: hidden;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }

        .nav {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            text-decoration: none;
            color: white;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: opacity 0.3s;
        }

        .nav-links a:hover {
            opacity: 0.8;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            cursor: pointer;
        }

        .btn-primary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .btn-primary:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" stop-color="%23ffffff" stop-opacity="0.1"/><stop offset="100%" stop-color="%23ffffff" stop-opacity="0"/></radialGradient></defs><circle cx="200" cy="200" r="100" fill="url(%23a)"/><circle cx="800" cy="300" r="150" fill="url(%23a)"/><circle cx="400" cy="700" r="120" fill="url(%23a)"/></svg>');
            opacity: 0.3;
        }

        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .hero-text h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero-text p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-hero {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            background: white;
            color: #667eea;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .btn-hero:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .btn-hero.secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-hero.secondary:hover {
            background: white;
            color: #667eea;
        }

        .hero-image {
            text-align: center;
            position: relative;
        }

        .hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        .floating-card {
            position: absolute;
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            animation: float 3s ease-in-out infinite;
        }

        .floating-card:nth-child(1) {
            top: 10%;
            right: -10%;
            animation-delay: 0s;
        }

        .floating-card:nth-child(2) {
            bottom: 20%;
            left: -10%;
            animation-delay: 1s;
        }

        .floating-card:nth-child(3) {
            top: 50%;
            right: 35%;
            animation-delay: 2s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Features Section */
        .features {
            padding: 6rem 0;
            background: #f8fafc;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .section-title {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #2d3748;
        }

        .section-title p {
            font-size: 1.2rem;
            color: #718096;
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: white;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #2d3748;
        }

        .feature-card p {
            color: #718096;
            line-height: 1.6;
        }

        /* Stats Section */
        .stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            text-align: center;
        }

        .stat-item h3 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-item p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* CTA Section */
        .cta {
            background: #2d3748;
            color: white;
            padding: 6rem 0;
            text-align: center;
        }

        .cta h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .cta p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Footer */
        .footer {
            background: #1a202c;
            color: white;
            padding: 3rem 0 2rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-section h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 0.5rem;
        }

        .footer-section ul li a {
            color: #a0aec0;
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-section ul li a:hover {
            color: white;
        }

        .footer-bottom {
            border-top: 1px solid #2d3748;
            padding-top: 2rem;
            text-align: center;
            color: #a0aec0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .hero-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .hero-text h1 {
                font-size: 2.5rem;
            }

            .floating-card {
                display: none;
            }

            .hero-buttons {
                justify-content: center;
            }
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeIn 0.8s ease-out forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in:nth-child(1) { animation-delay: 0.1s; }
        .fade-in:nth-child(2) { animation-delay: 0.2s; }
        .fade-in:nth-child(3) { animation-delay: 0.3s; }
        .fade-in:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="nav">
            <a href="#" class="logo">
                <i class="fas fa-brain"></i> BilBakalım
            </a>
            <ul class="nav-links">
                <li><a href="#features">Özellikler</a></li>
                <li><a href="#stats">İstatistikler</a></li>
                <li><a href="#about">Hakkımızda</a></li>
                <li><a href="#contact">İletişim</a></li>
            </ul>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-text fade-in">
                <h1>Bilgi Yarışmasında<br>Zirveye Çık!</h1>
                <p>Binlerce soru, heyecan verici turnuvalar ve gerçek zamanlı yarışmalarla bilginizi test edin. Arkadaşlarınızla yarışın ve en iyisi olun!</p>
                <div class="hero-buttons">
                    <a href="#features" class="btn-hero">Hemen Başla</a>
                    <a href="#about" class="btn-hero secondary">Daha Fazla Bilgi</a>
                </div>
            </div>
            <div class="hero-image fade-in">
                <div class="floating-card">
                    <i class="fas fa-trophy" style="color: #fbbf24; font-size: 2rem; margin-bottom: 0.5rem;"></i>
                    <h4>Turnuva Kazan</h4>
                    <p>Jeton kazan!</p>
                </div>
                <div class="floating-card">
                    <i class="fas fa-users" style="color: #10b981; font-size: 2rem; margin-bottom: 0.5rem;"></i>
                    <h4>Arkadaşlarınla Yarış</h4>
                    <p>Eğlenceli yarışmalar!</p>
                </div>
                <div class="floating-card">
                    <i class="fas fa-lightning-bolt" style="color: #f59e0b; font-size: 2rem; margin-bottom: 0.5rem;"></i>
                    <h4>Hızlı Cevapla</h4>
                    <p>Zamanla yarış!</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-title fade-in">
                <h2>Neden BilBakalım?</h2>
                <p>Bilgi yarışması deneyimini bir üst seviyeye taşıyan özelliklerimizi keşfedin</p>
            </div>
            <div class="features-grid">
                <div class="feature-card fade-in">
                    <div class="feature-icon">
                        <i class="fas fa-gamepad"></i>
                    </div>
                    <h3>Çeşitli Oyun Modları</h3>
                    <p>Bireysel oyunlar, turnuvalar ve günlük yarışmalarla sürekli yeni deneyimler yaşayın.</p>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3>Ödül Sistemi</h3>
                    <p>Doğru cevaplarla jeton kazanın, turnuvalarda ödüller kazanın ve liderlik tablosunda yer alın.</p>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Sosyal Özellikler</h3>
                    <p>Arkadaşlarınızı davet edin, birlikte yarışın ve sosyal medyada başarılarınızı paylaşın.</p>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>İlerleme Takibi</h3>
                    <p>Detaylı istatistiklerle bilginizin nasıl geliştiğini takip edin ve hedeflerinize ulaşın.</p>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3>Mobil Uyumlu</h3>
                    <p>Her cihazda mükemmel çalışan responsive tasarım ile istediğiniz yerden oynayın.</p>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Gerçek Zamanlı</h3>
                    <p>Canlı yarışmalar ve anlık güncellemelerle heyecanı hiç kaybetmeyin.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats" id="stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item fade-in">
                    <h3>10K+</h3>
                    <p>Aktif Kullanıcı</p>
                </div>
                <div class="stat-item fade-in">
                    <h3>50K+</h3>
                    <p>Soru Sayısı</p>
                </div>
                <div class="stat-item fade-in">
                    <h3>100+</h3>
                    <p>Günlük Turnuva</p>
                </div>
                <div class="stat-item fade-in">
                    <h3>95%</h3>
                    <p>Memnuniyet Oranı</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta" id="about">
        <div class="container">
            <h2>Hemen Başlamaya Hazır mısın?</h2>
            <p>Binlerce kullanıcının tercih ettiği bilgi yarışması platformuna katıl ve bilginizi test etmeye başla!</p>
            <div class="cta-buttons">
                <a href="#features" class="btn-hero secondary">Özellikleri Keşfet</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>BilBakalım</h3>
                    <p>Bilgi yarışması platformu olarak, kullanıcılarımıza en iyi deneyimi sunmak için sürekli gelişiyoruz.</p>
                </div>
                <div class="footer-section">
                    <h3>Hızlı Linkler</h3>
                    <ul>
                        <li><a href="#features">Özellikler</a></li>
                        <li><a href="#stats">İstatistikler</a></li>
                        <li><a href="#about">Hakkımızda</a></li>
                        <li><a href="/private/lesley/admin">Admin Panel</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Destek</h3>
                    <ul>
                        <li><a href="#">Yardım Merkezi</a></li>
                        <li><a href="#">İletişim</a></li>
                        <li><a href="#">Gizlilik Politikası</a></li>
                        <li><a href="#">Kullanım Şartları</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Sosyal Medya</h3>
                    <ul>
                        <li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li>
                        <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                        <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                        <li><a href="#"><i class="fab fa-youtube"></i> YouTube</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 BilBakalım. Tüm hakları saklıdır.</p>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Fade in animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in').forEach(el => {
            observer.observe(el);
        });

        // Counter animation for stats
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 100;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current) + (target >= 1000 ? '+' : '');
            }, 20);
        }

        // Animate stats when they come into view
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statItems = entry.target.querySelectorAll('.stat-item h3');
                    statItems.forEach((item, index) => {
                        const targets = [10000, 50000, 100, 95];
                        setTimeout(() => {
                            animateCounter(item, targets[index]);
                        }, index * 200);
                    });
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        const statsSection = document.querySelector('.stats');
        if (statsSection) {
            statsObserver.observe(statsSection);
        }
    </script>
</body>
</html>
