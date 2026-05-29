<?php // pages/verifikasi.php
requireLogin();
?>
<div style="max-width:860px;margin:0 auto;padding:2.5rem 2rem;">
<div class="page-header animate">
  <span class="step-badge">LANGKAH 06</span>
  <h1 class="page-title">Verifikasi Keaslian Tanda Tangan</h1>
  <p class="page-sub">Periksa validitas tanda tangan digital pada dokumen PDF.</p>
</div>
<div class="card animate d1">
  <ul class="instr-list">
    <li><div class="instr-num">1</div><p>Buka file PDF yang akan diperiksa di Acrobat Reader atau Foxit Reader.</p></li>
    <li><div class="instr-num">2</div><p>Klik <strong>Signature Panel</strong> (Acrobat) atau menu <strong>Protect → Validate</strong> (Foxit).</p></li>
    <li><div class="instr-num">3</div><p>Panel di sisi kiri menampilkan: <strong>Signed by</strong> (nama penandatangan) dan <strong>Signature Details</strong>.</p></li>
  </ul>
  <div class="grid-2 mt-3">
    <div style="background:#f8fafc;border:1px solid var(--border);border-radius:12px;padding:1.3rem;">
      <h4 style="font-size:.9rem;font-weight:600;margin-bottom:.6rem;">🔵 Acrobat Reader</h4>
      <p style="font-size:.85rem;color:var(--mid);line-height:1.55;"><em>"Signed and all signatures are valid"</em> — tanda tangan sah dan dokumen tidak dimodifikasi setelah ditandatangani.</p>
    </div>
    <div style="background:#f8fafc;border:1px solid var(--border);border-radius:12px;padding:1.3rem;">
      <h4 style="font-size:.9rem;font-weight:600;margin-bottom:.6rem;">🟠 Foxit Reader</h4>
      <p style="font-size:.85rem;color:var(--mid);line-height:1.55;"><em>"Signature is valid"</em> di panel Digital Signatures — tanda tangan telah terverifikasi.</p>
    </div>
  </div>
</div>
</div>
