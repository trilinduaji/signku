<?php // pages/sertifikat.php
requireLogin();
?>
<div style="max-width:860px;margin:0 auto;padding:2.5rem 2rem;">
<div class="page-header animate">
  <span class="step-badge">LANGKAH 02</span>
  <h1 class="page-title">Download &amp; Import Sertifikat CA</h1>
  <p class="page-sub">Instal sertifikat Certificate Authority agar Adobe dan Foxit mempercayai tanda tangan digital institusi.</p>
</div>

<!-- Download CA -->
<div class="card animate d1">
  <div class="card-title">📥 Unduh Sertifikat CA</div>
  <ul class="instr-list" style="margin-bottom:1.2rem;">
    <li><div class="instr-num">1</div><p>Klik tombol di bawah untuk mengunduh sertifikat CA institusi Anda (<code>InstitusiKami.crt</code>).</p></li>
  </ul>
  <a href="#" class="btn btn-primary" onclick="alert('Fitur unduh CA akan tersedia setelah Digital ID Anda disetujui admin.');return false;">
    ⬇ Download Sertifikat CA
  </a>
  <div class="info-box red mt-2"><span class="icon">🚫</span> Hanya unduh sertifikat CA dari portal resmi SignKu.</div>
</div>

<!-- Import tabs -->
<div class="card animate d2">
  <div class="card-title">🔧 Import Sertifikat CA ke Aplikasi</div>
  <div class="tabs">
    <button class="tab active" data-target="ca-acrobat">A. Adobe Acrobat Reader</button>
    <button class="tab" data-target="ca-foxit">B. Foxit Reader</button>
  </div>
  <div class="tab-panel active" id="ca-acrobat">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Buka Acrobat Reader → <strong>Preferences</strong> (macOS: Acrobat Reader menu; Windows: Edit menu).</p></li>
      <li><div class="instr-num">2</div><p>Pilih kategori <strong>Signatures</strong> → bagian <em>Identities &amp; Trusted Certificates</em> → klik <strong>More…</strong></p></li>
      <li><div class="instr-num">3</div><p>Klik <strong>Trusted Certificates</strong> → klik <strong>Import</strong>.</p></li>
      <li><div class="instr-num">4</div><p>Klik <strong>Browse</strong> → pilih file <code>InstitusiKami.crt</code> → <strong>Import</strong>.</p></li>
      <li><div class="instr-num">5</div><p>Klik nama CA → di bagian Certificates klik baris CA → klik <strong>Trust…</strong></p></li>
      <li><div class="instr-num">6</div><p>Centang <strong>"Use this certificate as a trusted root"</strong> → <strong>OK</strong>.</p></li>
      <li><div class="instr-num">7</div><p>Klik <strong>Import</strong> → pastikan muncul pesan <em>"Import Complete"</em>.</p></li>
    </ul>
  </div>
  <div class="tab-panel" id="ca-foxit">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Ubah ekstensi: <code>InstitusiKami.crt</code> → <code>InstitusiKami.cer</code>.</p></li>
      <li><div class="instr-num">2</div><p>Foxit Reader → tab <strong>Protect</strong> → <strong>Trusted Certificates</strong> → <strong>Add</strong>.</p></li>
      <li><div class="instr-num">3</div><p>Pilih file <code>.cer</code> → <strong>OK</strong>.</p></li>
      <li><div class="instr-num">4</div><p>Pilih <em>Institusi Kami</em> → <strong>Edit</strong> → centang <em>"Use this certificate as a trusted root"</em> → <strong>OK</strong>.</p></li>
      <li><div class="instr-num">5</div><p>Pastikan kolom Trust berubah menjadi <em>Trusted Root</em> → <strong>Close</strong>.</p></li>
    </ul>
  </div>
</div>
</div>
