<?php
/**
 * GET /api/auth/me  (requires Bearer token)
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$admin = require_admin();
json_response([
    'username' => $admin['username'],
    'role'     => $admin['role'] ?? 'admin',
]);
