<?php
// ── SignKu Configuration ──────────────────────────────────
define('APP_NAME',    'SignKu');
define('APP_VERSION', '2.0.0');
define('BASE_URL',    'http://localhost/signku');  // change for production
define('ROOT_PATH',   dirname(__DIR__));           // FIX: root of project, bukan /includes/
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
define('SESSION_LIFETIME', 3600 * 8); // 8 jam

// OTP
define('OTP_ISSUER', 'SignKu Digital');
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

// ── Session Bootstrap ────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode',  1);
    session_start();
}

// ── AUTO LOGIN: Tri Lindu Aji (bypass login form) ────────
// User mock dengan otp_enabled=1 dan otp_secret bawaan
// sehingga semua fitur termasuk OTP QR Code bisa berjalan
define('MOCK_USER_ID', 9999);
define('MOCK_USER', [
    'id'          => MOCK_USER_ID,
    'name'        => 'Tri Lindu Aji',
    'email'       => 'trilinduaji@signku.id',
    'password'    => '',
    'role'        => 'admin',
    'otp_secret'  => 'JBSWY3DPEHPK3PXP',   // secret TOTP valid (standard demo key)
    'otp_enabled' => 1,
    'created_at'  => date('Y-m-d H:i:s'),
    'updated_at'  => date('Y-m-d H:i:s'),
]);
// Selalu set session user_id ke mock user
$_SESSION['user_id'] = MOCK_USER_ID;

// Inisialisasi mock_otp_secret di session jika belum ada
// (agar OTP verify selalu pakai secret yang konsisten)
if (empty($_SESSION['mock_otp_secret'])) {
    $_SESSION['mock_otp_secret'] = MOCK_USER['otp_secret'];
}

// ── Auth Helpers ─────────────────────────────────────────
function isLoggedIn(): bool {
    return true; // selalu login
}

function requireLogin(): void {
    // tidak perlu redirect, selalu dianggap login
}

function currentUser(): ?array {
    static $user = null;
    if ($user === null) {
        // Coba ambil dari DB dulu; jika gagal (DB tidak ada) pakai mock
        try {
            $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([MOCK_USER_ID]);
            $dbUser = $stmt->fetch();
            if ($dbUser) {
                $user = array_merge($dbUser, ['name' => 'Tri Lindu Aji']);
            } else {
                $ins = db()->prepare(
                    'INSERT IGNORE INTO users (id,name,email,password,role,otp_secret,otp_enabled) VALUES (?,?,?,?,?,?,?)'
                );
                $ins->execute([
                    MOCK_USER_ID,
                    MOCK_USER['name'],
                    MOCK_USER['email'],
                    password_hash('signku2024', PASSWORD_BCRYPT),
                    'admin',
                    MOCK_USER['otp_secret'],
                    1,
                ]);
                $user = MOCK_USER;
            }
        } catch (\Throwable $e) {
            $user = MOCK_USER;
        }

        // KUNCI FIX: Jika user sudah scan QR baru via Setup OTP,
        // pakai secret terbaru dari session (bukan dari DB/mock yang lama)
        if (!empty($_SESSION['mock_otp_secret'])) {
            $user['otp_secret']  = $_SESSION['mock_otp_secret'];
            $user['otp_enabled'] = 1;
        }
    }
    return $user;
}

// ── Utility ──────────────────────────────────────────────
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
    } catch (\Throwable $e) {
        // DB tidak tersedia — lewati logging
    }
}

function sanitizeFilename(string $name): string {
    $name = preg_replace('/[^\w\-.]/', '_', $name);
    return preg_replace('/_+/', '_', $name);
}
