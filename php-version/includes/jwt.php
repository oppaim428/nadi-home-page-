<?php
/**
 * Minimal HS256 JWT implementation (no Composer needed).
 * Compatible with the original FastAPI tokens (PyJWT HS256).
 */
declare(strict_types=1);

function jwt_b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function jwt_b64url_decode(string $data): string
{
    $pad = strlen($data) % 4;
    if ($pad) {
        $data .= str_repeat('=', 4 - $pad);
    }
    $out = base64_decode(strtr($data, '-_', '+/'), true);
    return $out === false ? '' : $out;
}

function jwt_secret(): string
{
    $s = (string) np_env('JWT_SECRET', '');
    if ($s === '') {
        // Fallback so that auth still works even if .env is missing,
        // but tokens won't survive a redeploy. Log a warning.
        error_log('[NadiPlayer] JWT_SECRET is empty — using volatile fallback');
        $s = 'volatile-' . md5(__DIR__);
    }
    return $s;
}

function jwt_encode(array $payload): string
{
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $h = jwt_b64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $p = jwt_b64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $sig = hash_hmac('sha256', "$h.$p", jwt_secret(), true);
    $s = jwt_b64url_encode($sig);
    return "$h.$p.$s";
}

function jwt_decode(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$h, $p, $s] = $parts;
    $expected = jwt_b64url_encode(hash_hmac('sha256', "$h.$p", jwt_secret(), true));
    if (!hash_equals($expected, $s)) {
        return null;
    }
    $payload = json_decode(jwt_b64url_decode($p), true);
    if (!is_array($payload)) {
        return null;
    }
    if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
        return null;
    }
    return $payload;
}

function jwt_create_admin_token(string $username): string
{
    return jwt_encode([
        'sub'  => $username,
        'role' => 'admin',
        'iat'  => time(),
        'exp'  => time() + 7 * 86400,
    ]);
}
