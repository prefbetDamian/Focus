<?php
/**
 * Prosty helper geolokalizacji po IP
 * Używany m.in. przy STARCIU pracy jako fallback, gdy brak GPS.
 */

require_once __DIR__ . '/functions.php';

/**
 * Zwraca IP klienta (z uwzględnieniem ewentualnego X-Forwarded-For).
 */
function getClientIpForGeo(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
        $first = trim($parts[0]);
        if ($first !== '') {
            return $first;
        }
    }

    return getClientIP();
}

/**
 * Bardzo prosty fallback IP -> (lat,lng)
 * - korzysta z zewnętrznego API ipapi.co
 * - w razie błędu zwraca ['lat' => null, 'lng' => null]
 */
function getGeoFromIP(string $ip): array
{
    $url = "https://ipapi.co/{$ip}/json/";

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 1.5,
        ],
    ]);

    try {
        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) {
            return ['lat' => null, 'lng' => null];
        }

        $data = json_decode($res, true);
        if (!is_array($data)) {
            return ['lat' => null, 'lng' => null];
        }

        return [
            'lat' => isset($data['latitude']) ? (float)$data['latitude'] : null,
            'lng' => isset($data['longitude']) ? (float)$data['longitude'] : null,
        ];
    } catch (Throwable $e) {
        return ['lat' => null, 'lng' => null];
    }
}
