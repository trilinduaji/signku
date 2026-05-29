<?php // pages/reset-otp.php
// Dipanggil via index.php (sudah load config) tapi sertakan juga untuk keamanan
if (!function_exists('db')) { require_once __DIR__ . '/../includes/config.php'; }
$token = trim($_GET['token'] ?? '');
if (!$token) { flash('error','Token tidak valid.'); header('Location: ' . BASE_URL); exit; }

$stmt = db()->prepare('SELECT r.*,u.email,u.name FROM otp_reset_tokens r JOIN users u ON u.id=r.user_id WHERE r.token=? AND r.used=0 AND r.expires_at > NOW()');
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row) {
    flash('error','Token reset tidak valid atau sudah kadaluarsa.');
    header('Location: ' . BASE_URL . '/index.php?page=login'); exit;
}

// Mark as used and clear OTP
db()->prepare('UPDATE otp_reset_tokens SET used=1 WHERE id=?')->execute([$row['id']]);
db()->prepare('UPDATE users SET otp_secret=NULL,otp_enabled=0 WHERE id=?')->execute([$row['user_id']]);

session_regenerate_id(true);
$_SESSION['user_id'] = $row['user_id'];
logActivity('otp_reset', 'OTP berhasil direset via token');
flash('success', 'OTP berhasil direset! Silakan setup OTP baru sekarang.');
header('Location: ' . BASE_URL . '/index.php?page=setup-otp');
exit;
