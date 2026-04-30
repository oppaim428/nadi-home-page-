<?php
/**
 * NadiPlayer — First-time installer.
 *
 * Run once via the browser:  https://your-domain.tld/install.php
 *
 * Checks DB connection, creates tables and seeds the admin account.
 * Idempotent — safe to re-run if you change credentials.
 *
 * ⚠️  After successful install on a public host, RENAME or DELETE this file
 *     so it can't be hit again by visitors.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/env.php';

define('NP_ROOT', __DIR__);
define('NP_UPLOAD_DIR', NP_ROOT . '/uploads');
define('NP_STORAGE_DIR', NP_ROOT . '/storage');
np_load_env(NP_ROOT . '/.env');

if (!is_dir(NP_UPLOAD_DIR))  @mkdir(NP_UPLOAD_DIR, 0775, true);
if (!is_dir(NP_STORAGE_DIR)) @mkdir(NP_STORAGE_DIR, 0775, true);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/jwt.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config_store.php';

$logs = [];
$ok = true;

try {
    $logs[] = ['step' => 'Loading configuration', 'ok' => true,
               'detail' => 'DB driver: ' . db_driver()];

    db(); // triggers connection
    $logs[] = ['step' => 'Database connection', 'ok' => true,
               'detail' => db_driver() === 'sqlite' ? 'SQLite file ready' : 'MySQL connection OK'];

    $schemaLog = db_install_schema();
    foreach ($schemaLog as $line) {
        $logs[] = ['step' => 'Schema', 'ok' => true, 'detail' => $line];
    }

    seed_initial_admin();
    $logs[] = ['step' => 'Admin user', 'ok' => true,
               'detail' => 'Seeded / verified “' . htmlspecialchars((string) np_env('ADMIN_USERNAME', 'admin')) . '”'];

    config_get(); // ensures default site_config row exists
    $logs[] = ['step' => 'Site configuration', 'ok' => true,
               'detail' => 'Default site_config initialized'];

    if (!is_writable(NP_UPLOAD_DIR)) {
        $ok = false;
        $logs[] = ['step' => 'Uploads folder', 'ok' => false,
                   'detail' => 'uploads/ is not writable. chmod 755 (or 775) required.'];
    } else {
        $logs[] = ['step' => 'Uploads folder', 'ok' => true, 'detail' => 'Writable.'];
    }
} catch (Throwable $e) {
    $ok = false;
    $logs[] = ['step' => 'Fatal error', 'ok' => false, 'detail' => $e->getMessage()];
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>NadiPlayer — Installer</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{margin:0;font-family:system-ui,sans-serif;background:#04181b;color:#fff;min-height:100vh;padding:48px 24px}
  .wrap{max-width:760px;margin:0 auto;background:rgba(255,255,255,.04);border:1px solid rgba(212,164,55,.25);border-radius:16px;padding:32px;backdrop-filter:blur(12px)}
  h1{margin:0 0 4px;font-size:28px;letter-spacing:.05em}
  h1 span{color:#e5b73b}
  .sub{color:rgba(255,255,255,.6);margin-bottom:24px;font-size:14px}
  .row{display:flex;gap:12px;align-items:flex-start;padding:10px 14px;border-radius:10px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);margin-bottom:8px;font-size:14px}
  .row.ok{border-color:rgba(95,200,140,.3)}
  .row.bad{border-color:rgba(255,90,90,.4);background:rgba(255,90,90,.06)}
  .badge{flex:0 0 80px;font-weight:600;text-transform:uppercase;letter-spacing:.1em;font-size:11px}
  .badge.ok{color:#5fcc8c}
  .badge.bad{color:#ff8585}
  .step{flex:0 0 180px;color:rgba(255,255,255,.85)}
  .detail{flex:1;color:rgba(255,255,255,.65);word-break:break-word}
  .summary{margin-top:24px;padding:18px 20px;border-radius:12px;font-size:14px}
  .summary.ok{background:rgba(95,200,140,.1);border:1px solid rgba(95,200,140,.4);color:#bff5d4}
  .summary.bad{background:rgba(255,90,90,.08);border:1px solid rgba(255,90,90,.4);color:#ffb6b6}
  a{color:#e5b73b}
  code{background:rgba(0,0,0,.4);padding:2px 6px;border-radius:4px;color:#e5b73b}
  ol{padding-left:20px}
</style>
</head>
<body>
  <div class="wrap">
    <h1><span>NadiPlayer</span> — Installer</h1>
    <p class="sub">One-time setup. Re-runnable safely.</p>

    <?php foreach ($logs as $L): ?>
      <div class="row <?= $L['ok'] ? 'ok' : 'bad' ?>">
        <div class="badge <?= $L['ok'] ? 'ok' : 'bad' ?>"><?= $L['ok'] ? 'OK' : 'FAIL' ?></div>
        <div class="step"><?= htmlspecialchars((string) $L['step']) ?></div>
        <div class="detail"><?= htmlspecialchars((string) ($L['detail'] ?? '')) ?></div>
      </div>
    <?php endforeach ?>

    <?php if ($ok): ?>
      <div class="summary ok">
        <strong>✅ Installation completed successfully.</strong>
        <ol>
          <li>Open <a href="./">your site</a> to view the landing page.</li>
          <li>Sign in at <a href="./admin">/admin</a> using your <code>ADMIN_USERNAME</code> + <code>ADMIN_PASSWORD</code> from <code>.env</code>.</li>
          <li><strong>Delete <code>install.php</code> from your server</strong> after successful setup.</li>
        </ol>
      </div>
    <?php else: ?>
      <div class="summary bad">
        <strong>❌ Installation failed.</strong>
        Review the failed steps above. Common causes:
        <ul>
          <li>Wrong DB credentials in <code>.env</code></li>
          <li>Database not yet created in cPanel → MySQL Databases</li>
          <li><code>uploads/</code> directory permissions (chmod 755 or 775)</li>
        </ul>
      </div>
    <?php endif ?>
  </div>
</body>
</html>
