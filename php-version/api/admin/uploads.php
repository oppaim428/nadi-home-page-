<?php
/**
 * Admin uploads.
 *   POST   /api/admin/upload                 (multipart, field: file)
 *   GET    /api/admin/uploads                -> list uploaded files
 *   DELETE /api/admin/uploads/{filename}     -> delete file (filename via $_GET['filename'])
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

require_admin();

const NP_ALLOWED_MIME = [
    'image/png', 'image/jpeg', 'image/jpg', 'image/webp',
    'image/gif', 'image/svg+xml', 'image/avif',
];
const NP_EXT_FROM_MIME = [
    'image/png'     => '.png',
    'image/jpeg'    => '.jpg',
    'image/jpg'     => '.jpg',
    'image/webp'    => '.webp',
    'image/gif'     => '.gif',
    'image/svg+xml' => '.svg',
    'image/avif'    => '.avif',
];
const NP_ALLOWED_EXT = ['.png', '.jpg', '.jpeg', '.webp', '.gif', '.svg', '.avif'];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = isset($_GET['action']) ? (string) $_GET['action'] : '';
$filename = isset($_GET['filename']) ? (string) $_GET['filename'] : '';

function max_upload_bytes(): int
{
    $env = (int) np_env('MAX_UPLOAD_BYTES', 8 * 1024 * 1024);
    return $env > 0 ? $env : 8 * 1024 * 1024;
}

function list_uploads(): array
{
    $items = [];
    if (!is_dir(NP_UPLOAD_DIR)) return $items;
    $files = scandir(NP_UPLOAD_DIR) ?: [];
    foreach ($files as $f) {
        if ($f === '.' || $f === '..' || $f === '.gitkeep') continue;
        $full = NP_UPLOAD_DIR . '/' . $f;
        if (!is_file($full)) continue;
        $items[] = [
            'url'      => '/api/uploads/' . rawurlencode($f),
            'filename' => $f,
            'size'     => filesize($full),
            'modified' => gmdate('Y-m-d\TH:i:s\Z', filemtime($full) ?: time()),
        ];
    }
    // sort by modified desc
    usort($items, fn($a, $b) => strcmp($b['modified'], $a['modified']));
    return $items;
}

// ----- POST: upload -----
if ($method === 'POST' && $action === 'upload') {
    if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
        json_error('No file uploaded (expected field "file")', 400);
    }
    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_error('Upload failed (error code ' . (int) $file['error'] . ')', 400);
    }
    if ($file['size'] > max_upload_bytes()) {
        json_error('File too large (max ' . round(max_upload_bytes() / 1024 / 1024, 1) . ' MB)', 413);
    }

    $tmp = (string) $file['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower((string) $finfo->file($tmp));
    $clientType = strtolower((string) ($file['type'] ?? ''));
    if (!in_array($mime, NP_ALLOWED_MIME, true) && !in_array($clientType, NP_ALLOWED_MIME, true)) {
        // Allow svg fallback (some servers report text/xml).
        if (str_ends_with(strtolower((string) $file['name']), '.svg') && (str_starts_with($mime, 'text/') || $mime === 'application/xml')) {
            $mime = 'image/svg+xml';
        } else {
            json_error('Only PNG, JPG, WEBP, GIF, AVIF and SVG images are allowed', 400);
        }
    }
    if (!in_array($mime, NP_ALLOWED_MIME, true)) {
        $mime = $clientType;
    }

    $origExt = '.' . strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($origExt, NP_ALLOWED_EXT, true)) {
        $origExt = NP_EXT_FROM_MIME[$mime] ?? '.bin';
    }
    if ($origExt === '.jpeg') $origExt = '.jpg';

    $newName = bin2hex(random_bytes(16)) . $origExt;
    $dest = NP_UPLOAD_DIR . '/' . $newName;
    if (!@move_uploaded_file($tmp, $dest)) {
        // Fallback for CLI / built-in server.
        if (!@rename($tmp, $dest)) {
            json_error('Could not save uploaded file', 500);
        }
    }
    @chmod($dest, 0644);

    json_response([
        'url'          => '/api/uploads/' . rawurlencode($newName),
        'filename'     => $newName,
        'size'         => filesize($dest) ?: 0,
        'content_type' => $mime,
    ]);
}

// ----- GET: list -----
if ($method === 'GET' && $action === 'list') {
    json_response(list_uploads());
}

// ----- DELETE: by filename -----
if ($method === 'DELETE') {
    if ($filename === '' || str_contains($filename, '/') || str_contains($filename, '..')) {
        json_error('Invalid filename', 400);
    }
    $full = NP_UPLOAD_DIR . '/' . $filename;
    if (!is_file($full)) {
        json_error('File not found', 404);
    }
    @unlink($full);
    json_response(['ok' => true]);
}

json_error('Method not allowed', 405);
