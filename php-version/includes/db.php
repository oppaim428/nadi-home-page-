<?php
/**
 * DB layer — supports MySQL (cPanel) and SQLite (zero-setup).
 * Exposes db() returning a singleton PDO instance.
 */
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $driver = strtolower((string) np_env('DB_DRIVER', 'mysql'));

    try {
        if ($driver === 'sqlite') {
            $path = (string) np_env('DB_SQLITE_PATH', 'storage/nadiplayer.sqlite');
            if ($path[0] !== '/') {
                $path = NP_ROOT . '/' . $path;
            }
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $host    = (string) np_env('DB_HOST', 'localhost');
            $port    = (string) np_env('DB_PORT', '3306');
            $dbname  = (string) np_env('DB_NAME', 'nadiplayer');
            $user    = (string) np_env('DB_USER', 'root');
            $pass    = (string) np_env('DB_PASS', '');
            $charset = (string) np_env('DB_CHARSET', 'utf8mb4');

            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbname, $charset);
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
    } catch (Throwable $e) {
        error_log('[NadiPlayer DB] ' . $e->getMessage());
        // Don't leak internals to clients.
        if (function_exists('json_error')) {
            json_error('Database connection failed. Run /install.php to set up.', 500);
        } else {
            http_response_code(500);
            echo json_encode(['detail' => 'Database connection failed. Run /install.php to set up.']);
            exit;
        }
    }

    return $pdo;
}

function db_driver(): string
{
    return strtolower((string) np_env('DB_DRIVER', 'mysql'));
}

/**
 * Run schema for current driver. Idempotent (CREATE TABLE IF NOT EXISTS).
 */
function db_install_schema(): array
{
    $log = [];
    $pdo = db();
    $driver = db_driver();

    if ($driver === 'sqlite') {
        $stmts = [
            "CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'admin',
                created_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS site_config (
                id TEXT PRIMARY KEY,
                data TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS pages (
                id TEXT PRIMARY KEY,
                slug TEXT NOT NULL UNIQUE,
                title_en TEXT NOT NULL DEFAULT '',
                title_ar TEXT NOT NULL DEFAULT '',
                content_en TEXT NOT NULL DEFAULT '',
                content_ar TEXT NOT NULL DEFAULT '',
                show_in_nav INTEGER NOT NULL DEFAULT 0,
                published INTEGER NOT NULL DEFAULT 1,
                updated_at TEXT NOT NULL
            )",
        ];
    } else {
        $stmts = [
            "CREATE TABLE IF NOT EXISTS admins (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(64) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(32) NOT NULL DEFAULT 'admin',
                created_at VARCHAR(40) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS site_config (
                id VARCHAR(64) PRIMARY KEY,
                data LONGTEXT NOT NULL,
                updated_at VARCHAR(40) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS pages (
                id VARCHAR(64) PRIMARY KEY,
                slug VARCHAR(80) NOT NULL UNIQUE,
                title_en VARCHAR(255) NOT NULL DEFAULT '',
                title_ar VARCHAR(255) NOT NULL DEFAULT '',
                content_en LONGTEXT NOT NULL,
                content_ar LONGTEXT NOT NULL,
                show_in_nav TINYINT(1) NOT NULL DEFAULT 0,
                published TINYINT(1) NOT NULL DEFAULT 1,
                updated_at VARCHAR(40) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }

    foreach ($stmts as $sql) {
        $pdo->exec($sql);
        $log[] = 'OK: ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 70) . '...';
    }
    return $log;
}
