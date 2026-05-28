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

// ── Auth Helpers ─────────────────────────────────────────
/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '?page=login');
        exit;
    }
}

/**
 * Get current logged-in user from database
 * Returns array with user data or null if not logged in
 */
function currentUser(): ?array {
    static $user = null;

    if ($user === false) {
        return null; // Cache miss
    }

    if ($user !== null) {
        return $user; // Cache hit
    }

    if (!isLoggedIn()) {
        $user = false; // Not logged in
        return null;
    }

    // Fetch from database
    try {
        $stmt = db()->prepare('SELECT id, name, email, role, otp_secret, otp_enabled, created_at, updated_at FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            // User not found in DB - session mismatch
            session_destroy();
            $user = false;
            return null;
        }
    } catch (\Throwable $e) {
        // Database error
        $user = false;
        return null;
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

