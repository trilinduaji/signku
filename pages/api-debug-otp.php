<?php // pages/api-debug-otp.php — HAPUS DI PRODUCTION
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/totp.php';

header('Content-Type: application/json');

$activeSecret = $_SESSION['mock_otp_secret'] ?? 'NOT SET';
$user = currentUser();

$codes = [];
if ($activeSecret !== 'NOT SET') {
    $now = time();
    for ($i = -2; $i <= 2; $i++) {
        $codes['drift_' . ($i >= 0 ? '+' : '') . $i] = TOTP::getCode($activeSecret, $now + $i * 30);
    }
}

echo json_encode([
    'server_time'    => date('Y-m-d H:i:s'),
    'server_unix'    => time(),
    'timezone'       => date_default_timezone_get(),
    'active_secret'  => $activeSecret,
    'user_otp_secret'=> $user['otp_secret'] ?? 'null',
    'otp_enabled'    => $user['otp_enabled'] ?? 0,
    'valid_codes_now'=> $codes,
    'session_keys'   => array_keys($_SESSION),
], JSON_PRETTY_PRINT);
