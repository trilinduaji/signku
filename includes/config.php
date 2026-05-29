<?php
// ── SignKu Configuration ──────────────────────────────────
define('APP_NAME',    'SignKu');
define('APP_VERSION', '2.0.0');
define('BASE_URL',    'http://localhost/signku');  // change for production
define('ROOT_PATH',   dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads/pdf/');
define('SIGNED_PATH', ROOT_PATH . '/uploads/signed/');
define('ASSETS_PATH', ROOT_PATH . '/assets/');

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'signku');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHAR', 'utf8mb4');

// Session
define('SESSION_LIFETIME', 3600 * 8);

// OTP — email ini dipakai sebagai label di Google Authenticator
define('OTP_ISSUER', 'SignKu');
define('OTP_EMAIL',  'trilinduaji11@gmail.com');  // email akun Google Authenticator
define('OTP_DIGITS', 6);
define('OTP_PERIOD', 30);

// Timezone
date_default_timezone_set('Asia/Jakarta');

// ── Database Connection (PDO) ─────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHAR);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ── Session Bootstrap ─────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode',  1);
    session_start();
}

// ── OTP Secret Management ─────────────────────────────────
define('MOCK_USER_ID',   9999);
define('OTP_SECRET_FILE', __DIR__ . '/../.otp_secret');

/**
 * Baca secret OTP dari file SAJA.
 * TIDAK pernah auto-generate — hanya user yang bisa generate via halaman Setup OTP.
 * Jika belum ada, kembalikan string kosong (artinya OTP belum di-setup).
 */
function readOtpSecretFromFile(): string {
    if (!file_exists(OTP_SECRET_FILE)) return '';
    $s = trim(file_get_contents(OTP_SECRET_FILE));
    if ($s && preg_match('/^[A-Z2-7]{16,}$/', $s)) return $s;
    return '';
}

// Baca secret dari file — TIDAK auto-generate
$persistentOtpSecret = readOtpSecretFromFile();

// Sync ke session — session sebagai cache, file sebagai source of truth
// Jangan overwrite session jika user baru saja generate QR baru di setup-otp
if (empty($_SESSION['mock_otp_secret'])) {
    if ($persistentOtpSecret) {
        $_SESSION['mock_otp_secret'] = $persistentOtpSecret;
    }
    // Jika file kosong pun, jangan generate — biarkan kosong
    // User harus ke Setup OTP untuk generate
}

define('MOCK_USER', [
    'id'          => MOCK_USER_ID,
    'name'        => 'Tri Lindu Aji',
    'email'       => OTP_EMAIL,   // gunakan email Google yang benar
    'password'    => '',
    'role'        => 'admin',
    'otp_secret'  => $_SESSION['mock_otp_secret'] ?? $persistentOtpSecret,
    'otp_enabled' => !empty($_SESSION['mock_otp_secret'] ?? $persistentOtpSecret) ? 1 : 0,
    'created_at'  => date('Y-m-d H:i:s'),
    'updated_at'  => date('Y-m-d H:i:s'),
]);

$_SESSION['user_id'] = MOCK_USER_ID;

// ── Auth Helpers ──────────────────────────────────────────
function isLoggedIn(): bool { return true; }
function requireLogin(): void {}

function currentUser(): ?array {
    static $user = null;
    if ($user === null) {
        // Tentukan secret aktif: session > file > DB > kosong
        // Urutan prioritas: session (paling fresh) → file persisten → DB
        $activeSecret = $_SESSION['mock_otp_secret'] ?? '';
        if (!$activeSecret) {
            $activeSecret = readOtpSecretFromFile();
            if ($activeSecret) $_SESSION['mock_otp_secret'] = $activeSecret;
        }

        // Coba ambil data user dari DB
        try {
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([MOCK_USER_ID]);
            $dbUser = $stmt->fetch();
            if ($dbUser) {
                $user = array_merge($dbUser, ['name' => 'Tri Lindu Aji']);
                // Jika session/file punya secret lebih baru dari DB, pakai yang itu
                // Jika DB punya secret tapi session kosong, pakai dari DB
                if (!$activeSecret && $dbUser['otp_secret']) {
                    $activeSecret = $dbUser['otp_secret'];
                    $_SESSION['mock_otp_secret'] = $activeSecret;
                    // Sync balik ke file
                    file_put_contents(OTP_SECRET_FILE, $activeSecret);
                }
            } else {
                // User belum ada di DB, insert
                $ins = db()->prepare(
                    'INSERT IGNORE INTO users (id,name,email,password,role,otp_secret,otp_enabled) VALUES (?,?,?,?,?,?,?)'
                );
                $ins->execute([
                    MOCK_USER_ID,
                    'Tri Lindu Aji',
                    OTP_EMAIL,
                    password_hash('signku2024', PASSWORD_BCRYPT),
                    'admin',
                    $activeSecret ?: null,
                    $activeSecret ? 1 : 0,
                ]);
                $user = MOCK_USER;
            }
        } catch (\Throwable $e) {
            $user = MOCK_USER;
        }

        // Paksa email selalu OTP_EMAIL dan secret selalu dari sumber yang benar
        $user['email']       = OTP_EMAIL;
        $user['name']        = 'Tri Lindu Aji';
        $user['otp_secret']  = $activeSecret;
        $user['otp_enabled'] = $activeSecret ? 1 : 0;
    }
    return $user;
}

// ── Utility ───────────────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'] = compact('type', 'msg');
}

function getFlash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function logActivity(string $action, string $detail = ''): void {
    $userId = $_SESSION['user_id'] ?? null;
    $ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_log (user_id, action, detail, ip_address) VALUES (?,?,?,?)'
        );
        $stmt->execute([$userId, $action, $detail, $ip]);
    } catch (\Throwable $e) {}
}

function sanitizeFilename(string $name): string {
    $name = preg_replace('/[^\w\-.]/', '_', $name);
    return preg_replace('/_+/', '_', $name);
}
