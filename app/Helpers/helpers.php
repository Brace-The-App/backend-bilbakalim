<?php

use Carbon\Carbon;
use Carbon\CarbonInterface;

if (!function_exists('setActive')) {
    /**
     * @param string|array $routes
     * @param string $output
     * @return string
     */
    function setActive($routes, $output = 'active')
    {
        $routes = (array) $routes;

        foreach ($routes as $route) {
            if (request()->routeIs($route)) {
                return $output;
            }
        }

        return 'active';
    }
}

if (!function_exists('tr_timezone')) {
    /** Admin / TR panelleri için saat dilimi (uygulama UTC kalır). */
    function tr_timezone(): string
    {
        return 'Europe/Istanbul';
    }
}

if (!function_exists('tr_now')) {
    /** Şu an (Türkiye saati). */
    function tr_now(): CarbonInterface
    {
        return Carbon::now(tr_timezone());
    }
}

if (!function_exists('tr_carbon')) {
    /**
     * UTC (veya herhangi) bir zamanı Türkiye saatine çevir.
     * Kayıtlar DB'de UTC kalır; sadece gösterim/hesap için kullanılır.
     *
     * @param  mixed  $value  Carbon|DateTimeInterface|string|null
     */
    function tr_carbon(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof CarbonInterface) {
                return $value->copy()->timezone(tr_timezone());
            }

            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->timezone(tr_timezone());
            }

            return Carbon::parse((string) $value, config('app.timezone', 'UTC'))
                ->timezone(tr_timezone());
        } catch (\Throwable) {
            return null;
        }
    }
}

if (!function_exists('tr_time')) {
    /**
     * Türkiye saati formatlı string (admin panelleri).
     *
     * @param  mixed  $value
     */
    function tr_time(mixed $value, string $format = 'd.m.Y H:i', string $fallback = '—'): string
    {
        $dt = tr_carbon($value);

        return $dt ? $dt->format($format) : $fallback;
    }
}
