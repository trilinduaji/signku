<?php // pages/import-id.php
requireLogin();
?>
<div style="max-width:860px;margin:0 auto;padding:2.5rem 2rem;">
<div class="page-header animate">
  <span class="step-badge">LANGKAH 04</span>
  <h1 class="page-title">Import Digital ID</h1>
  <p class="page-sub">Setelah menerima file <code>.p12</code> via email, import ke Adobe atau Foxit Reader.</p>
</div>
<div class="card animate d1">
  <div class="tabs">
    <button class="tab active" data-target="imp-acrobat">A. Adobe Acrobat Reader</button>
    <button class="tab" data-target="imp-foxit">B. Foxit Reader</button>
  </div>
  <div class="tab-panel active" id="imp-acrobat">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Unduh file <code>nama.role.p12</code> dari email Certificate Digital.</p></li>
      <li><div class="instr-num">2</div><p>Acrobat Reader → <strong>Preferences</strong> → <strong>Signatures</strong> → <em>Identities &amp; Trusted Certificates</em> → <strong>More…</strong></p></li>
      <li><div class="instr-num">3</div><p>Klik <strong>Digital IDs</strong> → ikon <strong>+</strong> (tambah).</p></li>
      <li><div class="instr-num">4</div><p>Pilih <em>"My existing digital ID from: A file"</em> → <strong>Next</strong>.</p></li>
      <li><div class="instr-num">5</div><p>Klik <strong>Browse…</strong> → pilih file <code>.p12</code> → masukkan <strong>Passphrase</strong> → <strong>Next</strong>.</p></li>
      <li><div class="instr-num">6</div><p>Pastikan Digital ID dari <em>Institusi Kami</em> muncul → <strong>Finish</strong>.</p></li>
    </ul>
  </div>
  <div class="tab-panel" id="imp-foxit">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Foxit Reader → tab <strong>Protect</strong> → <strong>Digital IDs</strong>.</p></li>
      <li><div class="instr-num">2</div><p>Klik <strong>Add ID</strong> → pilih <em>"My existing digital ID from a file"</em> → <strong>Next</strong>.</p></li>
      <li><div class="instr-num">3</div><p>Klik <strong>Browse…</strong> → pilih file <code>.p12</code> → masukkan <strong>Passphrase</strong> → <strong>Next</strong>.</p></li>
      <li><div class="instr-num">4</div><p>Klik <strong>Finish</strong>. Pastikan <em>Certificate Issuer</em> adalah Institusi Kami.</p></li>
    </ul>
  </div>
</div>
</div>
