<?php // pages/request-id.php
requireLogin();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { flash('error','Token tidak valid.'); header('Location: ?page=request-id'); exit; }
    $role    = trim($_POST['role'] ?? '');
    $pass    = $_POST['passphrase'] ?? '';
    $confirm = $_POST['confirm_passphrase'] ?? '';
    if (strlen($pass) < 8) { flash('error','Passphrase minimal 8 karakter.'); header('Location: ?page=request-id'); exit; }
    if ($pass !== $confirm) { flash('error','Passphrase tidak cocok.'); header('Location: ?page=request-id'); exit; }

    // Check existing active request
    try {
        $chk = db()->prepare('SELECT id FROM digital_id_requests WHERE user_id=? AND status IN ("pending","approved")');
        $chk->execute([$user['id']]);
        if ($chk->fetch()) { flash('error','Anda sudah memiliki permintaan Digital ID yang aktif.'); header('Location: ?page=request-id'); exit; }
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        db()->prepare('INSERT INTO digital_id_requests (user_id,role,passphrase) VALUES (?,?,?)')
            ->execute([$user['id'], $role, $hash]);
    } catch (\Throwable $e) {
        flash('error','Database tidak tersedia. Fitur ini membutuhkan koneksi DB.');
        header('Location: ?page=request-id'); exit;
    }
    logActivity('request_digital_id', 'Request untuk role: ' . $role);
    flash('success', 'Permintaan Digital ID berhasil dikirim. Admin akan memproses dalam 1–3 hari kerja.');
    header('Location: ?page=request-id'); exit;
}

// Get requests
$rows = [];
try {
    $requests = db()->prepare('SELECT * FROM digital_id_requests WHERE user_id=? ORDER BY created_at DESC');
    $requests->execute([$user['id']]);
    $rows = $requests->fetchAll();
} catch (\Throwable $e) {}
?>
<div style="max-width:860px;margin:0 auto;padding:2.5rem 2rem;">
<div class="page-header animate">
  <span class="step-badge">LANGKAH 03</span>
  <h1 class="page-title">Request Digital ID</h1>
  <p class="page-sub">Ajukan permintaan Digital ID untuk mulai menandatangani dokumen secara digital.</p>
</div>

<!-- Request form -->
<div class="card animate d1">
  <div class="card-title">📝 Form Permohonan</div>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Nama Lengkap</label>
        <input type="text" value="<?= h($user['name']) ?>" disabled>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" value="<?= h($user['email']) ?>" disabled>
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
        <input type="password" name="passphrase" required minlength="8" placeholder="Buat passphrase kuat">
      </div>
      <div class="form-group">
        <label>Konfirmasi Passphrase</label>
        <input type="password" name="confirm_passphrase" required placeholder="Ulangi passphrase">
      </div>
    </div>
    <div class="info-box warn" style="margin-bottom:1rem;">
      <span class="icon">⚠️</span> <strong>Ingat passphrase Anda!</strong> Diperlukan setiap kali menandatangani dokumen dan tidak dapat dipulihkan.
    </div>
    <button class="btn btn-primary" type="submit">📤 Kirim Permohonan</button>
  </form>
</div>

<!-- Status list -->
<div class="card animate d2">
  <div class="card-title">📋 Status Permohonan Digital ID</div>
  <?php if ($rows): ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Role</th>
          <th>Status</th>
          <th>Siap</th>
          <th>Terkirim</th>
          <th>Berlaku s/d</th>
          <th>Tanggal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong><?= h($r['role']) ?></strong></td>
          <td>
            <?php if ($r['status'] === 'approved'): ?>
              <span class="badge badge-green">✅ Disetujui</span>
            <?php elseif ($r['status'] === 'rejected'): ?>
              <span class="badge badge-red">❌ Ditolak</span>
            <?php else: ?>
              <span class="badge badge-yellow">⏳ Menunggu</span>
            <?php endif; ?>
          </td>
          <td><?= $r['is_ready'] ? '<span class="badge badge-green">✅</span>' : '<span class="badge badge-yellow">-</span>' ?></td>
          <td><?= $r['is_sent'] ? '<span class="badge badge-green">✅</span>' : '<span class="badge badge-yellow">-</span>' ?></td>
          <td><?= $r['valid_until'] ? h($r['valid_until']) : '-' ?></td>
          <td style="font-size:.8rem;color:var(--mid);"><?= h($r['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <span class="validity mt-2">✔ Digital ID valid selama 2 tahun sejak diterbitkan</span>
  <?php else: ?>
  <p style="color:var(--mid);font-size:.9rem;">Belum ada permohonan. Gunakan form di atas untuk mengajukan.</p>
  <?php endif; ?>
</div>
</div>
