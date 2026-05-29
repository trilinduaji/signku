<?php // pages/otp-debug.php
requireLogin();
$user   = currentUser();
$secret = $_SESSION['mock_otp_secret']
    ?? (file_exists(OTP_SECRET_FILE) ? trim(file_get_contents(OTP_SECRET_FILE)) : null)
    ?? $user['otp_secret']
    ?? '';
$now    = time();
$uri    = $secret ? TOTP::getUri($secret, $user['email']) : '';
?>
<div style="max-width:700px;margin:2rem auto;padding:2rem;font-family:monospace;">

<h2 style="font-family:'Syne',sans-serif;">🔍 OTP Debug Panel</h2>

<div style="background:#0d1117;color:#e6edf3;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem;">
  <div style="color:#7ee787;margin-bottom:.5rem;font-size:.85rem;">▶ SECRET AKTIF DI SERVER</div>
  <div id="dbg-secret" style="font-size:1.3rem;font-weight:700;letter-spacing:.15em;color:#79c0ff;"><?= h($secret) ?></div>
  <div style="color:#8b949e;font-size:.78rem;margin-top:.4rem;">
    Dari: <?= !empty($_SESSION['mock_otp_secret']) ? 'Session' : (file_exists(OTP_SECRET_FILE) ? 'File persisten' : 'Mock default') ?>
  </div>
</div>

<div style="background:#0d1117;color:#e6edf3;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem;">
  <div style="color:#7ee787;margin-bottom:.5rem;font-size:.85rem;">▶ OTP URI (isi QR Code)</div>
  <div style="font-size:.75rem;word-break:break-all;color:#ffa657;"><?= h($uri) ?></div>
</div>

<div style="background:#0d1117;color:#e6edf3;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem;">
  <div style="color:#7ee787;margin-bottom:.8rem;font-size:.85rem;">▶ KODE YANG SEHARUSNYA KELUAR (live, refresh otomatis)</div>
  <?php for ($i = -1; $i <= 1; $i++):
    $ts   = $now + $i * 30;
    $code = TOTP::getCode($secret, $ts);
    $curr = $i === 0;
  ?>
  <div style="display:flex;align-items:center;gap:1rem;margin-bottom:.5rem;<?= $curr ? 'background:#1f3a1f;border-radius:6px;padding:.4rem .6rem;' : 'opacity:.6;padding:.4rem .6rem;' ?>">
    <span style="color:#8b949e;width:80px;"><?= $i===0?'SEKARANG':($i<0?'LALU '+abs($i*30).'s':'NANTI '.($i*30).'s') ?></span>
    <span id="code-<?= $i ?>" style="font-size:<?= $curr?'2rem':'1.2rem' ?>;font-weight:<?= $curr?'800':'400' ?>;letter-spacing:.3em;color:<?= $curr?'#7ee787':'#8b949e' ?>;"><?= $code ?></span>
    <?php if ($curr): ?>
    <span style="color:#8b949e;font-size:.8rem;">berlaku <span id="dbg-sec">?</span>s lagi</span>
    <?php endif; ?>
  </div>
  <?php endfor; ?>
</div>

<div style="background:#1a1f2e;border:1px solid #30363d;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem;">
  <div style="color:#ffa657;margin-bottom:.8rem;font-weight:700;">⚠️ Cara memastikan Google Authenticator COCOK:</div>
  <ol style="color:#e6edf3;line-height:1.9;font-family:sans-serif;font-size:.9rem;">
    <li>Buka <strong>Google Authenticator</strong> di HP</li>
    <li>Lihat kode untuk entry <strong>SignKu Digital</strong></li>
    <li>Bandingkan dengan kode <span style="color:#7ee787;font-weight:700;">SEKARANG</span> di atas</li>
    <li>Jika <strong>SAMA</strong> → sistem bekerja normal ✅</li>
    <li>Jika <strong>BEDA</strong> → klik tombol di bawah untuk reset dan scan ulang QR</li>
  </ol>
</div>

<div style="display:flex;gap:.8rem;flex-wrap:wrap;">
  <a href="?page=setup-otp" class="btn btn-primary">← Kembali ke Setup OTP</a>
  <button class="btn btn-outline" onclick="location.reload()">🔄 Refresh Kode</button>
  <form method="POST" action="?page=setup-otp" style="display:inline;">
    <input type="hidden" name="csrf"   value="<?= csrf() ?>">
    <input type="hidden" name="action" value="reset">
    <button type="submit" class="btn btn-danger" onclick="return confirm('Reset OTP dan scan QR baru?')">🔄 Reset & Scan Ulang</button>
  </form>
</div>

<script>
// Auto-refresh kode setiap detik via API
function refreshDebug() {
  fetch('?page=api-debug-otp')
    .then(r => r.json())
    .then(d => {
      if (d.codes) {
        d.codes.forEach(function(c) {
          var el = document.getElementById('code-' + c.offset);
          if (el) el.textContent = c.code;
        });
        var sec = document.getElementById('dbg-sec');
        if (sec) sec.textContent = d.seconds_left;
      }
    }).catch(function(){});
}
setInterval(refreshDebug, 5000);
setInterval(function(){
  var s = 30-(Math.floor(Date.now()/1000)%30);
  var el = document.getElementById('dbg-sec');
  if(el) el.textContent = s;
}, 1000);
</script>
</div>
