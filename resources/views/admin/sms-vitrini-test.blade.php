<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SMS Vitrini test</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 42rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        .ok { color: #0d6efd; background: #e7f1ff; padding: 1rem; border-radius: 8px; }
        .err { color: #842029; background: #f8d7da; padding: 1rem; border-radius: 8px; }
        pre { background: #f4f4f5; padding: 1rem; overflow: auto; border-radius: 8px; font-size: 0.875rem; }
        a { color: #0d6efd; }
    </style>
</head>
<body>
    <h1>SMS Vitrini test sonucu</h1>
    <p><strong>Numara:</strong> {{ $phone }}</p>
    <p><strong>Mesaj:</strong> {{ $message }}</p>

    @if(!empty($result['success']))
        <div class="ok">
            <strong>Başarılı.</strong> {{ $result['message'] ?? '' }}
        </div>
    @else
        <div class="err">
            <strong>Başarısız.</strong> {{ $result['message'] ?? 'Bilinmeyen hata' }}
        </div>
    @endif

    <h2>Ham yanıt</h2>
    <pre>{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>

    <p><a href="{{ route('admin.dashboard') }}">Panele dön</a></p>
</body>
</html>
