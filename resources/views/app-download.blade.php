<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uygulamayı İndir — Bil Bakalım</title>
    <meta name="description" content="Bil Bakalım bilgi yarışması uygulamasını App Store veya Google Play'den indirin.">
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #07131a;
            --teal: #20c9b4;
            --teal-deep: #0f9f8e;
            --gold: #f0b429;
            --paper: #f4faf8;
            --text: #16303c;
            --muted: #5a7582;
            --font-display: "Bricolage Grotesque", system-ui, sans-serif;
            --font-body: "Figtree", system-ui, sans-serif;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            font-family: var(--font-body);
            color: var(--text);
            background:
                radial-gradient(ellipse 80% 50% at 50% -10%, rgba(32, 201, 180, 0.28), transparent 55%),
                radial-gradient(ellipse 60% 40% at 100% 100%, rgba(240, 180, 41, 0.12), transparent 50%),
                var(--paper);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.25rem;
        }
        .card {
            width: min(100%, 420px);
            text-align: center;
        }
        .brand {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 1.75rem;
            letter-spacing: -0.03em;
            color: var(--ink);
            margin-bottom: .35rem;
        }
        .lead {
            color: var(--muted);
            font-size: 1.05rem;
            margin-bottom: 1.75rem;
            line-height: 1.5;
        }
        .stores { display: grid; gap: .85rem; }
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .65rem;
            padding: 1rem 1.25rem;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1.05rem;
            text-decoration: none;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-ios {
            background: var(--ink);
            color: #fff;
            box-shadow: 0 10px 28px rgba(7, 19, 26, 0.22);
        }
        .btn-android {
            background: linear-gradient(135deg, var(--teal), var(--teal-deep));
            color: #fff;
            box-shadow: 0 10px 28px rgba(15, 159, 142, 0.28);
        }
        .hint {
            margin-top: 1.5rem;
            font-size: .9rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">Bil Bakalım</div>
        <p class="lead" id="lead">Uygulamayı telefonuna indir, bilgi yarışmasına katıl.</p>
        <div class="stores">
            @if ($iosUrl)
                <a id="btn-ios" class="btn btn-ios" href="{{ $iosUrl }}" rel="noopener">App Store (iOS)</a>
            @endif
            @if ($androidUrl)
                <a id="btn-android" class="btn btn-android" href="{{ $androidUrl }}" rel="noopener">Google Play (Android)</a>
            @endif
        </div>
        <p class="hint" id="hint"></p>
    </div>
    <script>
        (function () {
            var iosEl = document.getElementById('btn-ios');
            var androidEl = document.getElementById('btn-android');
            var ios = iosEl ? iosEl.href : '';
            var android = androidEl ? androidEl.href : '';
            var ua = (navigator.userAgent || '').toLowerCase();
            var isAndroid = ua.indexOf('android') !== -1;
            var isIos = ua.indexOf('iphone') !== -1 || ua.indexOf('ipad') !== -1 || ua.indexOf('ipod') !== -1;
            var store = isAndroid ? android : (isIos ? ios : '');
            var lead = document.getElementById('lead');
            var hint = document.getElementById('hint');
            var key = 'bb_dl_auto_' + (isAndroid ? 'android' : (isIos ? 'ios' : 'other'));

            window.addEventListener('pageshow', function (e) {
                if (e.persisted) document.body.style.visibility = 'visible';
            });

            if (!store) return;

            try {
                if (sessionStorage.getItem(key)) {
                    lead.textContent = 'Mağazaya dönmek için aşağıdaki butona dokun.';
                    return;
                }
                sessionStorage.setItem(key, '1');
            } catch (err) {}

            lead.textContent = 'Mağazaya yönlendiriliyorsun…';
            hint.textContent = 'Açılmazsa butona dokun.';
            setTimeout(function () { window.location.href = store; }, 350);
        })();
    </script>
</body>
</html>
