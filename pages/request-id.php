<?php // pages/request-id.php — Digital ID langsung terbuat tanpa verifikasi/permohonan
requireLogin();
$user = currentUser();

// Cek apakah sudah punya Digital ID
$existingId = null;
try {
    $chk = db()->prepare('SELECT * FROM digital_id_requests WHERE user_id=? AND status="approved" ORDER BY created_at DESC LIMIT 1');
    $chk->execute([$user['id']]);
    $existingId = $chk->fetch() ?: null;
} catch (\Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { flash('error','Token tidak valid.'); header('Location: ?page=request-id'); exit; }
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $role = trim($_POST['role'] ?? '');
        $pass = $_POST['passphrase'] ?? '';
        $confirm = $_POST['confirm_passphrase'] ?? '';
        if (!$role) { flash('error','Pilih role terlebih dahulu.'); header('Location: ?page=request-id'); exit; }
        if (strlen($pass) < 8) { flash('error','Passphrase minimal 8 karakter.'); header('Location: ?page=request-id'); exit; }
        if ($pass !== $confirm) { flash('error','Passphrase tidak cocok.'); header('Location: ?page=request-id'); exit; }

        try {
            // Hapus yang lama jika ada
            db()->prepare('DELETE FROM digital_id_requests WHERE user_id=?')->execute([$user['id']]);
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $validUntil = date('Y-m-d', strtotime('+2 years'));
            // Langsung approved tanpa permohonan
            db()->prepare('INSERT INTO digital_id_requests (user_id,role,passphrase,status,is_ready,is_sent,valid_until) VALUES (?,?,?,?,?,?,?)')
                ->execute([$user['id'], $role, $hash, 'approved', 1, 1, $validUntil]);
            // Generate nomor Digital ID unik
            $idNum = 'DID-' . strtoupper(substr(md5($user['id'] . $role . time()), 0, 12));
            $_SESSION['digital_id_number'] = $idNum;
            $_SESSION['digital_id_role'] = $role;
        } catch (\Throwable $e) {
            // Mode tanpa DB: simpan ke session saja
            $idNum = 'DID-' . strtoupper(substr(md5($user['id'] . time()), 0, 12));
            $_SESSION['digital_id_number'] = $idNum;
            $_SESSION['digital_id_role'] = $role;
        }
        logActivity('create_digital_id', 'Digital ID dibuat untuk role: ' . $role);
        flash('success', '✅ Digital ID berhasil dibuat! ID Anda: ' . ($_SESSION['digital_id_number'] ?? ''));
        header('Location: ?page=request-id'); exit;
    }

    if ($action === 'revoke') {
        try { db()->prepare('DELETE FROM digital_id_requests WHERE user_id=?')->execute([$user['id']]); } catch (\Throwable $e) {}
        unset($_SESSION['digital_id_number'], $_SESSION['digital_id_role']);
        flash('success', 'Digital ID berhasil dihapus.');
        header('Location: ?page=request-id'); exit;
    }
}

// Cek session juga
$sessionId = $_SESSION['digital_id_number'] ?? null;
$sessionRole = $_SESSION['digital_id_role'] ?? null;
$hasId = $existingId || $sessionId;
?>
<div style="max-width:860px;margin:0 auto;padding:2.5rem 2rem;">
<div class="page-header animate">
  <span class="step-badge">LANGKAH 02</span>
  <h1 class="page-title">Digital ID</h1>
  <p class="page-sub">Buat Digital ID Anda secara instan — langsung aktif, tidak perlu menunggu persetujuan.</p>
</div>

<?php if ($hasId): ?>
<!-- Sudah punya Digital ID -->
<?php
  $role = $existingId['role'] ?? $sessionRole ?? 'Unknown';
  $validUntil = $existingId['valid_until'] ?? date('Y-m-d', strtotime('+2 years'));
  $idNum = $_SESSION['digital_id_number'] ?? ('DID-' . strtoupper(substr(md5($user['id']), 0, 12)));
?>
<div class="card animate d1" style="border: 2px solid var(--teal); background: linear-gradient(135deg, #f0fdfa, #fff);">
  <div style="display:flex; align-items:center; gap:1.2rem; margin-bottom:1.5rem;">
    <div style="width:64px;height:64px;border-radius:50%;background:var(--teal);display:flex;align-items:center;justify-content:center;font-size:2rem;flex-shrink:0;">🪪</div>
    <div>
      <div style="font-family:'Syne',sans-serif;font-weight:800;font-size:1.2rem;"><?= h($user['name']) ?></div>
      <div style="font-size:.85rem;color:var(--mid);"><?= h($user['email']) ?></div>
    </div>
    <div style="margin-left:auto;"><span class="badge badge-green">✅ AKTIF</span></div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;background:#f8fafc;border-radius:10px;padding:1rem;margin-bottom:1.2rem;">
    <div>
      <div style="font-size:.72rem;color:var(--mid);font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Nomor ID</div>
      <div style="font-family:'JetBrains Mono',monospace;font-size:.85rem;font-weight:700;color:var(--blue);margin-top:.2rem;"><?= h($idNum) ?></div>
    </div>
    <div>
      <div style="font-size:.72rem;color:var(--mid);font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Role</div>
      <div style="font-size:.88rem;font-weight:600;margin-top:.2rem;"><?= h($role) ?></div>
    </div>
    <div>
      <div style="font-size:.72rem;color:var(--mid);font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Berlaku Hingga</div>
      <div style="font-size:.88rem;font-weight:600;margin-top:.2rem;color:var(--green);"><?= h($validUntil) ?></div>
    </div>
  </div>
  <div style="display:flex;gap:.7rem;flex-wrap:wrap;">
    <a href="?page=sign-document" class="btn btn-primary">🖊 Mulai Tanda Tangan</a>
    <form method="POST" onsubmit="return confirm('Hapus Digital ID ini?');" style="display:inline;">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="revoke">
      <button type="submit" class="btn btn-outline" style="border-color:var(--warn);color:var(--warn);">🗑 Hapus & Buat Ulang</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- Belum punya Digital ID — form buat langsung -->
<div class="info-box green animate" style="margin-bottom:1.5rem;">
  <span class="icon">⚡</span>
  <span><strong>Langsung Aktif!</strong> Digital ID Anda akan dibuat seketika — tidak perlu menunggu persetujuan admin.</span>
</div>

<div class="card animate d1">
  <div class="card-title">🪪 Buat Digital ID</div>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <input type="hidden" name="action" value="create">
    <div class="form-row">
      <div class="form-group">
        <label>Nama Lengkap</label>
        <input type="text" value="<?= h($user['name']) ?>" disabled style="background:#f8fafc;">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" value="<?= h($user['email']) ?>" disabled style="background:#f8fafc;">
      </div>
    </div>
    <div class="form-group">
      <label>Role / Jabatan</label>
      <select name="role" required>
        <option value="">-- Pilih Role --</option>
        <option value="Mahasiswa">Mahasiswa</option>
        <option value="Dosen">Dosen</option>
        <option value="Staff Akademik">Staff Akademik</option>
        <option value="Kepala Program Studi">Kepala Program Studi</option>
        <option value="Dekan">Dekan</option>
        <option value="Direktur">Direktur</option>
        <option value="Rektor">Rektor</option>
      </select>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Passphrase <small style="color:var(--mid);font-weight:400;">(min. 8 karakter)</small></label>
        <input type="password" name="passphrase" required minlength="8" placeholder="Buat passphrase kuat" id="pass1"
               oninput="checkPass()">
      </div>
      <div class="form-group">
        <label>Konfirmasi Passphrase</label>
        <input type="password" name="confirm_passphrase" required placeholder="Ulangi passphrase" id="pass2"
               oninput="checkPass()">
      </div>
    </div>
    <div id="pass-match" style="font-size:.82rem;margin-bottom:.8rem;display:none;"></div>
    <div class="info-box warn" style="margin-bottom:1.2rem;">
      <span class="icon">⚠️</span> <strong>Ingat passphrase Anda!</strong> Diperlukan setiap kali menandatangani dokumen.
    </div>
    <button class="btn btn-primary btn-lg" type="submit" id="btn-create">⚡ Buat Digital ID Sekarang</button>
  </form>
</div>
<?php endif; ?>

</div>
<script>
function checkPass() {
  var p1 = document.getElementById('pass1').value;
  var p2 = document.getElementById('pass2').value;
  var el = document.getElementById('pass-match');
  if (!p2) { el.style.display='none'; return; }
  el.style.display = 'block';
  if (p1 === p2) {
    el.innerHTML = '<span style="color:var(--green);">✅ Passphrase cocok</span>';
  } else {
    el.innerHTML = '<span style="color:var(--warn);">❌ Passphrase tidak cocok</span>';
  }
}
</script>
