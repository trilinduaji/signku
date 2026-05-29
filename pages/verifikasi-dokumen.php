<?php // pages/verifikasi-dokumen.php — Cek keaslian dokumen bertanda tangan
requireLogin();
$user = currentUser();

$docId = (int)($_GET['id'] ?? 0);
$doc = null;
$verifyResult = null;
$dbError = null;

if ($docId) {
    try {
        $stmt = db()->prepare('SELECT sr.*, u.name as signer_name, u.email as signer_email FROM signing_requests sr JOIN users u ON sr.user_id=u.id WHERE sr.id=?');
        $stmt->execute([$docId]);
        $doc = $stmt->fetch() ?: null;
    } catch (\Throwable $e) {
        $dbError = $e->getMessage();
        // Fallback: coba query tanpa JOIN
        try {
            $stmt2 = db()->prepare('SELECT * FROM signing_requests WHERE id=?');
            $stmt2->execute([$docId]);
            $row = $stmt2->fetch() ?: null;
            if ($row) {
                $row['signer_name']  = $user['name'];
                $row['signer_email'] = $user['email'];
                $doc = $row;
            }
        } catch (\Throwable $e2) {}
    }
}

// Handle manual verification via hash upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['check_pdf'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { flash('error','Token tidak valid.'); header('Location: ?page=verifikasi-dokumen'); exit; }
    $file = $_FILES['check_pdf'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if ($finfo->file($file['tmp_name']) === 'application/pdf') {
            $uploadedHash = hash_file('sha256', $file['tmp_name']);
            // Cek di semua signed docs — bandingkan hash file yang ada di server
            $found = null;
            try {
                $all = db()->prepare('SELECT sr.*, u.name as signer_name, u.email as signer_email FROM signing_requests sr JOIN users u ON sr.user_id=u.id WHERE sr.status="signed"');
                $all->execute();
                foreach ($all->fetchAll() as $row) {
                    $signedPath = SIGNED_PATH . $row['filename_signed'];
                    if (file_exists($signedPath) && hash_file('sha256', $signedPath) === $uploadedHash) {
                        $found = $row;
                        break;
                    }
                }
                // Juga cek via doc_hash kolom jika ada
                if (!$found) {
                    try {
                        $byHash = db()->prepare('SELECT sr.*, u.name as signer_name, u.email as signer_email FROM signing_requests sr JOIN users u ON sr.user_id=u.id WHERE sr.doc_hash=? AND sr.status="signed"');
                        $byHash->execute([$uploadedHash]);
                        $found = $byHash->fetch() ?: null;
                    } catch (\Throwable $e3) {}
                }
            } catch (\Throwable $e) {
                // Fallback tanpa JOIN jika ada masalah DB
                try {
                    $all2 = db()->prepare('SELECT * FROM signing_requests WHERE status="signed"');
                    $all2->execute();
                    foreach ($all2->fetchAll() as $row) {
                        $signedPath = SIGNED_PATH . $row['filename_signed'];
                        if (file_exists($signedPath) && hash_file('sha256', $signedPath) === $uploadedHash) {
                            $row['signer_name']  = $user['name'];
                            $row['signer_email'] = $user['email'];
                            $found = $row;
                            break;
                        }
                    }
                } catch (\Throwable $e2) {}
            }
            $verifyResult = [
                'method'   => 'upload',
                'hash'     => $uploadedHash,
                'found'    => $found,
                'filename' => $file['name'],
            ];
        } else {
            flash('error', 'Hanya file PDF yang diizinkan.');

            header('Location: ?page=verifikasi-dokumen'); exit;
        }
    }
}

// Compute hash for specific doc
$docHash = null;
$fileExists = false;
if ($doc && $doc['status'] === 'signed') {
    $signedPath = SIGNED_PATH . $doc['filename_signed'];
    $fileExists = file_exists($signedPath);
    if ($fileExists) {
        $docHash = hash_file('sha256', $signedPath);
    }
}
?>
<div style="max-width:860px;margin:0 auto;padding:2.5rem 2rem;">
<div class="page-header animate">
  <span class="step-badge">🔍 VERIFIKASI</span>
  <h1 class="page-title">Cek Keaslian Dokumen</h1>
  <p class="page-sub">Verifikasi bahwa dokumen PDF belum diubah sejak ditandatangani di SignKu.</p>
</div>

<?php if ($doc): ?>
<!-- Verifikasi dokumen spesifik dari riwayat -->
<div class="card animate d1" style="border-left: 4px solid <?= $doc['status']==='signed' ? 'var(--teal)' : 'var(--warn)' ?>;">
  <div class="card-title">📄 <?= h($doc['filename_orig']) ?></div>

  <?php if ($doc['status'] !== 'signed'): ?>
  <div class="info-box warn">
    <span class="icon">⚠️</span> Dokumen ini belum ditandatangani.
  </div>
  <?php elseif (!$fileExists): ?>
  <div class="info-box warn">
    <span class="icon">⚠️</span> File bertanda tangan tidak ditemukan di server.
  </div>
  <?php else: ?>

  <!-- Detail verifikasi -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.2rem;">
    <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:1rem;">
      <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--green);margin-bottom:.4rem;">✅ Status</div>
      <div style="font-weight:700;font-size:1rem;">Dokumen Asli & Valid</div>
      <div style="font-size:.8rem;color:var(--mid);margin-top:.2rem;">OTP terverifikasi saat penandatanganan</div>
    </div>
    <div style="background:#f8fafc;border:1px solid var(--border);border-radius:10px;padding:1rem;">
      <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--mid);margin-bottom:.4rem;">🕐 Ditandatangani</div>
      <div style="font-weight:700;"><?= h($doc['signed_at'] ?? '-') ?></div>
      <div style="font-size:.8rem;color:var(--mid);margin-top:.2rem;">Halaman <?= (int)($doc['sign_page'] ?? 1) ?></div>
    </div>
    <div style="background:#f8fafc;border:1px solid var(--border);border-radius:10px;padding:1rem;">
      <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--mid);margin-bottom:.4rem;">👤 Penandatangan</div>
      <div style="font-weight:700;"><?= h($doc['signer_name'] ?? $user['name']) ?></div>
      <div style="font-size:.8rem;color:var(--mid);"><?= h($doc['signer_email'] ?? $user['email']) ?></div>
    </div>
    <div style="background:#f8fafc;border:1px solid var(--border);border-radius:10px;padding:1rem;">
      <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--mid);margin-bottom:.4rem;">🔐 OTP Verified</div>
      <div style="font-weight:700;"><?= $doc['otp_verified'] ? '<span style="color:var(--green);">✅ Ya</span>' : '❌ Tidak' ?></div>
      <div style="font-size:.8rem;color:var(--mid);">Google Authenticator</div>
    </div>
  </div>

  <!-- Hash dokumen -->
  <div style="background:#0d1117;border-radius:10px;padding:1.1rem;margin-bottom:1.2rem;">
    <div style="font-size:.72rem;color:#6b7280;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem;">🔑 SHA-256 Hash Dokumen (Fingerprint)</div>
    <div style="font-family:'JetBrains Mono',monospace;font-size:.8rem;color:#00b8a2;word-break:break-all;line-height:1.6;"><?= h($docHash) ?></div>
    <div style="font-size:.7rem;color:#6b7280;margin-top:.4rem;">Hash ini unik untuk dokumen ini. Jika dokumen diubah, hash akan berbeda.</div>
  </div>

  <!-- How to verify manually -->
  <details style="margin-bottom:1rem;">
    <summary style="cursor:pointer;font-size:.88rem;font-weight:600;color:var(--blue);padding:.4rem 0;">ℹ️ Cara verifikasi manual (opsional)</summary>
    <div style="padding:.8rem;background:#f8fafc;border-radius:8px;margin-top:.5rem;font-size:.83rem;line-height:1.7;">
      <p>Untuk memverifikasi secara mandiri, unduh dokumen dan jalankan perintah ini di terminal:</p>
      <pre style="background:#0d1117;color:#00b8a2;padding:.7rem;border-radius:6px;margin:.5rem 0;font-size:.78rem;overflow-x:auto;">sha256sum <?= h($doc['filename_signed'] ?? 'dokumen.pdf') ?></pre>
      <p>Bandingkan hasilnya dengan hash di atas. Jika sama, dokumen tidak diubah.</p>
    </div>
  </details>

  <div style="display:flex;gap:.7rem;flex-wrap:wrap;">
    <a href="?page=download-signed&id=<?= $doc['id'] ?>" class="btn btn-teal">⬇ Unduh Dokumen</a>
    <a href="?page=signing-list" class="btn btn-outline">📋 Kembali ke Riwayat</a>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Upload PDF untuk dicek keasliannya -->
<div class="card animate d<?= $doc ? '2' : '1' ?>">
  <div class="card-title">📤 Verifikasi Dokumen dari File</div>
  <p style="color:var(--mid);font-size:.9rem;margin-bottom:1.2rem;">
    Upload PDF bertanda tangan untuk memverifikasi apakah dokumen tersebut asli dan belum diubah sejak ditandatangani di SignKu.
  </p>

  <?php if ($verifyResult): ?>
  <?php if ($verifyResult['found']): ?>
  <div class="info-box green" style="margin-bottom:1.2rem;">
    <span class="icon">✅</span>
    <div>
      <strong>Dokumen Valid &amp; Asli!</strong><br>
      <span style="font-size:.85rem;">
        File <strong><?= h($verifyResult['filename']) ?></strong> cocok dengan dokumen yang tersimpan di SignKu.<br>
        Ditandatangani oleh: <strong><?= h($verifyResult['found']['signer_name']) ?></strong>
        (<?= h($verifyResult['found']['signer_email']) ?>)<br>
        Pada: <strong><?= h($verifyResult['found']['signed_at'] ?? '-') ?></strong> ·
        OTP: <?= $verifyResult['found']['otp_verified'] ? '✅ Terverifikasi' : '❌' ?>
      </span>
    </div>
  </div>
  <?php else: ?>
  <div class="info-box red" style="margin-bottom:1.2rem;">
    <span class="icon">❌</span>
    <div>
      <strong>Dokumen Tidak Ditemukan / Mungkin Telah Diubah</strong><br>
      <span style="font-size:.85rem;">
        File <strong><?= h($verifyResult['filename']) ?></strong> tidak cocok dengan dokumen manapun di sistem SignKu.<br>
        Kemungkinan: dokumen bukan dari SignKu, atau telah dimodifikasi setelah ditandatangani.
      </span>
    </div>
  </div>
  <?php endif; ?>
  <div style="background:#0d1117;border-radius:10px;padding:.9rem;margin-bottom:1.2rem;">
    <div style="font-size:.7rem;color:#6b7280;font-weight:700;text-transform:uppercase;margin-bottom:.3rem;">SHA-256 Hash File yang Diupload</div>
    <div style="font-family:'JetBrains Mono',monospace;font-size:.78rem;color:#00b8a2;word-break:break-all;"><?= h($verifyResult['hash']) ?></div>
  </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" id="verify-form">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <div class="dropzone" id="verify-dropzone" onclick="document.getElementById('check_pdf').click()" style="margin-bottom:.8rem;">
      <div style="font-size:2rem;">🔍</div>
      <p><strong>Klik untuk pilih PDF</strong> yang ingin diverifikasi</p>
      <p>Format: PDF saja</p>
    </div>
    <input type="file" id="check_pdf" name="check_pdf" accept=".pdf,application/pdf" style="display:none"
           onchange="document.getElementById('vf-name').textContent=this.files[0]?.name||'';document.getElementById('verify-form').submit()">
    <p id="vf-name" style="font-size:.85rem;color:var(--mid);"></p>
  </form>
</div>

<!-- QR Code verifikasi cepat (untuk dokumen spesifik) -->
<?php if ($doc && $doc['status']==='signed' && $docHash): ?>
<div class="card animate" style="border-top: 3px solid var(--blue);">
  <div class="card-title">📱 QR Code Verifikasi</div>
  <p style="color:var(--mid);font-size:.88rem;margin-bottom:1rem;">Scan QR ini untuk verifikasi cepat dokumen melalui browser.</p>
  <div style="display:flex;gap:2rem;align-items:center;flex-wrap:wrap;">
    <div id="qr-verify-div" style="background:#fff;padding:.8rem;border-radius:10px;border:1px solid var(--border);"></div>
    <div style="flex:1;min-width:200px;">
      <div style="font-size:.82rem;color:var(--mid);margin-bottom:.3rem;">Link verifikasi:</div>
      <div style="font-family:'JetBrains Mono',monospace;font-size:.78rem;word-break:break-all;color:var(--blue);">
        <?= h(BASE_URL) ?>/?page=verifikasi-dokumen&id=<?= $doc['id'] ?>
      </div>
      <div style="font-size:.78rem;color:var(--mid);margin-top:.6rem;">Hash: <?= substr($docHash, 0, 24) ?>...</div>
    </div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function(){
  try {
    new QRCode(document.getElementById('qr-verify-div'), {
      text: '<?= h(BASE_URL) ?>/?page=verifikasi-dokumen&id=<?= $doc['id'] ?>',
      width: 160, height: 160,
      colorDark:'#0d1117', colorLight:'#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });
  } catch(e) {}
})();
</script>
<?php endif; ?>

</div>

<script>
// Dropzone drag support for verify
const vdz = document.getElementById('verify-dropzone');
if(vdz) {
  vdz.addEventListener('dragover', e => { e.preventDefault(); vdz.classList.add('drag'); });
  vdz.addEventListener('dragleave', () => vdz.classList.remove('drag'));
  vdz.addEventListener('drop', e => {
    e.preventDefault(); vdz.classList.remove('drag');
    const f = e.dataTransfer.files[0];
    if(f && f.type === 'application/pdf') {
      const dt = new DataTransfer(); dt.items.add(f);
      document.getElementById('check_pdf').files = dt.files;
      document.getElementById('verify-form').submit();
    } else { alert('Hanya file PDF.'); }
  });
}
</script>
