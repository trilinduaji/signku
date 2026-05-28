<?php // pages/home.php ?>

<!-- HERO -->
<div class="hero animate">
  <div style="display:inline-block;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:99px;padding:.3rem 1rem;font-size:.78rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;margin-bottom:1.2rem;">Panduan Lengkap</div>
  <h1>Tanda Tangan Digital<br><em>Aman &amp; Sah Secara Hukum</em></h1>
  <p>Selamat datang, <strong>Tri Lindu Aji</strong>! Setup Digital ID, tanda tangani dokumen PDF, dan verifikasi keaslian tanda tangan.</p>
  <div style="display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap;">
    <a class="btn btn-primary btn-lg" href="?page=sign-document">🖊 Tanda Tangani Dokumen</a>
    <a class="btn btn-outline btn-lg" style="background:rgba(255,255,255,.15);color:#fff;border-color:rgba(255,255,255,.4);" href="?page=panduan">📖 Lihat Panduan</a>
  </div>
</div>

<div style="max-width:980px;margin:0 auto;padding:3rem 2rem;">

<!-- Quick start cards -->
<div style="font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:700;margin-bottom:.4rem;">Alur Penggunaan</div>
<div class="divider"></div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1rem;margin-bottom:3rem;">
  <?php
  $steps = [
    ['🔐','Setup OTP','setup-otp','Instal aplikasi OTP dan hubungkan akun Anda','blue'],
    ['📜','Sertifikat CA','sertifikat','Download & import CA ke Adobe/Foxit','green'],
    ['🆔','Request Digital ID','request-id','Ajukan permintaan Digital ID dari portal','yellow'],
    ['📥','Import Digital ID','import-id','Import file .p12 ke aplikasi PDF','orange'],
    ['🖊','Tanda Tangani','sign-document','Upload PDF dan tandatangani secara digital','blue'],
    ['✅','Verifikasi','verifikasi','Periksa keaslian tanda tangan di dokumen','green'],
    ['🎨','Tampilan TTD','tampilan-ttd','Buat tampilan visual tanda tangan Anda','yellow'],
  ];
  foreach ($steps as $i => [$icon, $title, $href, $desc, $color]):
  ?>
  <a href="?page=<?= $href ?>" style="background:#fff;border:1px solid var(--border);border-radius:14px;padding:1.2rem 1.4rem;display:flex;align-items:flex-start;gap:1rem;text-decoration:none;color:inherit;transition:box-shadow .2s,transform .2s,border-color .2s;"
     onmouseover="this.style.boxShadow='0 6px 24px rgba(26,79,214,.1)';this.style.borderColor='var(--blue)';this.style.transform='translateY(-2px)'"
     onmouseout="this.style.boxShadow='';this.style.borderColor='var(--border)';this.style.transform=''">
    <div style="width:42px;height:42px;border-radius:10px;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;"><?= $icon ?></div>
    <div>
      <div style="font-size:.75rem;font-weight:600;color:var(--mid);margin-bottom:.2rem;">LANGKAH <?= $i+1 ?></div>
      <div style="font-weight:600;font-size:.95rem;margin-bottom:.2rem;"><?= $title ?></div>
      <div style="font-size:.8rem;color:var(--mid);line-height:1.4;"><?= $desc ?></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Recent activity -->
<div class="card animate d1">
  <div class="card-title">📋 Aktivitas Terbaru</div>
  <?php
  $rows = [];
  try {
    $logs = db()->prepare('SELECT * FROM activity_log WHERE user_id=? ORDER BY created_at DESC LIMIT 6');
    $logs->execute([MOCK_USER_ID]);
    $rows = $logs->fetchAll();
  } catch (\Throwable $e) {}
  ?>
  <?php if ($rows): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Aksi</th><th>Detail</th><th>Waktu</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong><?= h($r['action']) ?></strong></td>
          <td><?= h($r['detail'] ?? '-') ?></td>
          <td style="font-size:.8rem;color:var(--mid);"><?= h($r['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <p style="color:var(--mid);font-size:.9rem;">Belum ada aktivitas.</p>
  <?php endif; ?>
</div>

</div>
