<?php // pages/setup-otp.php
requireLogin();
$user = currentUser();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { flash('error','Token tidak valid.'); header('Location: ?page=setup-otp'); exit; }

    $action = $_POST['action'] ?? '';

    // Generate QR baru
    if ($action === 'generate') {
        $secret = TOTP::generateSecret();
        // Simpan ke session — satu-satunya sumber kebenaran
        db()->prepare('UPDATE users SET otp_secret=? WHERE id=?')->execute([$secret,$user['id']]);
        $_SESSION['mock_otp_secret'] = $secret;
        unset($_SESSION['otp_pending_secret']);
        logActivity('otp_generate', 'Secret OTP baru di-generate');
        header('Location: ?page=setup-otp&step=scan'); exit;
    }

    // Verifikasi kode OTP dari authenticator
    if ($action === 'verify_setup') {
        $code   = trim($_POST['otp_code'] ?? '');
        $secret = $user['otp_secret'] ?? ($_SESSION['mock_otp_secret'] ?? '');

        if (!$secret) {
            flash('error','Secret OTP tidak ditemukan. Klik "Buat QR Code Baru" dulu.');
            header('Location: ?page=setup-otp'); exit;
        }
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            flash('error','Kode OTP harus 6 digit angka.');
            header('Location: ?page=setup-otp&step=scan'); exit;
        }

        // Verifikasi dengan drift ±2 window (toleransi ±60 detik clock skew)
        if (TOTP::verify($secret, $code, 2)) {
            // Simpan ke DB kalau ada
            try {
                db()->prepare('UPDATE users SET otp_secret=?,otp_enabled=1 WHERE id=?')
                    ->execute([$secret, $user['id']]);
            } catch (\Throwable $e) {}
            logActivity('otp_setup', 'OTP berhasil diverifikasi dan aktif');
            flash('success','✅ OTP berhasil dikonfigurasi! Akun Anda sekarang terlindungi.');
            header('Location: ?page=setup-otp'); exit;
        }

        // Gagal — tampilkan info debugging yang berguna
        $serverCode = TOTP::getCode($secret);
        flash('error','Kode OTP tidak cocok. Kode server saat ini: <strong>' . $serverCode . '</strong> — Pastikan waktu HP Anda sinkron dengan internet.');
        header('Location: ?page=setup-otp&step=scan'); exit;
    }

    // Reset OTP
    if ($action === 'request_reset') {
        unset($_SESSION['mock_otp_secret']);
        try {
            db()->prepare('UPDATE users SET otp_secret=NULL, otp_enabled=0 WHERE id=?')->execute([$user['id']]);
        } catch (\Throwable $e) {}
        logActivity('otp_reset','OTP direset');
        flash('success','OTP berhasil direset. Silakan buat QR Code baru.');
        header('Location: ?page=setup-otp'); exit;
    }
}

// Tentukan state
$step          = $_GET['step'] ?? '';
$activeSecret  = $_SESSION['mock_otp_secret'] ?? '';
$isOtpReady    = !empty($activeSecret);

// Kalau step=scan tapi tidak ada secret, redirect ke default
if ($step === 'scan' && !$activeSecret) {
    header('Location: ?page=setup-otp'); exit;
}

// Tab aktif
$activeTab = ($step === 'scan') ? 'tab-setup' : 'tab-install';
?>

<div style="max-width:880px;margin:0 auto;padding:2.5rem 2rem;">
<div class="page-header animate">
  <span class="step-badge">LANGKAH 01</span>
  <h1 class="page-title">Setup & Verifikasi OTP</h1>
  <p class="page-sub">Hubungkan akun Anda dengan Google Authenticator untuk verifikasi tanda tangan digital.</p>
</div>

<!-- Status Banner -->
<div class="<?= $isOtpReady ? 'info-box green' : 'info-box warn' ?> animate d1" style="margin-bottom:1.5rem;">
  <span class="icon"><?= $isOtpReady ? '✅' : '⚠️' ?></span>
  <span>
    <?= $isOtpReady
      ? '<strong>OTP Aktif.</strong> Akun Anda dilindungi. Gunakan kode dari Google Authenticator untuk menandatangani dokumen.'
      : '<strong>OTP Belum Dikonfigurasi.</strong> Klik tab B untuk membuat QR Code dan menghubungkan Google Authenticator.' ?>
  </span>
</div>

<div class="card animate d2">
  <div class="tabs" id="otp-tabs">
    <button class="tab <?= $activeTab === 'tab-install' ? 'active' : '' ?>" data-target="tab-install">A. Instalasi Aplikasi</button>
    <button class="tab <?= $activeTab === 'tab-setup'   ? 'active' : '' ?>" data-target="tab-setup">B. Setup OTP</button>
    <button class="tab" data-target="tab-reset">C. Reset OTP</button>
  </div>

  <!-- TAB A: INSTALASI -->
  <div class="tab-panel <?= $activeTab === 'tab-install' ? 'active' : '' ?>" id="tab-install">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Instal <strong>Google Authenticator</strong> di ponsel Anda.</p></li>
      <li><div class="instr-num">2</div><p><strong>Android:</strong> Play Store → cari "Google Authenticator" → Install.</p></li>
      <li><div class="instr-num">3</div><p><strong>iPhone:</strong> App Store → cari "Google Authenticator" → Get.</p></li>
    </ul>
    <div class="info-box blue mt-2"><span class="icon">ℹ️</span> Authy, FreeOTP, atau 1Password juga kompatibel.</div>
    <div class="mt-3">
      <button class="btn btn-primary" onclick="
        document.querySelectorAll('#otp-tabs .tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelector('[data-target=tab-setup]').classList.add('active');
        document.getElementById('tab-setup').classList.add('active');
      ">Lanjut ke Setup OTP →</button>
    </div>
  </div>

  <!-- TAB B: SETUP -->
  <div class="tab-panel <?= $activeTab === 'tab-setup' ? 'active' : '' ?>" id="tab-setup">

    <?php if ($step === 'scan' && $activeSecret): ?>
    <!-- ── SCAN & VERIFY STEP ── -->
    <div class="qr-wrap animate">
      <p style="font-weight:600;font-size:.95rem;">1. Scan QR Code ini dengan Google Authenticator</p>

      <!-- QR rendered by qrcode.js — no external API needed -->
      <div id="qr-canvas-wrap" style="display:flex;justify-content:center;margin:.8rem 0;">
        <div id="qr-div"></div>
      </div>
      <div id="qr-fallback" style="display:none;" class="info-box warn">
        <span>⚠️</span> QR tidak tampil. Gunakan kode manual di bawah.
      </div>

      <div style="margin-top:.5rem;">
        <p style="font-size:.8rem;color:var(--mid);margin-bottom:.4rem;">Atau tambah manual di aplikasi (pilih "Enter key manually"):</p>
        <div class="secret-display" id="secret-text" style="cursor:pointer;user-select:all;" title="Klik untuk copy">
          <?= h(chunk_split($activeSecret, 4, ' ')) ?>
        </div>
        <p style="font-size:.75rem;color:var(--mid);margin-top:.3rem;">Klik secret di atas untuk menyalin</p>
      </div>
    </div>

    <!-- OTP countdown timer -->
    <div style="display:flex;align-items:center;gap:.8rem;margin:1rem 0;padding:.8rem 1rem;background:#f8fafc;border-radius:10px;border:1px solid var(--border);">
      <div style="font-size:.85rem;color:var(--mid);">⏱ Kode berubah dalam:</div>
      <div id="otp-timer" style="font-family:'JetBrains Mono',monospace;font-size:1.1rem;font-weight:700;color:var(--blue);">30s</div>
      <div style="flex:1;height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden;">
        <div id="otp-progress" style="height:100%;background:var(--blue);border-radius:3px;transition:width .9s linear;"></div>
      </div>
    </div>

    <form method="POST" style="max-width:360px;" id="verify-form">
      <input type="hidden" name="csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="action" value="verify_setup">
      <div class="form-group">
        <label>2. Masukkan kode 6-digit dari aplikasi</label>
        <input type="text" name="otp_code" id="otp-input" maxlength="6" pattern="[0-9]{6}" required
               inputmode="numeric" placeholder="000000" autocomplete="off"
               style="font-family:'JetBrains Mono',monospace;font-size:1.5rem;letter-spacing:.3em;text-align:center;padding:.8rem;"
               autofocus>
      </div>
      <button class="btn btn-primary btn-lg" type="submit" id="btn-verify" style="width:100%;justify-content:center;">
        ✅ Verifikasi & Aktifkan OTP
      </button>
    </form>

    <div class="info-box warn mt-2">
      <span class="icon">⚠️</span>
      <span>Pastikan <strong>tanggal & waktu HP</strong> sudah otomatis/sinkron internet. Kode OTP bergantung pada waktu yang akurat.</span>
    </div>

    <!-- Load qrcode.js and render -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
    // Render QR Code
    (function() {
      var uri = <?= json_encode(TOTP::getQrDataUri($activeSecret, $user['email'])) ?>;
      try {
        new QRCode(document.getElementById('qr-div'), {
          text: uri, width: 240, height: 240,
          colorDark: '#0d1117', colorLight: '#ffffff',
          correctLevel: QRCode.CorrectLevel.M
        });
      } catch(e) {
        document.getElementById('qr-canvas-wrap').style.display = 'none';
        document.getElementById('qr-fallback').style.display = 'block';
        console.error('QRCode error:', e);
      }
    })();

    // Copy secret on click
    document.getElementById('secret-text').addEventListener('click', function() {
      var text = '<?= $activeSecret ?>';
      navigator.clipboard.writeText(text).then(function() {
        document.getElementById('secret-text').style.background = '#166534';
        setTimeout(function() { document.getElementById('secret-text').style.background = ''; }, 1000);
      }).catch(function() {
        // Fallback
        var ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta);
        ta.select(); document.execCommand('copy');
        document.body.removeChild(ta);
      });
    });

    // OTP countdown
    function updateTimer() {
      var now = Math.floor(Date.now() / 1000);
      var sec  = 30 - (now % 30);
      var pct  = (sec / 30) * 100;
      document.getElementById('otp-timer').textContent = sec + 's';
      document.getElementById('otp-progress').style.width = pct + '%';
      var el = document.getElementById('otp-timer');
      el.style.color = sec <= 5 ? 'var(--warn)' : 'var(--blue)';
    }
    setInterval(updateTimer, 1000);
    updateTimer();

    // Auto-submit when 6 digits entered
    document.getElementById('otp-input').addEventListener('input', function() {
      if (this.value.length === 6) {
        document.getElementById('btn-verify').textContent = '⏳ Memverifikasi...';
        document.getElementById('verify-form').submit();
      }
    });
    </script>

    <?php else: ?>
    <!-- ── GENERATE STEP ── -->
    <?php if ($isOtpReady): ?>
    <!-- OTP sudah ada — tampilkan QR yang aktif -->
    <div class="info-box green" style="margin-bottom:1rem;">
      <span class="icon">✅</span>
      <span>OTP sudah aktif dan terhubung. Jika perlu setup ulang, klik tombol di bawah.</span>
    </div>
    <div style="padding:1rem;background:#f8fafc;border-radius:10px;font-size:.88rem;margin-bottom:1rem;">
      <strong>Akun:</strong> <?= h($user['email']) ?><br>
      <strong>Status:</strong> <span class="badge badge-green">✅ Aktif</span>
    </div>
    <?php endif; ?>

    <ul class="instr-list" style="margin-bottom:1.5rem;">
      <li><div class="instr-num">1</div><p>Pastikan <strong>Google Authenticator</strong> sudah terinstal di ponsel.</p></li>
      <li><div class="instr-num">2</div><p>Klik <strong>Buat QR Code</strong> di bawah — sistem akan membuat secret OTP unik.</p></li>
      <li><div class="instr-num">3</div><p>Scan QR Code dengan aplikasi Authenticator.</p></li>
      <li><div class="instr-num">4</div><p>Masukkan kode 6-digit untuk konfirmasi.</p></li>
    </ul>
    <form method="POST">
      <input type="hidden" name="csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="action" value="generate">
      <button class="btn btn-primary btn-lg" type="submit">🔑 <?= $isOtpReady ? 'Buat QR Code Baru' : 'Buat QR Code OTP' ?></button>
    </form>
    <?php endif; ?>

  </div><!-- /tab-setup -->

  <!-- TAB C: RESET -->
  <div class="tab-panel" id="tab-reset">
    <ul class="instr-list" style="margin-bottom:1.5rem;">
      <li><div class="instr-num">1</div><p>Klik <strong>Reset OTP</strong> — secret OTP lama akan dihapus.</p></li>
      <li><div class="instr-num">2</div><p>Setelah reset, buka tab <strong>B. Setup OTP</strong> dan buat QR baru.</p></li>
      <li><div class="instr-num">3</div><p>Scan QR baru dengan Google Authenticator.</p></li>
    </ul>
    <form method="POST" onsubmit="return confirm('Yakin ingin reset OTP? Secret lama akan dihapus dan Anda perlu setup ulang.');">
      <input type="hidden" name="csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="action" value="request_reset">
      <button class="btn btn-danger" type="submit">🔄 Reset OTP Sekarang</button>
    </form>
  </div>

</div>
</div>
