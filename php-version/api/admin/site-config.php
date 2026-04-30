<?php
/**
 * /api/admin/site-config
 *   GET  -> current config
 *   PUT  -> updates config (requires Bearer token)
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/config_store.php';

require_admin();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    json_response(config_get());
}
if ($method === 'PUT') {
    $body = read_json_body();
    $saved = config_save($body);
    json_response($saved);
}
json_error('Method not allowed', 405);
