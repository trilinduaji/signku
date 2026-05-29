<?php // pages/api-otp-live.php
// Endpoint AJAX — return kode OTP server saat ini (sama persis dengan Google Authenticator)
header('Content-Type: application/json');
requireLogin();

$user = currentUser();

// Prioritas: secret dari DB (sudah discan user) → session → file persisten
// currentUser() sudah menyinkronkan semua sumber, jadi $user['otp_secret'] adalah yang benar
$secret = $user['otp_secret'] ?? '';

// Fallback ke session jika user belum setup DB
if (!$secret) {
    $secret = $_SESSION['mock_otp_secret'] ?? '';
}

if (!$secret) {
    echo json_encode(['error' => 'no_secret', 'message' => 'OTP belum dikonfigurasi']);
    exit;
}

$now     = time();
$period  = defined('OTP_PERIOD') ? OTP_PERIOD : 30;
$seconds = $period - ($now % $period);

echo json_encode([
    'code'       => TOTP::getCode($secret),
    'seconds'    => $seconds,
    'timestamp'  => $now,
    'window'     => (int)floor($now / $period),
    'secret_ok'  => true,
]);
