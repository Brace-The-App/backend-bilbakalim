<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Client platform: ios | android | web.
 * Tercihen body'de platform gönderilir; yoksa User-Agent'tan tahmin edilir.
 */
class ClientPlatform
{
    public const ALLOWED = ['ios', 'android', 'web'];

    /**
     * @return array{platform:?string,user_agent:?string}
     */
    public static function resolve(Request $request): array
    {
        $rawUa = trim((string) ($request->header('User-Agent') ?? ''));
        $uaStore = self::normalizeUserAgent($rawUa, $request->input('platform'));

        $platform = self::normalizePlatform($request->input('platform'));
        if ($platform === null && $rawUa !== '') {
            $platform = self::guessFromUserAgent($rawUa);
        }

        return [
            'platform' => $platform,
            'user_agent' => $uaStore,
        ];
    }

    public static function normalizePlatform(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $p = strtolower(trim((string) $value));
        $aliases = [
            'iphone' => 'ios',
            'ipad' => 'ios',
            'apple' => 'ios',
            'flutter_ios' => 'ios',
            'flutter_android' => 'android',
            'www' => 'web',
            'browser' => 'web',
            'node' => 'web',
            'nodejs' => 'web',
        ];
        $p = $aliases[$p] ?? $p;

        return in_array($p, self::ALLOWED, true) ? $p : null;
    }

    public static function guessFromUserAgent(string $ua): ?string
    {
        $uaLower = strtolower($ua);

        // Flutter / Dart UA'ları genelde platform vermez → client platform göndermeli
        if (str_contains($uaLower, 'iphone') || str_contains($uaLower, 'ipad') || str_contains($uaLower, 'ios')) {
            return 'ios';
        }
        if (str_contains($uaLower, 'android')) {
            return 'android';
        }
        if (
            str_contains($uaLower, 'mozilla')
            || str_contains($uaLower, 'chrome')
            || str_contains($uaLower, 'safari')
            || str_contains($uaLower, 'firefox')
            || str_contains($uaLower, 'edg/')
        ) {
            return 'web';
        }

        return null;
    }

    /**
     * Web için detay tutmuyoruz (chrome vs gerekmez) → "web".
     * Mobilde ham UA kısaltılarak saklanır (debug).
     */
    public static function normalizeUserAgent(string $rawUa, mixed $platformInput): ?string
    {
        $platform = self::normalizePlatform($platformInput);
        if ($platform === 'web') {
            return 'web';
        }

        if ($rawUa === '') {
            return $platform; // ios/android gönderilmiş, UA yoksa platform etiketi
        }

        if ($platform === null && self::guessFromUserAgent($rawUa) === 'web') {
            return 'web';
        }

        return mb_substr($rawUa, 0, 500);
    }
}
