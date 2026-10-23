<?php
declare(strict_types=1);

final class HttpError extends RuntimeException
{
    public int $status;
    public function __construct(int $status, string $message)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

function config(): array
{
    static $config;
    if ($config !== null) return $config;
    $path = getenv('KOSTIPS_CONFIG') ?: dirname(__DIR__) . '/config.php';
    if (!is_file($path)) throw new HttpError(503, 'Konfigurasi belum tersedia. Ikuti langkah setup di README.');
    $config = require $path;
    if (!is_array($config)) throw new RuntimeException('Invalid configuration');
    return $config;
}

function db(): PDO
{
    static $db;
    if ($db instanceof PDO) return $db;
    $settings = config()['database'];
    $dsn = $settings['dsn'] ?? ('mysql:host=' . $settings['host'] . ';port=' . (int)$settings['port'] . ';dbname=' . $settings['name'] . ';charset=utf8mb4');
    $db = new PDO($dsn, $settings['username'] ?? null, $settings['password'] ?? null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA busy_timeout = 5000');
    } elseif ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        // Re-read current rows after acquiring their parent thread lock.
        $db->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    }
    return $db;
}

function query(PDO $db, string $sql, array $params = []): PDOStatement
{
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function timestamp(): string { return gmdate('Y-m-d H:i:s'); }
function iso_date(string $value): string { return str_replace(' ', 'T', $value) . 'Z'; }
function new_id(string $prefix): string { return $prefix . bin2hex(random_bytes(16)); }
function valid_id($value): string
{
    if (!is_string($value) || !preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/', $value)) {
        throw new HttpError(422, 'ID tidak valid.');
    }
    return $value;
}
function input_text($value, string $label, int $max): string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) throw new HttpError(422, "$label tidak valid.");
    $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
    if ($value === '' || mb_strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) {
        throw new HttpError(422, "$label wajib diisi, maksimal $max karakter, tanpa karakter kontrol.");
    }
    return $value;
}
function folder_name(string $id, string $title): string
{
    valid_id($id);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
    $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii === false ? $title : $ascii));
    $slug = trim(substr($slug, 0, 80), '-');
    return $id . '-' . ($slug !== '' ? $slug : 'diskusi');
}
function user_public(array $user): array
{
    return ['id' => $user['id'], 'name' => $user['name'], 'initials' => $user['initials'], 'color' => $user['color']];
}
function is_local_request(): bool
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    return in_array($remote, ['127.0.0.1', '::1'], true)
        && (bool) preg_match('/\A(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]+)?\z/', $host);
}
function demo_allowed(): bool
{
    return (config()['environment'] ?? 'production') === 'development'
        && (config()['demo_auth'] ?? false) === true && is_local_request();
}
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('KOSTIPS_PHP_SESSION');
    session_set_cookie_params([
        'httponly' => true, 'samesite' => 'Lax', 'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
    if (($_SESSION['auth_mode'] ?? '') === 'demo' && !demo_allowed()) {
        unset($_SESSION['user_id'], $_SESSION['auth_mode']);
        session_regenerate_id(true);
    }
    if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
    if (!isset($_SESSION['user_id']) && demo_allowed()) {
        $_SESSION['user_id'] = 'me';
        $_SESSION['auth_mode'] = 'demo';
        session_regenerate_id(true);
    }
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function current_user(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!is_string($id)) return null;
    return query(db(), 'SELECT * FROM users WHERE id = ?', [$id])->fetch() ?: null;
}
function require_user(): array
{
    $user = current_user();
    if (!$user) throw new HttpError(401, 'Login diperlukan untuk tindakan ini.');
    return $user;
}
function require_admin(array $user): void
{
    if ($user['role'] !== 'admin') throw new HttpError(403, 'Hanya admin yang boleh mengakses fitur ini.');
}
function require_csrf(): void
{
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') throw new HttpError(403, 'Permintaan lintas situs ditolak.');
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? null);
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) throw new HttpError(403, 'Token CSRF tidak valid. Muat ulang halaman.');
}
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
require_once __DIR__ . '/UploadStore.php';
require_once __DIR__ . '/ForumService.php';
