<?php
/**
 * Public pages.
 *   GET /api/pages           -> list published pages
 *   GET /api/pages/{slug}    -> get a published page  (slug arrives in $_GET['slug'] via .htaccess)
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

function page_doc(array $r): array
{
    return [
        'id'          => (string) ($r['id'] ?? ''),
        'slug'        => (string) ($r['slug'] ?? ''),
        'title_en'    => (string) ($r['title_en'] ?? ''),
        'title_ar'    => (string) ($r['title_ar'] ?? ''),
        'content_en'  => (string) ($r['content_en'] ?? ''),
        'content_ar'  => (string) ($r['content_ar'] ?? ''),
        'show_in_nav' => (bool) ($r['show_in_nav'] ?? false),
        'published'   => (bool) ($r['published'] ?? true),
        'updated_at'  => (string) ($r['updated_at'] ?? ''),
    ];
}

$slug = isset($_GET['slug']) ? normalize_slug((string) $_GET['slug']) : '';

if ($slug === '') {
    $stmt = db()->query(
        'SELECT id, slug, title_en, title_ar, content_en, content_ar, show_in_nav, published, updated_at
         FROM pages WHERE published = 1 ORDER BY updated_at DESC'
    );
    $rows = $stmt->fetchAll();
    json_response(array_map('page_doc', $rows));
}

if (!is_valid_slug($slug)) {
    json_error('Page not found', 404);
}

$stmt = db()->prepare(
    'SELECT id, slug, title_en, title_ar, content_en, content_ar, show_in_nav, published, updated_at
     FROM pages WHERE slug = :s AND published = 1 LIMIT 1'
);
$stmt->execute([':s' => $slug]);
$row = $stmt->fetch();
if (!$row) {
    json_error('Page not found', 404);
}
json_response(page_doc($row));
