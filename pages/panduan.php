<?php // pages/panduan.php ?>
<div style="max-width:900px;margin:0 auto;padding:2.5rem 2rem;">

<div class="page-header animate">
  <h1 class="page-title">📖 Panduan Lengkap SignKu</h1>
  <p class="page-sub">Referensi lengkap untuk setup, penggunaan, dan verifikasi tanda tangan digital berbasis PKI.</p>
</div>

<!-- STEP 1: OTP -->
<div class="card animate d1">
  <span class="step-badge">LANGKAH 01</span>
  <h2 style="font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:700;margin:0.5rem 0 1.2rem;">Setup &amp; Reset OTP</h2>
  <div class="tabs">
    <button class="tab active" data-target="p-otp-a">A. Instalasi</button>
    <button class="tab" data-target="p-otp-b">B. Setup OTP</button>
    <button class="tab" data-target="p-otp-c">C. Reset OTP</button>
  </div>
  <div class="tab-panel active" id="p-otp-a">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Untuk fitur <strong>SignKu Digital Sign</strong>, instal aplikasi OTP di ponsel: <strong>Google Authenticator</strong>, LastPass Authenticator, atau FreeOTP.</p></li>
      <li><div class="instr-num">2</div><p><strong>iPhone:</strong> App Store → cari <em>"Google Authenticator"</em> → <strong>Get</strong> → <strong>Open</strong>.</p></li>
      <li><div class="instr-num">3</div><p><strong>Android:</strong> Play Store → cari <em>"Google Authenticator"</em> → <strong>Install</strong> → <strong>Open</strong>.</p></li>
    </ul>
    <div class="info-box blue mt-2"><span class="icon">ℹ️</span> Aplikasi OTP lain seperti Authy atau FreeOTP juga kompatibel.</div>
  </div>
  <div class="tab-panel" id="p-otp-b">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Login ke portal SignKu → klik nama pengguna → pilih <strong>Setup OTP</strong>.</p></li>
      <li><div class="instr-num">2</div><p>Klik <strong>Generate QR Code</strong>. Sistem menghasilkan kode rahasia unik untuk akun Anda.</p></li>
      <li><div class="instr-num">3</div><p>Scan <strong>QR Code</strong> menggunakan Google Authenticator di ponsel.</p></li>
      <li><div class="instr-num">4</div><p>Masukkan kode 6-digit dari aplikasi → klik <strong>Verifikasi &amp; Simpan</strong>.</p></li>
    </ul>
    <div class="info-box warn mt-2"><span class="icon">⚠️</span> Kode OTP berubah setiap <strong>30 detik</strong>. Sinkronkan waktu ponsel Anda.</div>
  </div>
  <div class="tab-panel" id="p-otp-c">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Klik nama pengguna → pilih <strong>Request Reset OTP</strong>.</p></li>
      <li><div class="instr-num">2</div><p>Klik <strong>Reset OTP</strong>. Link dikirim ke email terdaftar.</p></li>
      <li><div class="instr-num">3</div><p>Klik tautan di email untuk mereset OTP.</p></li>
      <li><div class="instr-num">4</div><p>Setelah reset, ulangi <em>Setup OTP</em> dari awal.</p></li>
    </ul>
  </div>
</div>

<!-- STEP 2: SERTIFIKAT CA -->
<div class="card animate d2">
  <span class="step-badge">LANGKAH 02</span>
  <h2 style="font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:700;margin:0.5rem 0 1.2rem;">Download &amp; Import Sertifikat CA</h2>
  <p style="font-size:.9rem;margin-bottom:1rem;"><strong>Unduh:</strong> Klik nama pengguna → <strong>Download CA</strong>. File <code>InstitusiKami.crt</code> diunduh otomatis.</p>
  <div class="info-box red"><span class="icon">🚫</span> Hanya unduh sertifikat CA dari portal resmi SignKu.</div>
  <div class="tabs" style="margin-top:1.2rem;">
    <button class="tab active" data-target="p-ca-a">A. Adobe Acrobat</button>
    <button class="tab" data-target="p-ca-b">B. Foxit Reader</button>
  </div>
  <div class="tab-panel active" id="p-ca-a">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Acrobat Reader → <strong>Preferences</strong> → <strong>Signatures</strong> → <em>Identities &amp; Trusted Certificates</em> → <strong>More…</strong></p></li>
      <li><div class="instr-num">2</div><p><strong>Trusted Certificates</strong> → <strong>Import</strong> → <strong>Browse</strong> → pilih <code>InstitusiKami.crt</code>.</p></li>
      <li><div class="instr-num">3</div><p>Klik CA → <strong>Trust…</strong> → centang <em>"Use this certificate as a trusted root"</em> → <strong>OK</strong>.</p></li>
      <li><div class="instr-num">4</div><p>Klik <strong>Import</strong> → pastikan muncul <em>"Import Complete"</em>.</p></li>
    </ul>
  </div>
  <div class="tab-panel" id="p-ca-b">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Ubah ekstensi: <code>InstitusiKami.crt</code> → <code>InstitusiKami.cer</code>.</p></li>
      <li><div class="instr-num">2</div><p>Foxit Reader → tab <strong>Protect</strong> → <strong>Trusted Certificates</strong> → <strong>Add</strong>.</p></li>
      <li><div class="instr-num">3</div><p>Pilih file <code>.cer</code> → <strong>OK</strong> → pilih CA → <strong>Edit</strong> → centang <em>"Use this certificate as a trusted root"</em>.</p></li>
    </ul>
  </div>
</div>

<!-- STEP 3: REQUEST ID -->
<div class="card animate d3">
  <span class="step-badge">LANGKAH 03</span>
  <h2 style="font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:700;margin:0.5rem 0 1.2rem;">Request Digital ID + Status Approval</h2>
  <ul class="instr-list">
    <li><div class="instr-num">1</div><p>Login → menu <strong>Request Digital ID</strong> di sidebar.</p></li>
    <li><div class="instr-num">2</div><p>Pilih <strong>Role</strong>, isi <strong>Passphrase</strong> (minimal 8 karakter), konfirmasi → klik <strong>Request</strong>.</p></li>
    <li><div class="instr-num">3</div><p>Permintaan masuk antrian. Admin memproses secara manual.</p></li>
    <li><div class="instr-num">4</div><p>Cek status di menu <strong>Status Digital ID</strong>. Ready jika <em>Is Approved</em>, <em>Is Ready</em>, <em>Is Sent</em> = ✅.</p></li>
    <li><div class="instr-num">5</div><p>Anda akan menerima <strong>dua email</strong>: Public Key &amp; Certificate Digital. Unduh lampiran Certificate.</p></li>
  </ul>
  <div class="info-box warn mt-2"><span class="icon">⚠️</span> <strong>Passphrase wajib diingat!</strong> Digunakan saat import dan setiap penandatanganan.</div>
  <span class="validity">✔ Digital ID valid selama 2 tahun</span>
</div>

<!-- STEP 4: IMPORT ID -->
<div class="card animate">
  <span class="step-badge">LANGKAH 04</span>
  <h2 style="font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:700;margin:0.5rem 0 1.2rem;">Import Digital ID</h2>
  <div class="tabs">
    <button class="tab active" data-target="p-imp-a">A. Adobe Acrobat</button>
    <button class="tab" data-target="p-imp-b">B. Foxit Reader</button>
  </div>
  <div class="tab-panel active" id="p-imp-a">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Unduh file <code>nama.role.p12</code> dari email Certificate Digital.</p></li>
      <li><div class="instr-num">2</div><p>Acrobat → <strong>Preferences</strong> → <strong>Signatures</strong> → <strong>More…</strong> → <strong>Digital IDs</strong> → ikon <strong>+</strong>.</p></li>
      <li><div class="instr-num">3</div><p>Pilih <em>"My existing digital ID from: A file"</em> → <strong>Next</strong> → browse file <code>.p12</code> → masukkan Passphrase.</p></li>
      <li><div class="instr-num">4</div><p>Klik <strong>Finish</strong>. Pastikan Digital ID dari <em>Institusi Kami</em> muncul.</p></li>
    </ul>
  </div>
  <div class="tab-panel" id="p-imp-b">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Foxit → tab <strong>Protect</strong> → <strong>Digital IDs</strong> → <strong>Add ID</strong>.</p></li>
      <li><div class="instr-num">2</div><p>Pilih <em>"My existing digital ID from a file"</em> → <strong>Next</strong>.</p></li>
      <li><div class="instr-num">3</div><p>Browse file <code>.p12</code> → masukkan Passphrase → <strong>Next</strong> → <strong>Finish</strong>.</p></li>
    </ul>
  </div>
</div>

<!-- STEP 5: PENGGUNAAN -->
<div class="card animate">
  <span class="step-badge">LANGKAH 05</span>
  <h2 style="font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:700;margin:0.5rem 0 1.2rem;">Penggunaan Digital Sign</h2>
  <div class="tabs">
    <button class="tab active" data-target="p-sign-a">A. Acrobat Reader</button>
    <button class="tab" data-target="p-sign-b">B. Foxit Reader</button>
    <button class="tab" data-target="p-sign-c">C. Aplikasi Web (SignKu)</button>
  </div>
  <div class="tab-panel active" id="p-sign-a">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Buka PDF → klik <strong>Certificates</strong> di toolbar → <strong>Digitally Sign</strong>.</p></li>
      <li><div class="instr-num">2</div><p>Seret kursor untuk membuat kotak area tanda tangan.</p></li>
      <li><div class="instr-num">3</div><p>Pilih Digital ID → <strong>Continue</strong> → pilih Appearance → masukkan Passphrase → <strong>Sign</strong>.</p></li>
    </ul>
  </div>
  <div class="tab-panel" id="p-sign-b">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Foxit → tab <strong>Protect</strong> → <strong>Sign &amp; Certify</strong> → <strong>Place Signature…</strong></p></li>
      <li><div class="instr-num">2</div><p>Seret area → pilih Digital ID di <strong>Sign As:</strong> → pilih Appearance.</p></li>
      <li><div class="instr-num">3</div><p>Masukkan Passphrase → <strong>Sign</strong> → <strong>Save</strong>.</p></li>
    </ul>
  </div>
  <div class="tab-panel" id="p-sign-c">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Login ke SignKu → menu <strong>Tanda Tangani Dokumen</strong>.</p></li>
      <li><div class="instr-num">2</div><p>Upload file PDF. Dokumen akan tampil di viewer interaktif.</p></li>
      <li><div class="instr-num">3</div><p>Geser kotak merah ke posisi yang ingin ditandatangani.</p></li>
      <li><div class="instr-num">4</div><p>Masukkan kode <strong>OTP</strong> dari aplikasi Authenticator → klik <strong>Tanda Tangani</strong>.</p></li>
      <li><div class="instr-num">5</div><p>Dokumen bertanda tangan dapat diunduh dari menu <strong>Riwayat Dokumen</strong>.</p></li>
    </ul>
    <div class="info-box blue mt-2"><span class="icon">ℹ️</span> Pastikan OTP sudah diset dan Digital ID sudah disetujui sebelum menggunakan fitur ini.</div>
  </div>
</div>

<!-- STEP 6: VERIFIKASI -->
<div class="card animate">
  <span class="step-badge">LANGKAH 06</span>
  <h2 style="font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:700;margin:0.5rem 0 1.2rem;">Verifikasi Keaslian Tanda Tangan</h2>
  <ul class="instr-list">
    <li><div class="instr-num">1</div><p>Buka file PDF di Acrobat Reader atau Foxit Reader.</p></li>
    <li><div class="instr-num">2</div><p>Klik <strong>Signature Panel</strong> (Acrobat) atau <strong>Protect → Validate</strong> (Foxit).</p></li>
    <li><div class="instr-num">3</div><p>Panel Signatures menampilkan: <strong>Signed by</strong> (nama penandatangan) dan <strong>Signature Details</strong>.</p></li>
  </ul>
  <div class="grid-2 mt-2">
    <div class="platform-card" style="background:#f8fafc;border:1px solid var(--border);border-radius:12px;padding:1.2rem;">
      <h4 style="font-size:.9rem;font-weight:600;margin-bottom:.6rem;">🔵 Acrobat Reader</h4>
      <p style="font-size:.85rem;color:var(--mid);line-height:1.5;"><em>"Signed and all signatures are valid"</em> — tanda tangan sah, dokumen tidak dimodifikasi.</p>
    </div>
    <div class="platform-card" style="background:#f8fafc;border:1px solid var(--border);border-radius:12px;padding:1.2rem;">
      <h4 style="font-size:.9rem;font-weight:600;margin-bottom:.6rem;">🟠 Foxit Reader</h4>
      <p style="font-size:.85rem;color:var(--mid);line-height:1.5;"><em>"Signature is valid"</em> di panel Digital Signatures — tanda tangan terverifikasi.</p>
    </div>
  </div>
</div>

<!-- STEP 7: TAMPILAN TTD -->
<div class="card animate">
  <span class="step-badge">LANGKAH 07</span>
  <h2 style="font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:700;margin:0.5rem 0 1.2rem;">Pembuatan Tampilan Tanda Tangan Digital</h2>
  <div class="tabs">
    <button class="tab active" data-target="p-style-a">Acrobat Reader</button>
    <button class="tab" data-target="p-style-b">Foxit Reader</button>
  </div>
  <div class="tab-panel active" id="p-style-a">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Dialog penandatanganan → klik <strong>Create</strong> di sebelah menu Appearance.</p></li>
      <li><div class="instr-num">2</div><p>Klik ikon <strong>Image</strong> → <strong>Browse</strong> → unggah gambar tanda tangan format <strong>PDF</strong>.</p></li>
      <li><div class="instr-num">3</div><p>Pilih teks yang ditampilkan (nama, tanggal, logo) → isi <strong>Preset name</strong> → <strong>Save</strong>.</p></li>
    </ul>
  </div>
  <div class="tab-panel" id="p-style-b">
    <ul class="instr-list">
      <li><div class="instr-num">1</div><p>Dialog penandatanganan → <strong>Create New Style</strong> dari dropdown <em>Appearance Type</em>.</p></li>
      <li><div class="instr-num">2</div><p>Isi <strong>Title</strong> → pilih <strong>Imported Graphics</strong> → browse gambar (JPG/PNG/PDF).</p></li>
      <li><div class="instr-num">3</div><p>Atur <strong>Configure Text</strong> → <strong>OK</strong>.</p></li>
    </ul>
  </div>
  <div class="info-box green mt-2"><span class="icon">✅</span> Format: <strong>PDF</strong> untuk Acrobat; <strong>JPG, PNG, atau PDF</strong> untuk Foxit.</div>
</div>

</div>
