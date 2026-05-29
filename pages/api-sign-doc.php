<?php // pages/api-sign-doc.php — OTP via Google Authenticator + inject tanda tangan visual ke PDF
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

// ── Validasi format OTP ───────────────────────────────────
if (strlen($otpCode) !== 6 || !ctype_digit($otpCode)) {
    echo json_encode(['success'=>false,'message'=>'Kode OTP harus 6 digit angka.']); exit;
}

// ── Ambil secret OTP aktif ────────────────────────────────
$activeSecret = $user['otp_secret'] ?? ($_SESSION['mock_otp_secret'] ?? null);
if (empty($activeSecret)) {
    echo json_encode(['success'=>false,'message'=>'OTP belum dikonfigurasi. Silakan scan QR Code di halaman Setup OTP.']); exit;
}

// ── Verifikasi OTP — HARUS cocok dengan Google Authenticator ────────────
if (!TOTP::verify($activeSecret, $otpCode, 2)) {
    $expected = TOTP::getCode($activeSecret);
    logActivity('sign_failed_otp', 'OTP salah doc#' . $docId);
    echo json_encode([
        'success'      => false,
        'message'      => 'Kode OTP salah atau sudah kedaluwarsa. Masukkan kode terbaru dari aplikasi Google Authenticator.',
    ]); exit;
}

// ── Cek & proses dokumen ──────────────────────────────────
$doc = null;
try {
    $stmt = db()->prepare('SELECT * FROM signing_requests WHERE id=? AND user_id=? AND status="uploaded"');
    $stmt->execute([$docId, $user['id']]);
    $doc = $stmt->fetch() ?: null;
} catch (\Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'Database error: ' . $e->getMessage()]); exit;
}
if (!$doc) { echo json_encode(['success'=>false,'message'=>'Dokumen tidak ditemukan atau sudah diproses.']); exit; }

if (!is_dir(SIGNED_PATH)) mkdir(SIGNED_PATH, 0755, true);

$srcPath    = UPLOAD_PATH . $doc['filename_stored'];
$signedName = 'signed_' . $doc['filename_stored'];
$destPath   = SIGNED_PATH . $signedName;

if (!file_exists($srcPath)) {
    echo json_encode(['success'=>false,'message'=>'File asli tidak ditemukan di server.']); exit;
}

// ── Inject tanda tangan visual ke PDF ────────────────────
$signedAt  = date('Y-m-d H:i:s');
$pyScript  = __DIR__ . '/../includes/inject_signature.py';

$sigImagePath = '';
$tmpSigFile   = '';

// Ambil gambar tanda tangan dari base64
$sigB64 = trim($_POST['sig_image_b64'] ?? '');
if ($sigB64 && preg_match('/^data:image\/(png|jpeg|gif|webp);base64,(.+)$/is', $sigB64, $m)) {
    $imgData = base64_decode($m[2]);
    if ($imgData) {
        $tmpSigFile = sys_get_temp_dir() . '/signku_sig_' . uniqid() . '.png';
        file_put_contents($tmpSigFile, $imgData);
        if (file_exists($tmpSigFile) && filesize($tmpSigFile) > 0) {
            $sigImagePath = $tmpSigFile;
        }
    }
}

// Argumen untuk Python
$argsList = [
    $srcPath,
    $destPath,
    (string)$signPage,
    (string)$signX,
    (string)$signY,
    (string)$signW,
    (string)$signH,
    $user['name'],
    $signedAt,
    $sigImagePath
];

// ── PERBAIKAN EKSEKUSI PYTHON (Support Windows XAMPP & Linux) ──
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

if ($isWindows) {
    // Di Windows, escape string manual agar command prompt tidak error
    $args = implode(' ', array_map(function($a) { return '"' . str_replace('"', '""', $a) . '"'; }, $argsList));
    $cmd = 'python "' . $pyScript . '" ' . $args . ' 2>&1';
} else {
    // Di Linux / Mac OS
    $python = trim(shell_exec('which python3 2>/dev/null') ?: '/usr/bin/python3');
    $args = implode(' ', array_map('escapeshellarg', $argsList));
    $cmd = escapeshellarg($python) . ' ' . escapeshellarg($pyScript) . ' ' . $args . ' 2>&1';
}

$output = trim(shell_exec($cmd) ?: '');

// Hapus file temp gambar TTD
if ($tmpSigFile && file_exists($tmpSigFile)) {
    @unlink($tmpSigFile);
}

// ── CEK KEBERHASILAN PYTHON ──
// Jika gagal, kita HENTIKAN dan BONGKAR errornya ke layar user!
if (strpos($output, 'SUCCESS') === false) {
    echo json_encode([
        'success' => false,
        'message' => '<b>Gagal merender tanda tangan visual!</b><br>Error sistem:<br><code style="display:block;background:#fee2e2;padding:8px;margin-top:5px;font-size:11px;color:#991b1b;border-radius:4px;">' . nl2br(htmlspecialchars($output)) . '</code><br>Pastikan Library Python sudah diinstall.'
    ]); exit;
}

$injected = true;

// ── Update DB ─────────────────────────────────────────────
$docHash = hash_file('sha256', $destPath);
try {
    $hasHashCol = false;
    try {
        $cols = db()->query('SHOW COLUMNS FROM signing_requests LIKE "doc_hash"')->fetchAll();
        $hasHashCol = !empty($cols);
    } catch (\Throwable $e2) {}

    if ($hasHashCol) {
        db()->prepare('UPDATE signing_requests SET filename_signed=?,sign_x=?,sign_y=?,sign_page=?,sign_width=?,sign_height=?,status="signed",otp_verified=1,doc_hash=?,signed_at=NOW() WHERE id=?')
           ->execute([$signedName, $signX, $signY, $signPage, $signW, $signH, $docHash, $docId]);
    } else {
        db()->prepare('UPDATE signing_requests SET filename_signed=?,sign_x=?,sign_y=?,sign_page=?,sign_width=?,sign_height=?,status="signed",otp_verified=1,signed_at=NOW() WHERE id=?')
           ->execute([$signedName, $signX, $signY, $signPage, $signW, $signH, $docId]);
    }
} catch (\Throwable $e) {}

logActivity('sign_success', 'Dokumen ditandatangani: ' . $doc['filename_orig']);

echo json_encode([
    'success'   => true,
    'message'   => 'Dokumen berhasil ditandatangani! Tanda tangan visual telah disematkan. OTP terverifikasi ✅',
    'doc_id'    => $docId,
    'doc_hash'  => $docHash,
    'signed_at' => $signedAt,
    'injected'  => $injected,
]);