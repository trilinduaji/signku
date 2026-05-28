<?php // pages/sign-document.php
requireLogin();
$user = currentUser();

// Handle PDF upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { flash('error','Token tidak valid.'); header('Location: ?page=sign-document'); exit; }

    $file = $_FILES['pdf_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) { flash('error','Upload gagal. Kode error: ' . $file['error']); header('Location: ?page=sign-document'); exit; }

    // FIX: validasi MIME type dengan finfo, bukan hanya $_FILES['type'] yang bisa dimanipulasi
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if ($mimeType !== 'application/pdf') {
        flash('error','Hanya file PDF yang diizinkan.');
        header('Location: ?page=sign-document'); exit;
    }
    if ($file['size'] > 20 * 1024 * 1024) { flash('error','Ukuran file maksimal 20 MB.'); header('Location: ?page=sign-document'); exit; }

    // Pastikan folder upload ada
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }

    $origName = sanitizeFilename(pathinfo($file['name'], PATHINFO_FILENAME)) . '.pdf';
    $stored   = uniqid('pdf_', true) . '_' . $origName;
    $dest     = UPLOAD_PATH . $stored;
    if (!move_uploaded_file($file['tmp_name'], $dest)) { flash('error','Gagal menyimpan file.'); header('Location: ?page=sign-document'); exit; }

    $ins = db()->prepare('INSERT INTO signing_requests (user_id,filename_orig,filename_stored) VALUES (?,?,?)');
    $ins->execute([$user['id'], $origName, $stored]);
    $docId = db()->lastInsertId();
    logActivity('upload_pdf', $origName);
    header('Location: ?page=sign-document&doc=' . $docId); exit;
}

$docId = (int)($_GET['doc'] ?? 0);
$doc   = null;
if ($docId) {
    try {
        $stmt = db()->prepare('SELECT * FROM signing_requests WHERE id=? AND user_id=?');
        $stmt->execute([$docId, $user['id']]);
        $doc = $stmt->fetch() ?: null;
    } catch (\Throwable $e) {}
}
?>
<div style="max-width:960px;margin:0 auto;padding:2.5rem 2rem;">
<div class="page-header animate">
  <span class="step-badge">TANDA TANGANI</span>
  <h1 class="page-title">Tanda Tangani Dokumen</h1>
  <p class="page-sub">Upload PDF, atur posisi tanda tangan, verifikasi dengan OTP, dan unduh dokumen bertanda tangan.</p>
</div>

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
  Sebelum menandatangani, pastikan sudah <strong><a href="?page=setup-otp">scan QR Code OTP</a></strong> di halaman Setup OTP menggunakan Google Authenticator.
  Secret OTP yang aktif tersimpan di sesi Anda.
</div>

<?php else: ?>
<!-- PDF viewer and signing -->
<?php if ($doc['status'] === 'signed'): ?>
<div class="info-box green animate">
  <span class="icon">✅</span>
  Dokumen ini sudah ditandatangani. <a href="?page=download-signed&id=<?= $doc['id'] ?>" class="btn btn-teal" style="margin-left:.8rem;">⬇ Unduh Dokumen</a>
</div>
<?php else: ?>

<div class="card animate d1">
  <div class="card-title">📄 <?= h($doc['filename_orig']) ?></div>

  <!-- PDF Controls -->
  <div class="pdf-controls">
    <button class="btn btn-outline" id="btn-prev">◀ Sebelumnya</button>
    <span style="font-size:.9rem;color:var(--mid);">Halaman <span id="page-num">1</span> / <span id="page-count">-</span></span>
    <button class="btn btn-outline" id="btn-next">Berikutnya ▶</button>
    <button class="btn btn-outline" id="btn-zoom-in">🔍+</button>
    <button class="btn btn-outline" id="btn-zoom-out">🔍−</button>
    <span class="badge badge-blue" style="margin-left:.5rem;">Seret kotak merah ke area tanda tangan</span>
  </div>

  <!-- PDF Canvas -->
  <div id="pdf-canvas-wrap">
    <canvas id="pdf-canvas"></canvas>
    <!-- FIX: hapus style="display:flex" yang konflik dengan display:none awal -->
    <div id="sign-overlay" style="width:200px;height:80px;top:60px;left:40px;">
      ✍ Tanda Tangan
    </div>
  </div>

  <div style="margin-top:.6rem;font-size:.8rem;color:var(--mid);">
    Posisi X: <strong id="pos-x">40</strong>px · Y: <strong id="pos-y">60</strong>px ·
    Lebar: <strong id="pos-w">200</strong>px · Tinggi: <strong id="pos-h">80</strong>px
  </div>
</div>

<!-- Sign form with OTP -->
<div class="card animate d2">
  <div class="card-title">🔑 Verifikasi &amp; Tanda Tangani</div>
  <form id="sign-form">
    <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
    <input type="hidden" id="inp-x"    name="sign_x"    value="40">
    <input type="hidden" id="inp-y"    name="sign_y"    value="60">
    <input type="hidden" id="inp-w"    name="sign_w"    value="200">
    <input type="hidden" id="inp-h"    name="sign_h"    value="80">
    <input type="hidden" id="inp-page" name="sign_page" value="1">

    <div class="form-row">
      <div class="form-group">
        <label>Kode OTP <small style="color:var(--mid);font-weight:400;">(dari Google Authenticator)</small></label>
        <input type="text" id="otp-input" name="otp_code" maxlength="6" pattern="[0-9]{6}" required
               placeholder="000000" autocomplete="off" inputmode="numeric"
               style="font-family:'JetBrains Mono',monospace;font-size:1.4rem;letter-spacing:.25em;text-align:center;">
        <div style="margin-top:.4rem;font-size:.8rem;color:var(--mid);">
          Kode berlaku: <span id="otp-countdown" style="font-weight:700;color:var(--blue);">30</span>s
        </div>
      </div>
      <div class="form-group" style="display:flex;flex-direction:column;justify-content:flex-end;">
        <button class="btn btn-primary btn-lg" type="submit" id="btn-sign">
          🖊 Tanda Tangani Dokumen
        </button>
      </div>
    </div>
    <div id="sign-result" style="display:none;"></div>
  </form>
</div>

<?php endif; ?>
<?php endif; ?>

</div>

<!-- PDF.js + signing logic -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc =
  'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

<?php if ($doc && $doc['status'] !== 'signed'): ?>
// FIX: URL PDF menggunakan BASE_URL yang benar ke folder uploads/pdf/
const pdfUrl = '<?= BASE_URL ?>/uploads/pdf/<?= h($doc['filename_stored']) ?>';
let pdfDoc = null, currentPage = 1, scale = 1.3;

async function renderPage(num) {
  const page   = await pdfDoc.getPage(num);
  const vp     = page.getViewport({ scale });
  const canvas = document.getElementById('pdf-canvas');
  const ctx    = canvas.getContext('2d');
  canvas.width  = vp.width;
  canvas.height = vp.height;
  await page.render({ canvasContext: ctx, viewport: vp }).promise;
  document.getElementById('page-num').textContent   = num;
  document.getElementById('page-count').textContent = pdfDoc.numPages;
  document.getElementById('inp-page').value = num;
}

pdfjsLib.getDocument(pdfUrl).promise.then(pdf => {
  pdfDoc = pdf;
  renderPage(currentPage);
}).catch(err => {
  // FIX: tampilkan error tanpa menghapus overlay — buat elemen error terpisah
  const errDiv = document.createElement('div');
  errDiv.className = 'info-box warn';
  errDiv.style.margin = '1rem';
  errDiv.innerHTML = '<span>⚠️</span> Gagal memuat PDF: ' + err.message +
    '<br><small>Pastikan BASE_URL di config.php sudah benar dan file tersedia.</small>';
  document.getElementById('pdf-canvas-wrap').prepend(errDiv);
});

document.getElementById('btn-prev').onclick = () => { if (currentPage > 1) renderPage(--currentPage); };
document.getElementById('btn-next').onclick = () => { if (pdfDoc && currentPage < pdfDoc.numPages) renderPage(++currentPage); };
document.getElementById('btn-zoom-in').onclick  = () => { scale = Math.min(scale + .2, 3); renderPage(currentPage); };
document.getElementById('btn-zoom-out').onclick = () => { scale = Math.max(scale - .2, .5); renderPage(currentPage); };

// ── Draggable overlay ─────────────────────────────────────
const overlay = document.getElementById('sign-overlay');
const wrap    = document.getElementById('pdf-canvas-wrap');
let dragging  = false, dragOX = 0, dragOY = 0;

function updatePos() {
  const x = parseInt(overlay.style.left)  || 0;
  const y = parseInt(overlay.style.top)   || 0;
  const w = parseInt(overlay.style.width) || 200;
  const h = parseInt(overlay.style.height)|| 80;
  document.getElementById('pos-x').textContent = x;
  document.getElementById('pos-y').textContent = y;
  document.getElementById('pos-w').textContent = w;
  document.getElementById('pos-h').textContent = h;
  document.getElementById('inp-x').value = x;
  document.getElementById('inp-y').value = y;
  document.getElementById('inp-w').value = w;
  document.getElementById('inp-h').value = h;
}

overlay.addEventListener('mousedown', e => {
  dragging = true;
  // FIX: simpan offset dari sudut kiri-atas overlay ke posisi mouse
  const oRect = overlay.getBoundingClientRect();
  dragOX = e.clientX - oRect.left;
  dragOY = e.clientY - oRect.top;
  e.preventDefault();
});
document.addEventListener('mousemove', e => {
  if (!dragging) return;
  const wRect = wrap.getBoundingClientRect();
  const x = Math.max(0, Math.min(e.clientX - wRect.left - dragOX, wRect.width  - overlay.offsetWidth));
  const y = Math.max(0, Math.min(e.clientY - wRect.top  - dragOY, wRect.height - overlay.offsetHeight));
  overlay.style.left = x + 'px';
  overlay.style.top  = y + 'px';
  updatePos();
});
document.addEventListener('mouseup', () => { dragging = false; });

// Touch support
overlay.addEventListener('touchstart', e => {
  const t = e.touches[0];
  dragging = true;
  const oRect = overlay.getBoundingClientRect();
  dragOX = t.clientX - oRect.left;
  dragOY = t.clientY - oRect.top;
}, {passive: true});
document.addEventListener('touchmove', e => {
  if (!dragging) return;
  const t = e.touches[0];
  const wRect = wrap.getBoundingClientRect();
  const x = Math.max(0, Math.min(t.clientX - wRect.left - dragOX, wRect.width  - overlay.offsetWidth));
  const y = Math.max(0, Math.min(t.clientY - wRect.top  - dragOY, wRect.height - overlay.offsetHeight));
  overlay.style.left = x + 'px';
  overlay.style.top  = y + 'px';
  updatePos();
}, {passive: true});
document.addEventListener('touchend', () => { dragging = false; });

updatePos();

// ── OTP Countdown ─────────────────────────────────────────
function otpCountdown() {
  const sec = 30 - (Math.floor(Date.now() / 1000) % 30);
  const el  = document.getElementById('otp-countdown');
  el.textContent = sec;
  el.style.color = sec <= 5 ? 'var(--warn)' : 'var(--blue)';
}
setInterval(otpCountdown, 1000);
otpCountdown();

// ── Sign form submit ──────────────────────────────────────
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
        ' <a class="btn btn-teal" href="?page=download-signed&id=<?= $doc['id'] ?>">⬇ Unduh Sekarang</a></span></div>';
      btn.disabled = true; btn.textContent = '✅ Selesai';
    } else {
      el.innerHTML = '<div class="info-box red"><span class="icon">❌</span>' + data.message + '</div>';
      btn.disabled = false; btn.textContent = '🖊 Tanda Tangani Dokumen';
    }
  } catch (err) {
    document.getElementById('sign-result').innerHTML = '<div class="info-box red"><span class="icon">❌</span>Terjadi kesalahan jaringan.</div>';
    btn.disabled = false; btn.textContent = '🖊 Tanda Tangani Dokumen';
  }
});

<?php endif; ?>

// Dropzone drag-over style
const dz = document.getElementById('dropzone');
if (dz) {
  dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag'); });
  dz.addEventListener('dragleave', ()  => dz.classList.remove('drag'));
  dz.addEventListener('drop', e => {
    e.preventDefault(); dz.classList.remove('drag');
    const f = e.dataTransfer.files[0];
    if (f && f.type === 'application/pdf') {
      const dt = new DataTransfer(); dt.items.add(f);
      document.getElementById('pdf_input').files = dt.files;
      document.getElementById('upload-form').submit();
    } else {
      alert('Hanya file PDF yang diizinkan.');
    }
  });
}
</script>
