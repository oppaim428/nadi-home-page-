<?php
/**
 * Admin pages CRUD.
 *   GET    /api/admin/pages        -> list all pages
 *   POST   /api/admin/pages        -> create page (slug must be unique)
 *   PUT    /api/admin/pages/{id}   -> update page (id passed via $_GET['id'])
 *   DELETE /api/admin/pages/{id}   -> delete page
 */
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

require_admin();

function page_doc_admin(array $r): array
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

function sanitize_in(array $body): array
{
    return [
        'slug'        => normalize_slug((string) ($body['slug'] ?? '')),
        'title_en'    => (string) ($body['title_en'] ?? ''),
        'title_ar'    => (string) ($body['title_ar'] ?? ''),
        'content_en'  => (string) ($body['content_en'] ?? ''),
        'content_ar'  => (string) ($body['content_ar'] ?? ''),
        'show_in_nav' => to_bool($body['show_in_nav'] ?? false) ? 1 : 0,
        'published'   => array_key_exists('published', $body) ? (to_bool($body['published']) ? 1 : 0) : 1,
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pageId = isset($_GET['id']) ? (string) $_GET['id'] : '';

if ($method === 'GET' && $pageId === '') {
    $rows = db()->query(
        'SELECT id, slug, title_en, title_ar, content_en, content_ar, show_in_nav, published, updated_at
         FROM pages ORDER BY updated_at DESC'
    )->fetchAll();
    json_response(array_map('page_doc_admin', $rows));
}

if ($method === 'POST') {
    $body = read_json_body();
    $in = sanitize_in($body);
    if (!is_valid_slug($in['slug'])) {
        json_error('Invalid slug. Use lowercase letters, numbers and dashes.', 400);
    }
    $stmt = db()->prepare('SELECT id FROM pages WHERE slug = :s LIMIT 1');
    $stmt->execute([':s' => $in['slug']]);
    if ($stmt->fetch()) {
        json_error('A page with this slug already exists', 409);
    }
    $id = make_uuid();
    $now = now_iso();
    $ins = db()->prepare(
        'INSERT INTO pages (id, slug, title_en, title_ar, content_en, content_ar, show_in_nav, published, updated_at)
         VALUES (:id, :slug, :te, :ta, :ce, :ca, :nav, :pub, :u)'
    );
    $ins->execute([
        ':id' => $id,
        ':slug' => $in['slug'],
        ':te' => $in['title_en'],
        ':ta' => $in['title_ar'],
        ':ce' => $in['content_en'],
        ':ca' => $in['content_ar'],
        ':nav' => $in['show_in_nav'],
        ':pub' => $in['published'],
        ':u' => $now,
    ]);
    $row = array_merge($in, ['id' => $id, 'updated_at' => $now]);
    json_response(page_doc_admin($row));
}

if ($method === 'PUT') {
    if ($pageId === '') json_error('Page id required', 400);
    $body = read_json_body();
    $in = sanitize_in($body);
    if (!is_valid_slug($in['slug'])) {
        json_error('Invalid slug', 400);
    }
    $stmt = db()->prepare('SELECT id FROM pages WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $pageId]);
    if (!$stmt->fetch()) {
        json_error('Page not found', 404);
    }
    $clash = db()->prepare('SELECT id FROM pages WHERE slug = :s AND id <> :id LIMIT 1');
    $clash->execute([':s' => $in['slug'], ':id' => $pageId]);
    if ($clash->fetch()) {
        json_error('Slug already in use by another page', 409);
    }
    $now = now_iso();
    $upd = db()->prepare(
        'UPDATE pages SET slug=:slug, title_en=:te, title_ar=:ta, content_en=:ce, content_ar=:ca,
         show_in_nav=:nav, published=:pub, updated_at=:u WHERE id=:id'
    );
    $upd->execute([
        ':slug' => $in['slug'],
        ':te' => $in['title_en'],
        ':ta' => $in['title_ar'],
        ':ce' => $in['content_en'],
        ':ca' => $in['content_ar'],
        ':nav' => $in['show_in_nav'],
        ':pub' => $in['published'],
        ':u' => $now,
        ':id' => $pageId,
    ]);
    $row = array_merge($in, ['id' => $pageId, 'updated_at' => $now]);
    json_response(page_doc_admin($row));
}

if ($method === 'DELETE') {
    if ($pageId === '') json_error('Page id required', 400);
    $del = db()->prepare('DELETE FROM pages WHERE id = :id');
    $del->execute([':id' => $pageId]);
    if ($del->rowCount() === 0) {
        json_error('Page not found', 404);
    }
    json_response(['ok' => true]);
}

json_error('Method not allowed', 405);
