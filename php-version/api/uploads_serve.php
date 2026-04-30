<?php
/**
 * Serves uploaded files at /api/uploads/<filename>
 * Used as the fallback if Apache rules are not active. The .htaccess will
 * usually serve the file directly from /uploads/ for performance.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$filename = isset($_GET['filename']) ? (string) $_GET['filename'] : '';
if ($filename === '' || str_contains($filename, '/') || str_contains($filename, '..')) {
    json_error('Invalid filename', 400);
}
$path = NP_UPLOAD_DIR . '/' . $filename;
if (!is_file($path)) {
    json_error('File not found', 404);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path) ?: 'application/octet-stream';
if (str_ends_with(strtolower($filename), '.svg')) $mime = 'image/svg+xml';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: public, max-age=86400');
readfile($path);
exit;
