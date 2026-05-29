<?php // pages/setup-otp.php
requireLogin();
$user = currentUser();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        flash('error','Token tidak valid.'); header('Location: ?page=setup-otp'); exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $secret = TOTP::generateSecret();
        // Simpan ke SEMUA tempat: file persisten + session + DB
        file_put_contents(OTP_SECRET_FILE, $secret);
        $_SESSION['mock_otp_secret'] = $secret;
        // Simpan sementara ke DB juga (belum enable, sampai verify berhasil)
        try { db()->prepare('UPDATE users SET otp_secret=? WHERE id=?')->execute([$secret, $user['id']]); } catch(\Throwable $e){}
        logActivity('otp_generate', 'Secret OTP baru di-generate');
        header('Location: ?page=setup-otp&step=scan'); exit;
    }

    if ($action === 'verify_setup') {
        $code   = trim($_POST['otp_code'] ?? '');
        // Ambil secret dari session ATAU file persisten
        $secret = $_SESSION['mock_otp_secret'] ?? '';
        if (!$secret && file_exists(OTP_SECRET_FILE)) {
            $secret = trim(file_get_contents(OTP_SECRET_FILE));
            if ($secret) $_SESSION['mock_otp_secret'] = $secret;
        }
        if (!$secret) {
            flash('error','Session hilang. Klik "Buat QR Code Baru".'); header('Location: ?page=setup-otp'); exit;
        }
        // Verifikasi dengan drift 2 (±60 detik) — standar Google Authenticator
        if (TOTP::verify($secret, $code, 2)) {
            // Simpan ke SEMUA tempat dan aktifkan OTP
            file_put_contents(OTP_SECRET_FILE, $secret);
            $_SESSION['mock_otp_secret'] = $secret;
            try {
                db()->prepare('UPDATE users SET otp_secret=?,otp_enabled=1 WHERE id=?')->execute([$secret, $user['id']]);
            } catch(\Throwable $e){}
            logActivity('otp_activated', 'OTP berhasil dikonfigurasi dengan Google Authenticator');
            flash('success','✅ OTP berhasil dikonfigurasi! Sekarang gunakan kode dari Google Authenticator untuk menandatangani dokumen.');
            header('Location: ?page=setup-otp'); exit;
        }
        // Kode salah — tampilkan kode yang benar dari server untuk bantu debug
        $serverCode = TOTP::getCode($secret);
        flash('error','Kode OTP salah. Kode server saat ini: <strong>' . $serverCode . '</strong> — pastikan waktu HP sinkron otomatis.');
        header('Location: ?page=setup-otp&step=scan'); exit;
    }

    if ($action === 'reset') {
        unset($_SESSION['mock_otp_secret']);
        // Hapus file secret persisten agar bisa generate baru
        if (file_exists(OTP_SECRET_FILE)) unlink(OTP_SECRET_FILE);
        try { db()->prepare('UPDATE users SET otp_secret=NULL,otp_enabled=0 WHERE id=?')->execute([$user['id']]); } catch(\Throwable $e){}
        logActivity('otp_reset', 'OTP direset oleh user');
        flash('success','OTP direset. Silakan buat QR baru.');
        header('Location: ?page=setup-otp'); exit;
    }
}

$step = $_GET['step'] ?? '';

// Ambil secret aktif: session lebih prioritas (fresh), lalu file persisten
$activeSecret = $_SESSION['mock_otp_secret'] ?? '';
if (!$activeSecret) {
    $activeSecret = readOtpSecretFromFile();
    if ($activeSecret) $_SESSION['mock_otp_secret'] = $activeSecret;
}

$isActive  = !empty($activeSecret);
$activeTab = ($step === 'scan') ? 'tab-setup' : ($isActive ? 'tab-setup' : 'tab-install');
if ($step === 'scan' && !$activeSecret) { header('Location: ?page=setup-otp'); exit; }
?>

<div style="max-width:860px;margin:0 auto;padding:2.5rem 2rem;">
<div class="page-header animate">
  <span class="step-badge">LANGKAH 01</span>
  <h1 class="page-title">Setup OTP</h1>
  <p class="page-sub">Hubungkan akun Anda dengan Google Authenticator.</p>
</div>

<div class="<?= $isActive ? 'info-box green' : 'info-box warn' ?> animate d1" style="margin-bottom:1.5rem;">
  <span class="icon"><?= $isActive ? '✅' : '⚠️' ?></span>
  <span><?= $isActive ? '<strong>OTP Aktif.</strong> Gunakan kode dari Google Authenticator untuk menandatangani dokumen.' : '<strong>OTP Belum Aktif.</strong> Buka tab B untuk membuat QR Code.' ?></span>
</div>

<div class="card animate d2">
  <div class="tabs" id="otp-tabs">
    <button class="tab <?= $activeTab==='tab-install'?'active':'' ?>" data-target="tab-install">A. Instalasi</button>
    <button class="tab <?= $activeTab==='tab-setup'?'active':'' ?>"   data-target="tab-setup">B. Setup OTP</button>
    <button class="tab" data-target="tab-reset">C. Reset</button>
  </div>

  <!-- TAB A -->
  <div class="tab-panel <?= $activeTab==='tab-install'?'active':'' ?>" id="tab-install">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Instal <strong>Google Authenticator</strong> di ponsel Anda (Android / iPhone).</p></li>
      <li><div class="instr-num">2</div><p>Buka tab <strong>B. Setup OTP</strong> lalu klik <strong>Buat QR Code</strong>.</p></li>
      <li><div class="instr-num">3</div><p>Scan QR Code dengan Google Authenticator, lalu masukkan kode 6 digit.</p></li>
    </ul>
    <button class="btn btn-primary mt-3" onclick="switchTab('tab-setup')">Lanjut ke Setup →</button>
  </div>

  <!-- TAB B -->
  <div class="tab-panel <?= $activeTab==='tab-setup'?'active':'' ?>" id="tab-setup">
    <?php if ($step === 'scan' && $activeSecret): ?>

    <!-- ═══ SCAN & VERIFY ═══ -->

    <!-- Langkah 1: QR Code -->
    <div style="margin-bottom:1.8rem;">
      <div style="display:flex;align-items:center;gap:.7rem;margin-bottom:1rem;">
        <div style="background:var(--blue);color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;flex-shrink:0;">1</div>
        <p style="font-weight:700;font-size:1rem;margin:0;">Buka Google Authenticator → Scan QR Code ini</p>
      </div>

      <div style="background:#f8fafc;border:1.5px solid var(--border);border-radius:14px;padding:1.5rem;text-align:center;">
        <div id="qr-div" style="display:inline-block;background:#fff;padding:12px;border-radius:10px;border:1px solid #e2e8f0;"></div>
        <div id="qr-fallback" style="display:none;" class="info-box warn"><span>⚠️</span> QR tidak tampil, gunakan kode manual di bawah.</div>

        <div style="margin-top:1.2rem;padding-top:1.2rem;border-top:1px solid var(--border);">
          <p style="font-size:.8rem;color:var(--mid);margin-bottom:.4rem;">📋 Atau tambahkan secara manual di Google Authenticator:</p>
          <div class="secret-display" id="secret-display" style="cursor:pointer;letter-spacing:.12em;" title="Klik untuk salin"><?= h($activeSecret) ?></div>
          <p style="font-size:.72rem;color:var(--mid);margin-top:.3rem;">Klik untuk menyalin kode secret</p>
        </div>
      </div>
    </div>

    <!-- Langkah 2: Input kode dari HP -->
    <div style="margin-bottom:1.5rem;">
      <div style="display:flex;align-items:center;gap:.7rem;margin-bottom:1rem;">
        <div style="background:var(--blue);color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;flex-shrink:0;">2</div>
        <p style="font-weight:700;font-size:1rem;margin:0;">Masukkan kode 6 digit dari Google Authenticator</p>
      </div>

      <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:12px;padding:1.2rem;margin-bottom:1rem;font-size:.85rem;">
        <strong>📱 Setelah scan QR</strong>, Google Authenticator akan menampilkan kode 6 digit yang berubah tiap 30 detik.
        Masukkan kode tersebut di bawah — <strong>jangan gunakan kode dari layar ini</strong>.
      </div>

      <form method="POST" id="verify-form" style="max-width:340px;">
        <input type="hidden" name="csrf"   value="<?= csrf() ?>">
        <input type="hidden" name="action" value="verify_setup">
        <input type="text" name="otp_code" id="otp-input" maxlength="6" pattern="[0-9]{6}"
               inputmode="numeric" placeholder="_ _ _ _ _ _" autocomplete="one-time-code"
               style="font-family:'JetBrains Mono',monospace;font-size:2rem;letter-spacing:.4em;text-align:center;padding:.8rem;width:100%;margin-bottom:.8rem;"
               autofocus>
        <button class="btn btn-primary btn-lg" type="submit" id="btn-verify" style="width:100%;">
          ✅ Verifikasi & Aktifkan OTP
        </button>
      </form>
    </div>

    <div class="info-box blue" style="font-size:.84rem;">
      <span class="icon">💡</span>
      <span>Kode tidak cocok? Pastikan <strong>waktu HP sinkron otomatis</strong>:
        iPhone → Settings → General → Date &amp; Time → Set Automatically ·
        Android → Settings → Date &amp; Time → Automatic
      </span>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
    (function(){
      try {
        new QRCode(document.getElementById('qr-div'), {
          text: <?= json_encode(TOTP::getQrDataUri($activeSecret, OTP_EMAIL)) ?>,
          width: 220, height: 220,
          colorDark:'#0d1117', colorLight:'#ffffff',
          correctLevel: QRCode.CorrectLevel.M
        });
      } catch(e) {
        document.getElementById('qr-div').style.display='none';
        document.getElementById('qr-fallback').style.display='block';
      }
    })();

    document.getElementById('secret-display').onclick = function() {
      var t = this;
      navigator.clipboard.writeText('<?= $activeSecret ?>').catch(function(){
        var ta=document.createElement('textarea'); ta.value='<?= $activeSecret ?>';
        document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
      });
      t.textContent = '✅ Tersalin!';
      setTimeout(function(){ t.textContent = '<?= $activeSecret ?>'; }, 1500);
    };

    // Auto-submit saat 6 digit diketik
    document.getElementById('otp-input').addEventListener('input', function(){
      this.value = this.value.replace(/[^0-9]/g, '');
      if(this.value.length === 6) {
        document.getElementById('btn-verify').textContent = '⏳ Memverifikasi...';
        document.getElementById('verify-form').submit();
      }
    });
    </script>

    <?php else: ?>
    <!-- ═══ GENERATE STEP ═══ -->
    <?php if ($isActive): ?>
    <div class="info-box green" style="margin-bottom:1rem;">
      <span class="icon">✅</span> OTP sudah aktif. Klik di bawah untuk buat QR baru jika perlu.
    </div>
    <?php endif; ?>
    <ul class="instr-list" style="margin-bottom:1.5rem;">
      <li><div class="instr-num">1</div><p>Instal <strong>Google Authenticator</strong> di ponsel.</p></li>
      <li><div class="instr-num">2</div><p>Klik <strong>Buat QR Code</strong> — sistem akan buat secret OTP unik.</p></li>
      <li><div class="instr-num">3</div><p>Scan QR dengan Authenticator lalu masukkan kode 6 digit.</p></li>
    </ul>
    <form method="POST">
      <input type="hidden" name="csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="action" value="generate">
      <button class="btn btn-primary btn-lg" type="submit">🔑 <?= $isActive?'Buat QR Code Baru':'Buat QR Code OTP' ?></button>
    </form>
    <?php endif; ?>
  </div>

  <!-- TAB C -->
  <div class="tab-panel" id="tab-reset">
    <p style="color:var(--mid);margin-bottom:1.5rem;">Reset akan menghapus secret OTP saat ini. Anda perlu setup ulang setelahnya.</p>
    <form method="POST" onsubmit="return confirm('Reset OTP sekarang?');">
      <input type="hidden" name="csrf"   value="<?= csrf() ?>">
      <input type="hidden" name="action" value="reset">
      <button class="btn btn-danger" type="submit">🔄 Reset OTP</button>
    </form>
  </div>
</div>
</div>

<script>
function switchTab(id) {
  document.querySelectorAll('#otp-tabs .tab').forEach(function(t){t.classList.remove('active');});
  document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active');});
  document.querySelector('[data-target='+id+']').classList.add('active');
  document.getElementById(id).classList.add('active');
}
</script>
