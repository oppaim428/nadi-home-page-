<?php
/**
 * Bootstrap — included by every API entry point.
 *  - Loads .env
 *  - Defines paths
 *  - Sends CORS / JSON headers
 *  - Exposes db(), json_response(), require_admin(), etc.
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak errors to JSON clients
ini_set('log_errors', '1');

require_once __DIR__ . '/env.php';

define('NP_ROOT', dirname(__DIR__));
define('NP_UPLOAD_DIR', NP_ROOT . '/uploads');
define('NP_STORAGE_DIR', NP_ROOT . '/storage');

np_load_env(NP_ROOT . '/.env');

if (!is_dir(NP_UPLOAD_DIR)) {
    @mkdir(NP_UPLOAD_DIR, 0775, true);
}
if (!is_dir(NP_STORAGE_DIR)) {
    @mkdir(NP_STORAGE_DIR, 0775, true);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';

// CORS
$cors = (string) np_env('CORS_ORIGINS', '*');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($cors === '*' || $cors === '') {
    header('Access-Control-Allow-Origin: *');
} else {
    $allowed = array_map('trim', explode(',', $cors));
    if ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Credentials: true');
    }
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('X-Content-Type-Options: nosniff');

// Default JSON for API responses (overridden by static file handlers).
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Short-circuit preflight
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
