<?php
/**
 * Authentication helpers — wraps password hashing + JWT verification
 * + admin lookup against the DB.
 */
declare(strict_types=1);

function hash_admin_password(string $plain): string
{
    // PASSWORD_BCRYPT keeps wire-compat with the original Python code (bcrypt).
    return password_hash($plain, PASSWORD_BCRYPT);
}

function verify_admin_password(string $plain, string $hash): bool
{
    return password_verify($plain, $hash);
}

function get_bearer_token(): ?string
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($auth === '' && function_exists('apache_request_headers')) {
        $hdr = apache_request_headers();
        foreach ($hdr as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) {
                $auth = $v;
                break;
            }
        }
    }
    if (stripos($auth, 'Bearer ') === 0) {
        return trim(substr($auth, 7));
    }
    return null;
}

/**
 * Returns admin row [username, role] or aborts with 401.
 */
function require_admin(): array
{
    $token = get_bearer_token();
    if (!$token) {
        json_error('Not authenticated', 401);
    }
    $payload = jwt_decode($token);
    if (!$payload || empty($payload['sub'])) {
        json_error('Invalid or expired token', 401);
    }
    $stmt = db()->prepare('SELECT username, role FROM admins WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $payload['sub']]);
    $row = $stmt->fetch();
    if (!$row) {
        json_error('Admin not found', 401);
    }
    return $row;
}

function seed_initial_admin(): void
{
    $username = (string) np_env('ADMIN_USERNAME', 'admin');
    $password = (string) np_env('ADMIN_PASSWORD', 'admin123');
    $pdo = db();

    // Remove any legacy admin records that don't match the configured username.
    $stmt = $pdo->prepare('DELETE FROM admins WHERE username <> :u');
    $stmt->execute([':u' => $username]);

    $stmt = $pdo->prepare('SELECT id, password_hash FROM admins WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();

    if (!$row) {
        $stmt = $pdo->prepare(
            'INSERT INTO admins (username, password_hash, role, created_at) VALUES (:u, :h, :r, :c)'
        );
        $stmt->execute([
            ':u' => $username,
            ':h' => hash_admin_password($password),
            ':r' => 'admin',
            ':c' => now_iso(),
        ]);
    } elseif (!verify_admin_password($password, (string) $row['password_hash'])) {
        $stmt = $pdo->prepare('UPDATE admins SET password_hash = :h WHERE id = :id');
        $stmt->execute([':h' => hash_admin_password($password), ':id' => (int) $row['id']]);
    }
}
