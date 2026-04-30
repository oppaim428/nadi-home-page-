<?php
/**
 * GET /api/site-config
 * Public, returns full site configuration document.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/config_store.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

json_response(config_get());
