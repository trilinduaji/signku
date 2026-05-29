<?php // pages/api-debug-otp.php
header('Content-Type: application/json');
requireLogin();

// Baca secret dari semua sumber yang mungkin
$fromFile    = file_exists(OTP_SECRET_FILE) ? trim(file_get_contents(OTP_SECRET_FILE)) : null;
$fromSession = $_SESSION['mock_otp_secret'] ?? null;
$fromUser    = currentUser()['otp_secret']  ?? null;

// Yang dipakai server untuk verifikasi
$active = $fromSession ?? $fromFile ?? $fromUser ?? null;

$now    = time();
$codes  = [];
if ($active) {
    for ($i = -2; $i <= 2; $i++) {
        $ts = $now + $i * 30;
        $codes[] = [
            'offset'  => $i,
            'window'  => (int)floor($ts / 30),
            'code'    => TOTP::getCode($active, $ts),
            'current' => $i === 0,
        ];
    }
}

echo json_encode([
    'server_time'    => date('Y-m-d H:i:s') . ' UTC',
    'unix_ts'        => $now,
    'seconds_left'   => 30 - ($now % 30),
    'active_secret'  => $active,
    'secret_from'    => $fromSession ? 'session' : ($fromFile ? 'file' : 'user'),
    'secret_file'    => $fromFile,
    'secret_session' => $fromSession,
    'otp_uri'        => $active ? TOTP::getUri($active, 'trilinduaji@signku.id') : null,
    'codes'          => $codes,
    'match_check'    => 'Kode offset=0 harus SAMA dengan Google Authenticator',
], JSON_PRETTY_PRINT);
