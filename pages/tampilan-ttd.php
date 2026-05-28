<?php // pages/tampilan-ttd.php
requireLogin();
$user = currentUser();

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { flash('error','Token tidak valid.'); header('Location: ?page=tampilan-ttd'); exit; }
    $name      = trim($_POST['app_name'] ?? '');
    $showName  = isset($_POST['show_name'])  ? 1 : 0;
    $showDate  = isset($_POST['show_date'])  ? 1 : 0;
    $showLogo  = isset($_POST['show_logo'])  ? 1 : 0;
    $isDefault = isset($_POST['is_default']) ? 1 : 0;

    if (strlen($name) < 2) { flash('error','Nama tampilan minimal 2 karakter.'); header('Location: ?page=tampilan-ttd'); exit; }

    // FIX: pastikan folder assets/img ada sebelum upload
    $imgDir = ASSETS_PATH . 'img/';
    if (!is_dir($imgDir)) {
        mkdir($imgDir, 0755, true);
    }

    $imgPath = null;
    if (!empty($_FILES['sig_image']['name']) && $_FILES['sig_image']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['sig_image'];
        // FIX: validasi MIME dengan finfo, bukan hanya $_FILES['type']
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($f['tmp_name']);
        $allowed  = ['image/png', 'image/jpeg'];
        if (!in_array($mimeType, $allowed)) {
            flash('error','Format gambar harus PNG atau JPG.');
            header('Location: ?page=tampilan-ttd'); exit;
        }
        if ($f['size'] > 5 * 1024 * 1024) {
            flash('error','Ukuran gambar maksimal 5 MB.');
            header('Location: ?page=tampilan-ttd'); exit;
        }
        $ext   = $mimeType === 'image/png' ? 'png' : 'jpg';
        $fname = uniqid('sig_') . '.' . $ext;
        $dest  = $imgDir . $fname;
        if (move_uploaded_file($f['tmp_name'], $dest)) {
            $imgPath = $fname;
        }
    }

    try {
        if ($isDefault) db()->prepare('UPDATE signature_appearances SET is_default=0 WHERE user_id=?')->execute([$user['id']]);
        db()->prepare('INSERT INTO signature_appearances (user_id,name,image_path,show_name,show_date,show_logo,is_default) VALUES (?,?,?,?,?,?,?)')
            ->execute([$user['id'], $name, $imgPath, $showName, $showDate, $showLogo, $isDefault]);
    } catch (\Throwable $e) {}
    logActivity('create_appearance', 'Tampilan: ' . $name);
    flash('success', 'Tampilan tanda tangan berhasil disimpan!');
    header('Location: ?page=tampilan-ttd'); exit;
}

$appearances = [];
try {
    $apps = db()->prepare('SELECT * FROM signature_appearances WHERE user_id=? ORDER BY created_at DESC');
    $apps->execute([$user['id']]);
    $appearances = $apps->fetchAll();
} catch (\Throwable $e) {}
?>
<div style="max-width:860px;margin:0 auto;padding:2.5rem 2rem;">
<div class="page-header animate">
  <span class="step-badge">LANGKAH 07</span>
  <h1 class="page-title">Tampilan Tanda Tangan Digital</h1>
  <p class="page-sub">Buat dan kelola tampilan visual tanda tangan Anda.</p>
</div>

<div class="card animate d1">
  <div class="card-title">🎨 Buat Tampilan Baru</div>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Nama Tampilan</label>
        <input type="text" name="app_name" required placeholder="Contoh: TTD Resmi, TTD Singkat">
      </div>
      <div class="form-group">
        <label>Gambar Tanda Tangan <small style="color:var(--mid);">(PNG/JPG, maks 5MB, opsional)</small></label>
        <input type="file" name="sig_image" accept="image/png,image/jpeg">
      </div>
    </div>
    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:1.2rem;">
      <label style="display:flex;align-items:center;gap:.4rem;font-size:.9rem;cursor:pointer;">
        <input type="checkbox" name="show_name" checked> Tampilkan nama
      </label>
      <label style="display:flex;align-items:center;gap:.4rem;font-size:.9rem;cursor:pointer;">
        <input type="checkbox" name="show_date" checked> Tampilkan tanggal
      </label>
      <label style="display:flex;align-items:center;gap:.4rem;font-size:.9rem;cursor:pointer;">
        <input type="checkbox" name="show_logo"> Tampilkan logo
      </label>
      <label style="display:flex;align-items:center;gap:.4rem;font-size:.9rem;cursor:pointer;">
        <input type="checkbox" name="is_default"> Jadikan default
      </label>
    </div>
    <button class="btn btn-primary" type="submit">💾 Simpan Tampilan</button>
  </form>

  <div class="info-box green mt-2"><span class="icon">✅</span> Format gambar didukung: <strong>PNG, JPG</strong> untuk SignKu Web; <strong>PDF</strong> untuk Adobe; <strong>JPG, PNG, PDF</strong> untuk Foxit.</div>
</div>

<!-- Existing appearances -->
<?php if ($appearances): ?>
<div class="card animate d2">
  <div class="card-title">📋 Tampilan Tersimpan</div>
  <div class="grid-2">
    <?php foreach ($appearances as $a): ?>
    <div style="border:1px solid var(--border);border-radius:10px;padding:1rem;background:#fafafa;">
      <?php
        // FIX: cek file menggunakan ASSETS_PATH yang sudah benar
        $imgFile = ASSETS_PATH . 'img/' . ($a['image_path'] ?? '');
      ?>
      <?php if ($a['image_path'] && file_exists($imgFile)): ?>
        <img src="<?= BASE_URL ?>/assets/img/<?= h($a['image_path']) ?>" style="width:100%;max-height:80px;object-fit:contain;border-radius:6px;background:#fff;margin-bottom:.6rem;">
      <?php else: ?>
        <div style="height:60px;background:#e5e7eb;border-radius:6px;display:flex;align-items:center;justify-content:center;color:var(--mid);font-size:.8rem;margin-bottom:.6rem;">Tanpa Gambar</div>
      <?php endif; ?>
      <div style="font-weight:600;font-size:.9rem;"><?= h($a['name']) ?></div>
      <div style="font-size:.75rem;color:var(--mid);margin-top:.3rem;">
        <?= $a['show_name'] ? '✔ Nama ' : '' ?>
        <?= $a['show_date'] ? '✔ Tanggal ' : '' ?>
        <?= $a['is_default'] ? '<span class="badge badge-blue" style="font-size:.7rem;">Default</span>' : '' ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
</div>
