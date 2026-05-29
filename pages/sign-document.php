<?php // pages/sign-document.php — dengan draggable signature + OTP tervalidasi + auto-download
requireLogin();
$user = currentUser();

// Handle PDF upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { flash('error','Token tidak valid.'); header('Location: ?page=sign-document'); exit; }
    $file = $_FILES['pdf_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) { flash('error','Upload gagal. Kode error: ' . $file['error']); header('Location: ?page=sign-document'); exit; }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if ($mimeType !== 'application/pdf') { flash('error','Hanya file PDF yang diizinkan.'); header('Location: ?page=sign-document'); exit; }
    if ($file['size'] > 20 * 1024 * 1024) { flash('error','Ukuran file maksimal 20 MB.'); header('Location: ?page=sign-document'); exit; }
    if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);
    $origName = sanitizeFilename(pathinfo($file['name'], PATHINFO_FILENAME)) . '.pdf';
    $stored = uniqid('pdf_', true) . '_' . $origName;
    $dest = UPLOAD_PATH . $stored;
    if (!move_uploaded_file($file['tmp_name'], $dest)) { flash('error','Gagal menyimpan file.'); header('Location: ?page=sign-document'); exit; }
    try {
        $ins = db()->prepare('INSERT INTO signing_requests (user_id,filename_orig,filename_stored) VALUES (?,?,?)');
        $ins->execute([$user['id'], $origName, $stored]);
        $docId = db()->lastInsertId();
    } catch (\Throwable $e) {
        // No DB: use session
        $docId = 'sess_' . uniqid();
        if (!isset($_SESSION['signing_requests'])) $_SESSION['signing_requests'] = [];
        $_SESSION['signing_requests'][$docId] = [
            'id' => $docId, 'user_id' => $user['id'],
            'filename_orig' => $origName, 'filename_stored' => $stored,
            'status' => 'uploaded', 'otp_verified' => 0,
        ];
    }
    logActivity('upload_pdf', $origName);
    header('Location: ?page=sign-document&doc=' . $docId); exit;
}

$docId = $_GET['doc'] ?? 0;
$doc = null;
if ($docId) {
    try {
        $stmt = db()->prepare('SELECT * FROM signing_requests WHERE id=? AND user_id=?');
        $stmt->execute([$docId, $user['id']]);
        $doc = $stmt->fetch() ?: null;
    } catch (\Throwable $e) {
        $doc = $_SESSION['signing_requests'][$docId] ?? null;
    }
}
$otpActive = !empty($user['otp_secret']) && $user['otp_enabled'];
?>
<div style="max-width:980px;margin:0 auto;padding:2.5rem 2rem;">
<div class="page-header animate">
  <span class="step-badge">TANDA TANGANI</span>
  <h1 class="page-title">Tanda Tangani Dokumen</h1>
  <p class="page-sub">Upload PDF, geser kotak tanda tangan ke posisi yang diinginkan, verifikasi OTP, dokumen otomatis tersimpan.</p>
</div>

<?php if (!$otpActive): ?>
<div class="info-box warn animate">
  <span class="icon">⚠️</span>
  OTP belum aktif. <strong><a href="?page=setup-otp">Setup OTP terlebih dahulu →</a></strong> agar bisa menandatangani dokumen.
</div>
<?php endif; ?>

<?php if (!$doc): ?>
<!-- Upload form -->
<div class="card animate d1">
  <div class="card-title">📁 Upload Dokumen PDF</div>
  <form method="POST" enctype="multipart/form-data" id="upload-form">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <div class="dropzone" id="dropzone" onclick="document.getElementById('pdf_input').click()">
      <div style="font-size:2.5rem;">📄</div>
      <p><strong>Klik untuk pilih file</strong> atau seret ke sini</p>
      <p>Format: PDF · Maks. 20 MB</p>
    </div>
    <input type="file" id="pdf_input" name="pdf_file" accept=".pdf,application/pdf" style="display:none"
           onchange="document.getElementById('file-name').textContent=this.files[0]?.name||'';document.getElementById('upload-form').submit()">
    <p id="file-name" style="margin-top:.5rem;font-size:.85rem;color:var(--mid);"></p>
  </form>
</div>
<div class="info-box blue animate d2">
  <span class="icon">ℹ️</span>
  Pastikan sudah <a href="?page=setup-otp"><strong>scan QR Code OTP</strong></a> dan sudah punya
  <a href="?page=request-id"><strong>Digital ID</strong></a> sebelum menandatangani.
</div>

<?php else: ?>
<?php if ($doc['status'] === 'signed'): ?>
<div class="info-box green animate">
  <span class="icon">✅</span>
  Dokumen ini sudah ditandatangani.
  <a href="?page=download-signed&id=<?= $doc['id'] ?>" class="btn btn-teal" style="margin-left:.8rem;">⬇ Unduh Dokumen</a>
  <a href="?page=verifikasi-dokumen&id=<?= $doc['id'] ?>" class="btn btn-outline" style="margin-left:.5rem;">🔍 Cek Keaslian</a>
</div>
<?php else: ?>

<!-- OTP hint — user harus buka Google Authenticator sendiri -->
<div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:12px;padding:.9rem 1.2rem;display:flex;align-items:center;gap:.8rem;margin-bottom:1.2rem;" class="animate">
  <div style="font-size:1.4rem;">📱</div>
  <div>
    <div style="font-weight:700;font-size:.9rem;color:#166534;">Buka Google Authenticator di HP Anda</div>
    <div style="font-size:.8rem;color:#15803d;margin-top:.2rem;">Lihat kode 6 digit untuk akun <strong>SignKu (<?= h(OTP_EMAIL) ?>)</strong> lalu masukkan di kolom di bawah.</div>
  </div>
</div>

<div class="card animate d1">
  <div class="card-title">📄 <?= h($doc['filename_orig']) ?></div>

  <!-- PDF Controls -->
  <div class="pdf-controls">
    <button class="btn btn-outline" id="btn-prev">◀ Sebelumnya</button>
    <span style="font-size:.9rem;color:var(--mid);">Halaman <span id="page-num">1</span> / <span id="page-count">-</span></span>
    <button class="btn btn-outline" id="btn-next">Berikutnya ▶</button>
    <button class="btn btn-outline" id="btn-zoom-in">🔍+</button>
    <button class="btn btn-outline" id="btn-zoom-out">🔍−</button>
    <span class="badge badge-blue" id="drag-hint" style="cursor:default;">✋ Geser kotak merah ke area tanda tangan</span>
  </div>

  <!-- Upload gambar tanda tangan -->
  <div style="margin-bottom:.8rem;background:#f8fafc;border:1.5px solid var(--border);border-radius:10px;padding:.8rem 1rem;display:flex;align-items:center;gap:.9rem;flex-wrap:wrap;">
    <div style="font-size:.85rem;font-weight:600;color:var(--ink);white-space:nowrap;">&#9998;&#65039; Gambar Tanda Tangan</div>
    <label for="sig-img-input" style="cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .9rem;border-radius:99px;border:1.5px solid var(--border);font-size:.82rem;font-weight:600;background:#fff;color:var(--ink);transition:all .15s;">
      &#128194; Pilih Gambar (PNG/JPG)
    </label>
    <input type="file" id="sig-img-input" accept="image/png,image/jpeg,image/gif,image/webp" style="display:none">
    <span id="sig-img-name" style="font-size:.8rem;color:var(--mid);font-style:italic;">Belum ada — akan muncul di kotak TTD kiri</span>
    <button type="button" id="sig-img-clear" onclick="clearSigImage()" style="display:none;padding:.3rem .7rem;border-radius:99px;border:1px solid #fca5a5;background:#fff1f2;color:#991b1b;font-size:.78rem;cursor:pointer;">&#10005; Hapus</button>
  </div>

  <!-- PDF Canvas with draggable overlay -->
  <div id="pdf-canvas-wrap" style="position:relative;border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden;cursor:default;background:#e5e7eb;min-height:400px;">
    <canvas id="pdf-canvas" style="display:block;max-width:100%;"></canvas>
    <div id="sign-overlay"
         style="position:absolute;width:260px;height:80px;top:80px;left:50px;border:1.5px dashed #1a4fd6;background:#fff;cursor:move;border-radius:4px;display:flex;align-items:stretch;user-select:none;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.12);">
      <!-- Sisi kiri: area gambar TTD -->
      <div id="overlay-left" style="flex:0 0 45%;display:flex;align-items:center;justify-content:center;padding:4px;border-right:1px solid #d1d5db;position:relative;overflow:hidden;">
        <span id="overlay-ttd-placeholder" style="font-size:.65rem;color:#9ca3af;text-align:center;line-height:1.3;">&#128293;<br>TTD</span>
        <img id="overlay-sig-img" src="" alt="TTD" style="display:none;max-width:100%;max-height:100%;object-fit:contain;position:absolute;inset:3px;">
      </div>
      <!-- Sisi kanan: info -->
      <div style="flex:1;display:flex;flex-direction:column;justify-content:center;padding:4px 6px;gap:1px;">
        <span style="font-size:.5rem;font-weight:700;color:#1a4fd6;line-height:1.2;">DIGITALLY SIGNED BY UNILA</span>
        <span style="font-size:.55rem;font-weight:700;color:#0d1117;line-height:1.3;">Nama: <?= h($user['name'] ?? 'Penandatangan') ?></span>
        <span style="font-size:.5rem;color:#6b7280;line-height:1.2;">Waktu: <?= date('j/n/Y') ?> WIB</span>
      </div>
    </div>
    <!-- Resize handle -->
    <div id="resize-handle" style="position:absolute;width:14px;height:14px;background:var(--warn);border-radius:50%;cursor:se-resize;bottom:0;right:0;transform:translate(-50%,-50%);"></div>
  </div>

  <div style="margin-top:.5rem;font-size:.8rem;color:var(--mid);display:flex;gap:1.2rem;flex-wrap:wrap;">
    <span>Posisi: X=<strong id="pos-x">50</strong>px, Y=<strong id="pos-y">80</strong>px</span>
    <span>Ukuran: <strong id="pos-w">220</strong>×<strong id="pos-h">80</strong>px</span>
    <span id="page-indicator">Halaman: <strong id="pos-page">1</strong></span>
  </div>
</div>

<!-- Sign form with OTP -->
<div class="card animate d2">
  <div class="card-title">🔑 Verifikasi OTP &amp; Tanda Tangani</div>
  <form id="sign-form">
    <input type="hidden" name="doc_id"    value="<?= h($doc['id']) ?>">
    <input type="hidden" id="inp-x"       name="sign_x"    value="50">
    <input type="hidden" id="inp-y"       name="sign_y"    value="80">
    <input type="hidden" id="inp-w"       name="sign_w"    value="260">
    <input type="hidden" id="inp-h"       name="sign_h"    value="80">
    <input type="hidden" id="inp-page"    name="sign_page" value="1">
    <input type="hidden" id="inp-sig-img" name="sig_image_b64" value="">

    <div class="form-row" style="align-items:flex-end;">
      <div class="form-group">
        <label style="font-weight:700;">Kode dari Google Authenticator</label>
        <input type="text" id="otp-input" name="otp_code" maxlength="6" pattern="[0-9]{6}" required
               placeholder="_ _ _ _ _ _" autocomplete="one-time-code" inputmode="numeric"
               style="font-family:'JetBrains Mono',monospace;font-size:1.8rem;letter-spacing:.4em;text-align:center;padding:.7rem;width:100%;">
        <div style="font-size:.78rem;color:var(--mid);margin-top:.4rem;">
          📱 Buka Google Authenticator → lihat kode untuk <strong>SignKu</strong> → ketik di sini.
        </div>
      </div>
      <div class="form-group" style="display:flex;flex-direction:column;justify-content:flex-end;">
        <button class="btn btn-primary btn-lg" type="submit" id="btn-sign" style="height:52px;">
          🖊 Tanda Tangani &amp; Unduh
        </button>
      </div>
    </div>
    <div id="sign-result" style="display:none;margin-top:.5rem;"></div>
  </form>
</div>

<?php endif; ?>
<?php endif; ?>
</div>

<!-- PDF.js + signing logic -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

// OTP hanya dari Google Authenticator — tidak ada kode server di layar

<?php if ($doc && $doc['status'] !== 'signed'): ?>
// Gunakan URL relatif agar bekerja di semua environment (localhost, server, dll)
const pdfUrl = (function(){
  const base = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '');
  return base + '/uploads/pdf/<?= h($doc['filename_stored']) ?>';
})();
let pdfDoc = null, currentPage = 1, scale = 1.3;

async function renderPage(num) {
  const page = await pdfDoc.getPage(num);
  const vp = page.getViewport({ scale });
  const canvas = document.getElementById('pdf-canvas');
  const ctx = canvas.getContext('2d');
  canvas.width  = vp.width;
  canvas.height = vp.height;
  await page.render({ canvasContext: ctx, viewport: vp }).promise;
  document.getElementById('page-num').textContent   = num;
  document.getElementById('page-count').textContent = pdfDoc.numPages;
  document.getElementById('inp-page').value = num;
  document.getElementById('pos-page').textContent = num;
  // Reposition resize handle
  updateResizeHandle();
}

pdfjsLib.getDocument(pdfUrl).promise.then(pdf => {
  pdfDoc = pdf;
  renderPage(currentPage);
}).catch(err => {
  const errDiv = document.createElement('div');
  errDiv.className = 'info-box warn';
  errDiv.style.margin = '1rem';
  errDiv.innerHTML = '<span>⚠️</span> Gagal memuat PDF: ' + err.message;
  document.getElementById('pdf-canvas-wrap').prepend(errDiv);
});

document.getElementById('btn-prev').onclick = () => { if (currentPage > 1) renderPage(--currentPage); };
document.getElementById('btn-next').onclick = () => { if (pdfDoc && currentPage < pdfDoc.numPages) renderPage(++currentPage); };
document.getElementById('btn-zoom-in').onclick  = () => { scale = Math.min(scale + .2, 3); renderPage(currentPage); };
document.getElementById('btn-zoom-out').onclick = () => { scale = Math.max(scale - .2, .5); renderPage(currentPage); };

// ── Draggable & Resizable signature overlay ───────────
const overlay = document.getElementById('sign-overlay');
const wrap    = document.getElementById('pdf-canvas-wrap');
const resizeH = document.getElementById('resize-handle');
let dragging = false, resizing = false;
let dragOX = 0, dragOY = 0;
let resizeStartX = 0, resizeStartY = 0, resizeStartW = 0, resizeStartH = 0;

function updatePos() {
  const x = parseInt(overlay.style.left)  || 0;
  const y = parseInt(overlay.style.top)   || 0;
  const w = overlay.offsetWidth  || 220;
  const h = overlay.offsetHeight || 80;
  document.getElementById('pos-x').textContent = x;
  document.getElementById('pos-y').textContent = y;
  document.getElementById('pos-w').textContent = w;
  document.getElementById('pos-h').textContent = h;
  document.getElementById('inp-x').value = x;
  document.getElementById('inp-y').value = y;
  document.getElementById('inp-w').value = w;
  document.getElementById('inp-h').value = h;
  updateResizeHandle();
}

function updateResizeHandle() {
  const x = parseInt(overlay.style.left)  || 0;
  const y = parseInt(overlay.style.top)   || 0;
  const w = overlay.offsetWidth  || 220;
  const h = overlay.offsetHeight || 80;
  resizeH.style.left = (x + w - 7) + 'px';
  resizeH.style.top  = (y + h - 7) + 'px';
}

// Drag
overlay.addEventListener('mousedown', e => {
  if (e.target === resizeH) return;
  dragging = true;
  const r = overlay.getBoundingClientRect();
  dragOX = e.clientX - r.left;
  dragOY = e.clientY - r.top;
  e.preventDefault();
});

// Resize
resizeH.addEventListener('mousedown', e => {
  resizing = true;
  resizeStartX = e.clientX;
  resizeStartY = e.clientY;
  resizeStartW = overlay.offsetWidth;
  resizeStartH = overlay.offsetHeight;
  e.preventDefault();
  e.stopPropagation();
});

document.addEventListener('mousemove', e => {
  const wRect = wrap.getBoundingClientRect();
  if (dragging) {
    const x = Math.max(0, Math.min(e.clientX - wRect.left - dragOX, wRect.width  - overlay.offsetWidth));
    const y = Math.max(0, Math.min(e.clientY - wRect.top  - dragOY, wRect.height - overlay.offsetHeight));
    overlay.style.left = x + 'px';
    overlay.style.top  = y + 'px';
    updatePos();
  }
  if (resizing) {
    const newW = Math.max(80, resizeStartW + (e.clientX - resizeStartX));
    const newH = Math.max(40, resizeStartH + (e.clientY - resizeStartY));
    overlay.style.width  = newW + 'px';
    overlay.style.height = newH + 'px';
    updatePos();
  }
});
document.addEventListener('mouseup', () => { dragging = false; resizing = false; });

// Touch drag
overlay.addEventListener('touchstart', e => {
  if (e.target === resizeH) return;
  const t = e.touches[0];
  dragging = true;
  const r = overlay.getBoundingClientRect();
  dragOX = t.clientX - r.left;
  dragOY = t.clientY - r.top;
}, {passive:true});
document.addEventListener('touchmove', e => {
  if (!dragging) return;
  const t = e.touches[0];
  const wRect = wrap.getBoundingClientRect();
  const x = Math.max(0, Math.min(t.clientX - wRect.left - dragOX, wRect.width  - overlay.offsetWidth));
  const y = Math.max(0, Math.min(t.clientY - wRect.top  - dragOY, wRect.height - overlay.offsetHeight));
  overlay.style.left = x + 'px';
  overlay.style.top  = y + 'px';
  updatePos();
}, {passive:true});
document.addEventListener('touchend', () => { dragging = false; });
updatePos();

// ── Signature image upload & preview ─────────────────────
const sigInput    = document.getElementById('sig-img-input');
const sigImgEl    = document.getElementById('overlay-sig-img');
const sigPlaceholder = document.getElementById('overlay-ttd-placeholder');
const sigNameEl   = document.getElementById('sig-img-name');
const sigClearBtn = document.getElementById('sig-img-clear');
const sigB64Input = document.getElementById('inp-sig-img');

sigInput.addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function(e) {
    const b64 = e.target.result; // data:image/...;base64,...
    // Preview di overlay kiri
    sigImgEl.src = b64;
    sigImgEl.style.display = 'block';
    sigPlaceholder.style.display = 'none';
    // Simpan base64 ke hidden input (kirim ke server)
    sigB64Input.value = b64;
    // Update label
    sigNameEl.textContent = file.name;
    sigClearBtn.style.display = 'inline-flex';
  };
  reader.readAsDataURL(file);
});

function clearSigImage() {
  sigInput.value = '';
  sigImgEl.src = '';
  sigImgEl.style.display = 'none';
  sigPlaceholder.style.display = '';
  sigB64Input.value = '';
  sigNameEl.textContent = 'Belum ada — akan muncul di kotak TTD kiri';
  sigClearBtn.style.display = 'none';
}

// Auto-submit saat 6 digit diketik
document.getElementById('otp-input').addEventListener('input', function(){
  if(this.value.length === 6) {
    document.getElementById('btn-sign').textContent = '⏳ Memverifikasi…';
    document.getElementById('sign-form').dispatchEvent(new Event('submit', {bubbles:true}));
  }
});

// ── Sign form submit → auto download ──────────────────
document.getElementById('sign-form').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('btn-sign');
  btn.disabled = true; btn.textContent = '⏳ Memproses…';
  const fd = new FormData(e.target);
  try {
    const res  = await fetch('?page=api-sign-doc', { method: 'POST', body: fd });
    const data = await res.json();
    const el   = document.getElementById('sign-result');
    el.style.display = 'block';
    if (data.success) {
      el.innerHTML = '<div class="info-box green"><span class="icon">✅</span><span>' + data.message +
        ' <a class="btn btn-teal" href="?page=download-signed&id=<?= $doc['id'] ?>" id="auto-dl-btn">⬇ Unduh Sekarang</a>' +
        ' <a class="btn btn-outline" href="?page=verifikasi-dokumen&id=<?= $doc['id'] ?>" style="margin-left:.4rem;">🔍 Cek Keaslian</a>' +
        '</span></div>';
      btn.disabled = true; btn.textContent = '✅ Selesai';
      // Auto-download
      setTimeout(function() {
        var dlBtn = document.getElementById('auto-dl-btn');
        if(dlBtn) dlBtn.click();
      }, 800);
      // Update heading
      document.getElementById('drag-hint').textContent = '✅ Dokumen selesai ditandatangani';
    } else {
      el.innerHTML = '<div class="info-box red"><span class="icon">❌</span>' + data.message + '<br><small style="opacity:.8;">💡 Pastikan kode dari Google Authenticator masih berlaku (belum lewat 30 detik).</small></div>';
      btn.disabled = false; btn.textContent = '🖊 Tanda Tangani & Unduh';
    }
  } catch(err) {
    document.getElementById('sign-result').innerHTML = '<div class="info-box red"><span>❌</span> Kesalahan jaringan. Coba lagi.</div>';
    btn.disabled = false; btn.textContent = '🖊 Tanda Tangani & Unduh';
  }
});


<?php endif; ?>

// Dropzone
const dz = document.getElementById('dropzone');
if(dz) {
  dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag'); });
  dz.addEventListener('dragleave', () => dz.classList.remove('drag'));
  dz.addEventListener('drop', e => {
    e.preventDefault(); dz.classList.remove('drag');
    const f = e.dataTransfer.files[0];
    if(f && f.type === 'application/pdf') {
      const dt = new DataTransfer(); dt.items.add(f);
      document.getElementById('pdf_input').files = dt.files;
      document.getElementById('upload-form').submit();
    } else { alert('Hanya file PDF yang diizinkan.'); }
  });
}
</script>
