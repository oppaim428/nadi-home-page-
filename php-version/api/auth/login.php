<?php
/**
 * POST /api/auth/login   { username, password }   -> { access_token, token_type, username }
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$body = read_json_body();
$username = trim((string) ($body['username'] ?? ''));
$password = (string) ($body['password'] ?? '');
if ($username === '' || $password === '') {
    json_error('Username and password are required', 400);
}

$stmt = db()->prepare('SELECT username, password_hash FROM admins WHERE username = :u LIMIT 1');
$stmt->execute([':u' => $username]);
$row = $stmt->fetch();
if (!$row || !verify_admin_password($password, (string) $row['password_hash'])) {
    json_error('Invalid username or password', 401);
}

$token = jwt_create_admin_token($row['username']);
json_response([
    'access_token' => $token,
    'token_type'   => 'bearer',
    'username'     => $row['username'],
]);
