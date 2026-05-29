<?php // pages/signing-list.php
requireLogin();
$user = currentUser();

$rows = [];
try {
    $stmt = db()->prepare('SELECT * FROM signing_requests WHERE user_id=? ORDER BY created_at DESC');
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll();
} catch (\Throwable $e) {}
?>
<div style="max-width:960px;margin:0 auto;padding:2.5rem 2rem;">
<div class="page-header animate">
  <h1 class="page-title">📋 Riwayat Dokumen</h1>
  <p class="page-sub">Daftar semua dokumen yang pernah Anda proses di SignKu.</p>
</div>

<div class="card animate d1">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;">
    <div class="card-title mb-0">Semua Dokumen</div>
    <a href="?page=sign-document" class="btn btn-primary">+ Upload Baru</a>
  </div>
  <?php if ($rows): ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Nama File</th>
          <th>Hal.</th>
          <th>Status</th>
          <th>OTP</th>
          <th>Ditandatangani</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $i => $r): ?>
        <tr>
          <td style="color:var(--mid);font-size:.8rem;"><?= $i+1 ?></td>
          <td>
            <strong><?= h($r['filename_orig']) ?></strong>
            <div style="font-size:.73rem;color:var(--mid);">Upload: <?= h($r['created_at']) ?></div>
          </td>
          <td style="font-size:.85rem;"><?= $r['sign_page'] ?? '-' ?></td>
          <td>
            <?php if ($r['status'] === 'signed'): ?>
              <span class="badge badge-green">✅ Ditandatangani</span>
            <?php elseif ($r['status'] === 'failed'): ?>
              <span class="badge badge-red">❌ Gagal</span>
            <?php else: ?>
              <span class="badge badge-yellow">📤 Diunggah</span>
            <?php endif; ?>
          </td>
          <td><?= $r['otp_verified'] ? '<span class="badge badge-green">✅</span>' : '<span class="badge badge-yellow">–</span>' ?></td>
          <td style="font-size:.8rem;color:var(--mid);"><?= $r['signed_at'] ? h($r['signed_at']) : '–' ?></td>
          <td>
            <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
            <?php if ($r['status'] === 'signed'): ?>
              <a href="?page=download-signed&id=<?= $r['id'] ?>" class="btn btn-teal" style="padding:.3rem .7rem;font-size:.78rem;">⬇ Unduh</a>
              <a href="?page=verifikasi-dokumen&id=<?= $r['id'] ?>" class="btn btn-outline" style="padding:.3rem .7rem;font-size:.78rem;">🔍 Keaslian</a>
            <?php elseif ($r['status'] === 'uploaded'): ?>
              <a href="?page=sign-document&doc=<?= $r['id'] ?>" class="btn btn-primary" style="padding:.3rem .7rem;font-size:.78rem;">🖊 Tanda Tangani</a>
            <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <div style="text-align:center;padding:3rem;color:var(--mid);">
    <div style="font-size:3rem;">📂</div>
    <p style="margin-top:.5rem;">Belum ada dokumen. <a href="?page=sign-document">Upload dokumen pertama Anda →</a></p>
  </div>
  <?php endif; ?>
</div>
</div>
