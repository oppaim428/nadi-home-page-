<?php
declare(strict_types=1);

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $detail, int $status = 400): void
{
    json_response(['detail' => $detail], $status);
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_error('Invalid JSON body');
    }
    return $data;
}

function now_iso(): string
{
    return gmdate('Y-m-d\\TH:i:s.000\\Z');
}

function normalize_slug(?string $s): string
{
    $s = strtolower(trim((string) $s));
    $s = preg_replace('/[^a-z0-9-]+/', '-', $s);
    $s = trim((string) $s, '-');
    return substr((string) $s, 0, 63);
}

function is_valid_slug(string $slug): bool
{
    return (bool) preg_match('/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/', $slug);
}

function make_uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function to_bool($v): bool
{
    if (is_bool($v)) return $v;
    if (is_int($v)) return $v !== 0;
    if (is_string($v)) {
        $l = strtolower(trim($v));
        return in_array($l, ['1', 'true', 'yes', 'on'], true);
    }
    return false;
}
