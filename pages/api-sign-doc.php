<?php // pages/api-sign-doc.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/totp.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Tidak terautentikasi.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Metode tidak valid.']); exit; }

$user     = currentUser();
$docId    = (int)($_POST['doc_id']    ?? 0);
$otpCode  = trim($_POST['otp_code']   ?? '');
$signX    = (float)($_POST['sign_x']  ?? 40);
$signY    = (float)($_POST['sign_y']  ?? 60);
$signW    = (float)($_POST['sign_w']  ?? 200);
$signH    = (float)($_POST['sign_h']  ?? 80);
$signPage = (int)($_POST['sign_page'] ?? 1);

// ── Validasi format OTP dulu ──────────────────────────────
if (strlen($otpCode) !== 6 || !ctype_digit($otpCode)) {
    echo json_encode(['success'=>false,'message'=>'Kode OTP harus 6 digit angka.']); exit;
}

// ── Ambil secret OTP yang aktif dari session ─────────────
// Prioritas: session mock_otp_secret > user DB > MOCK_USER default
$activeSecret = $_SESSION['mock_otp_secret']
    ?? ($user['otp_secret'] ?? null);

if (empty($activeSecret)) {
    echo json_encode(['success'=>false,'message'=>'OTP belum dikonfigurasi. Silakan scan QR Code di halaman Setup OTP.']); exit;
}

// ── Verifikasi OTP dengan toleransi drift 2 window ───────
// Drift 2 = toleransi ±60 detik untuk perbedaan jam server/ponsel
if (!TOTP::verify($activeSecret, $otpCode, 2)) {
    // Debug info (hapus di production)
    $expected = TOTP::getCode($activeSecret);
    logActivity('sign_failed_otp', 'OTP salah untuk doc #' . $docId . ' | expected_window: ' . $expected);
    echo json_encode([
        'success' => false,
        'message' => 'Kode OTP salah atau sudah kadaluarsa. Pastikan waktu di ponsel Anda sinkron (Settings → Date & Time → Automatic).',
    ]); exit;
}

// ── Cek & proses dokumen ──────────────────────────────────
$doc = null;
try {
    $stmt = db()->prepare('SELECT * FROM signing_requests WHERE id=? AND user_id=? AND status="uploaded"');
    $stmt->execute([$docId, $user['id']]);
    $doc = $stmt->fetch() ?: null;
} catch (\Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'Database tidak tersedia: ' . $e->getMessage()]); exit;
}
if (!$doc) { echo json_encode(['success'=>false,'message'=>'Dokumen tidak ditemukan atau sudah diproses.']); exit; }

// ── Pastikan folder signed ada ────────────────────────────
if (!is_dir(SIGNED_PATH)) {
    mkdir(SIGNED_PATH, 0755, true);
}

$srcPath    = UPLOAD_PATH . $doc['filename_stored'];
$signedName = 'signed_' . $doc['filename_stored'];
$destPath   = SIGNED_PATH . $signedName;

if (!file_exists($srcPath)) {
    echo json_encode(['success'=>false,'message'=>'File asli tidak ditemukan di server.']); exit;
}

if (!copy($srcPath, $destPath)) {
    echo json_encode(['success'=>false,'message'=>'Gagal memproses dokumen.']); exit;
}

// ── Update DB ─────────────────────────────────────────────
try {
    db()->prepare('UPDATE signing_requests SET filename_signed=?,sign_x=?,sign_y=?,sign_page=?,sign_width=?,sign_height=?,status="signed",otp_verified=1,signed_at=NOW() WHERE id=?')
       ->execute([$signedName, $signX, $signY, $signPage, $signW, $signH, $docId]);
} catch (\Throwable $e) {}

logActivity('sign_success', 'Dokumen ditandatangani: ' . $doc['filename_orig']);

echo json_encode([
    'success' => true,
    'message' => 'Dokumen berhasil ditandatangani! OTP terverifikasi ✅',
    'doc_id'  => $docId,
]);
