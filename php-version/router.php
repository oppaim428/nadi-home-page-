<?php
/**
 * Front-controller / fallback router.
 * Apache normally serves /index.html and uses .htaccess for /api routing.
 * This file is the fallback used by PHP built-in dev server
 *   php -S 127.0.0.1:8080 router.php
 * It performs the same routing logic in PHP.
 */
declare(strict_types=1);

$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$root = __DIR__;

// Normalise trailing slash
$path = rtrim($uri, '/');
if ($path === '') $path = '/';

// Block sensitive folders
if (preg_match('#^/(includes|sql|storage|\\.env|\\.git)#', $path)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

// Static files inside /uploads/ — served via /api/uploads/<filename>
if (preg_match('#^/api/uploads/([^/]+)$#', $path, $m)) {
    $file = $root . '/uploads/' . $m[1];
    if (is_file($file)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file) ?: 'application/octet-stream';
        if (str_ends_with(strtolower($m[1]), '.svg')) $mime = 'image/svg+xml';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($file));
        readfile($file);
        return true;
    }
    http_response_code(404);
    echo 'Not found';
    return true;
}

// API routing table
$apiRoutes = [
    '#^/api/?$#'                              => ['file' => '/api/index.php'],
    '#^/api/site-config/?$#'                  => ['file' => '/api/site-config.php'],
    '#^/api/auth/login/?$#'                   => ['file' => '/api/auth/login.php'],
    '#^/api/auth/me/?$#'                      => ['file' => '/api/auth/me.php'],
    '#^/api/admin/site-config/?$#'            => ['file' => '/api/admin/site-config.php'],
    '#^/api/admin/upload/?$#'                 => ['file' => '/api/admin/uploads.php', 'qs' => ['action' => 'upload']],
    '#^/api/admin/uploads/?$#'                => ['file' => '/api/admin/uploads.php', 'qs' => ['action' => 'list']],
    '#^/api/admin/uploads/([^/]+)$#'          => ['file' => '/api/admin/uploads.php', 'qs_capture' => ['filename' => 1]],
    '#^/api/admin/pages/?$#'                  => ['file' => '/api/admin/pages.php'],
    '#^/api/admin/pages/([A-Za-z0-9\-]+)/?$#' => ['file' => '/api/admin/pages.php', 'qs_capture' => ['id' => 1]],
    '#^/api/pages/?$#'                        => ['file' => '/api/pages.php'],
    '#^/api/pages/([A-Za-z0-9\-]+)/?$#'       => ['file' => '/api/pages.php', 'qs_capture' => ['slug' => 1]],
];

foreach ($apiRoutes as $re => $route) {
    if (preg_match($re, $path, $m)) {
        if (!empty($route['qs'])) {
            foreach ($route['qs'] as $k => $v) $_GET[$k] = $v;
        }
        if (!empty($route['qs_capture'])) {
            foreach ($route['qs_capture'] as $k => $idx) $_GET[$k] = $m[$idx] ?? '';
        }
        require $root . $route['file'];
        return true;
    }
}

// Real file? let PHP/Apache serve it.
$candidate = $root . $path;
if (is_file($candidate)) {
    return false; // built-in server will serve it
}

// Otherwise fall back to SPA
header('Content-Type: text/html; charset=utf-8');
readfile($root . '/index.html');
return true;
