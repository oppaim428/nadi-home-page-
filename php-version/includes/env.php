<?php
/**
 * Lightweight .env loader (no Composer needed).
 * Reads `.env` from project root and populates $_ENV / getenv().
 * Lines starting with # and blank lines are ignored.
 * Quoted values ("..." or '...') are unwrapped.
 */
declare(strict_types=1);

function np_load_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }
        // Strip surrounding quotes
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last  = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
        if (getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

function np_env(string $key, $default = null)
{
    if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    $v = getenv($key);
    if ($v !== false && $v !== '') {
        return $v;
    }
    return $default;
}
