<?php // pages/download-signed.php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$id   = (int)($_GET['id'] ?? 0);
$user = currentUser();

$stmt = db()->prepare('SELECT * FROM signing_requests WHERE id=? AND user_id=? AND status="signed"');
$stmt->execute([$id, $user['id']]);
$doc = $stmt->fetch();

if (!$doc || empty($doc['filename_signed'])) {
    flash('error', 'Dokumen tidak ditemukan atau belum ditandatangani.');
    header('Location: ' . BASE_URL . '/index.php?page=signing-list');
    exit;
}

$path = SIGNED_PATH . $doc['filename_signed'];
if (!file_exists($path)) {
    flash('error', 'File tidak ditemukan di server.');
    header('Location: ' . BASE_URL . '/index.php?page=signing-list');
    exit;
}

logActivity('download_signed', $doc['filename_orig']);

// FIX: sanitasi nama file untuk header Content-Disposition
$downloadName = 'SIGNED_' . preg_replace('/[^\w\-.]/', '_', $doc['filename_orig']);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
readfile($path);
exit;
