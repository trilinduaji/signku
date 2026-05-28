<?php
ob_start(); // Buffer semua output — header() bisa dipanggil kapan saja

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/totp.php';

$page = $_GET['page'] ?? 'home';
$allowed = [
    'home', 'login', 'register', 'logout',
    'setup-otp', 'reset-otp',
    'sertifikat', 'request-id', 'import-id',
    'verifikasi', 'tampilan-ttd',
    'sign-document', 'signing-list', 'download-signed',
    'api-sign-doc', 'api-upload-pdf', 'api-debug-otp',
    'panduan',
];

if (!in_array($page, $allowed, true)) {
    $page = 'home';
}

// API endpoints — output JSON dan langsung exit
if (str_starts_with($page, 'api-')) {
    ob_end_clean(); // Buang buffer, kirim JSON langsung
    require_once __DIR__ . '/pages/' . $page . '.php';
    exit;
}

// download-signed adalah file download, tidak pakai layout HTML
if ($page === 'download-signed') {
    ob_end_clean();
    require_once __DIR__ . '/pages/download-signed.php';
    exit;
}

// logout & reset-otp hanya redirect, tidak perlu layout
if ($page === 'logout') {
    ob_end_clean();
    require_once __DIR__ . '/pages/logout.php';
    exit;
}
if ($page === 'reset-otp') {
    ob_end_clean();
    require_once __DIR__ . '/pages/reset-otp.php';
    exit;
}

// Flash message
$flash = getFlash();
$user  = currentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SignKu – <?= h(ucfirst(str_replace('-', ' ', $page))) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
/* ─── DESIGN TOKENS ─────────────────────────────────────── */
:root {
  --ink:    #0d1117;
  --paper:  #f5f2ec;
  --blue:   #1a4fd6;
  --blue2:  #0f3298;
  --teal:   #00b8a2;
  --accent: #e8f0fe;
  --warn:   #e74c3c;
  --mid:    #6b7280;
  --border: #d6d0c4;
  --card:   #ffffff;
  --radius: 14px;
  --green:  #16a34a;
  --orange: #ea580c;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'DM Sans', sans-serif;
  background: var(--paper);
  color: var(--ink);
  min-height: 100vh;
}
a { color: var(--blue); text-decoration: none; }
code, .mono { font-family: 'JetBrains Mono', monospace; font-size: .82em; }

/* ─── NAV ───────────────────────────────────────────────── */
nav {
  position: sticky; top: 0; z-index: 200;
  background: rgba(245,242,236,.92);
  backdrop-filter: blur(14px);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 2rem; height: 62px;
}
.nav-logo {
  font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.45rem;
  color: var(--blue); display: flex; align-items: center; gap: .45rem;
}
.nav-logo span { color: var(--teal); }
.nav-links { display: flex; gap: 1.4rem; list-style: none; }
.nav-links a { font-size: .85rem; font-weight: 500; color: var(--mid); transition: color .2s; }
.nav-links a:hover, .nav-links a.active { color: var(--blue); }
.nav-right { display: flex; gap: .7rem; align-items: center; }
.btn { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem 1.1rem;
  border-radius: 99px; font-size: .85rem; font-weight: 600; border: none; cursor: pointer;
  transition: all .15s; text-decoration: none; }
.btn-primary { background: var(--blue); color: #fff; }
.btn-primary:hover { background: var(--blue2); }
.btn-outline { background: transparent; border: 1.5px solid var(--border); color: var(--ink); }
.btn-outline:hover { border-color: var(--blue); color: var(--blue); }
.btn-danger  { background: var(--warn); color: #fff; }
.btn-teal    { background: var(--teal); color: #fff; }
.btn-lg { padding: .7rem 1.8rem; font-size: .95rem; }

/* ─── PROGRESS BAR ──────────────────────────────────────── */
#progress-bar {
  position: fixed; top: 0; left: 0; height: 3px;
  background: linear-gradient(90deg, var(--teal), var(--blue));
  width: 0%; z-index: 300; transition: width .12s linear;
}

/* ─── SIDEBAR LAYOUT ────────────────────────────────────── */
.layout { display: flex; min-height: calc(100vh - 62px); }
.sidebar {
  width: 240px; flex-shrink: 0;
  background: var(--card); border-right: 1px solid var(--border);
  padding: 1.5rem 1rem; position: sticky; top: 62px;
  height: calc(100vh - 62px); overflow-y: auto;
}
.sidebar-section { margin-bottom: 1.4rem; }
.sidebar-label {
  font-size: .7rem; font-weight: 700; letter-spacing: .08em;
  text-transform: uppercase; color: var(--mid); padding: 0 .5rem;
  margin-bottom: .5rem; display: block;
}
.sidebar a {
  display: flex; align-items: center; gap: .6rem;
  padding: .5rem .7rem; border-radius: 8px;
  font-size: .875rem; font-weight: 500; color: var(--ink);
  transition: all .15s; margin-bottom: 2px;
}
.sidebar a:hover { background: var(--accent); color: var(--blue); }
.sidebar a.active { background: var(--blue); color: #fff; }
.sidebar a .icon { font-size: 1rem; flex-shrink: 0; }

/* ─── MAIN CONTENT ──────────────────────────────────────── */
.main { flex: 1; padding: 2.5rem; max-width: 900px; }
.page-header { margin-bottom: 2rem; }
.page-title {
  font-family: 'Syne', sans-serif; font-size: 1.7rem; font-weight: 700;
  margin-bottom: .3rem;
}
.page-sub { color: var(--mid); font-size: .93rem; }
.step-badge {
  display: inline-block; background: var(--blue); color: #fff;
  font-size: .72rem; font-weight: 700; padding: .3rem .75rem;
  border-radius: 99px; margin-bottom: .5rem;
}

/* ─── CARDS ─────────────────────────────────────────────── */
.card {
  background: var(--card); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 1.8rem;
  margin-bottom: 1.5rem;
}
.card-title {
  font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1.05rem;
  margin-bottom: 1.2rem; display: flex; align-items: center; gap: .5rem;
}

/* ─── TABS ──────────────────────────────────────────────── */
.tabs { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
.tab {
  padding: .45rem 1.1rem; border-radius: 99px; font-size: .85rem; font-weight: 500;
  border: 1.5px solid var(--border); background: transparent; color: var(--mid);
  cursor: pointer; transition: all .15s;
}
.tab.active, .tab:hover { background: var(--blue); border-color: var(--blue); color: #fff; }
.tab-panel { display: none; }
.tab-panel.active { display: block; animation: fadeIn .25s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

/* ─── INSTRUCTION LIST ──────────────────────────────────── */
.instr-list { list-style: none; display: flex; flex-direction: column; gap: .9rem; }
.instr-list li { display: flex; gap: 1rem; align-items: flex-start; }
.instr-num {
  width: 26px; height: 26px; border-radius: 50%;
  background: var(--blue); color: #fff; font-size: .75rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;
}
.instr-list li p { font-size: .92rem; line-height: 1.65; }
.instr-list li strong { color: var(--blue2); }

/* ─── INFO BOXES ────────────────────────────────────────── */
.info-box {
  border-radius: 10px; padding: .95rem 1.1rem; font-size: .88rem;
  line-height: 1.55; margin-top: 1rem; display: flex; gap: .7rem; align-items: flex-start;
}
.info-box.blue   { background: #eff6ff; border-left: 3px solid var(--blue); }
.info-box.warn   { background: #fff7ed; border-left: 3px solid #f97316; }
.info-box.green  { background: #f0fdf4; border-left: 3px solid #22c55e; }
.info-box.red    { background: #fff1f2; border-left: 3px solid var(--warn); }

/* ─── FLASH MESSAGES ────────────────────────────────────── */
.flash {
  padding: .9rem 1.2rem; border-radius: 10px; margin-bottom: 1.5rem;
  font-size: .9rem; font-weight: 500; display: flex; align-items: center; gap: .6rem;
}
.flash.success { background: #f0fdf4; border: 1px solid #86efac; color: #15803d; }
.flash.error   { background: #fff1f2; border: 1px solid #fca5a5; color: #991b1b; }
.flash.info    { background: #eff6ff; border: 1px solid #93c5fd; color: #1e40af; }

/* ─── FORMS ─────────────────────────────────────────────── */
.form-group { margin-bottom: 1.2rem; }
.form-group label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: .4rem; }
.form-group input, .form-group select, .form-group textarea {
  width: 100%; padding: .6rem .9rem; border: 1.5px solid var(--border);
  border-radius: 8px; font-size: .9rem; font-family: inherit; background: #fff;
  transition: border-color .15s;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
  outline: none; border-color: var(--blue);
  box-shadow: 0 0 0 3px rgba(26,79,214,.1);
}
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

/* ─── TABLES ────────────────────────────────────────────── */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: .88rem; }
th { background: #f8fafc; font-weight: 600; text-align: left; padding: .7rem 1rem; border-bottom: 2px solid var(--border); }
td { padding: .65rem 1rem; border-bottom: 1px solid var(--border); }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #f8fafc; }

/* ─── STATUS BADGES ─────────────────────────────────────── */
.badge {
  display: inline-flex; align-items: center; gap: .3rem;
  padding: .25rem .7rem; border-radius: 99px; font-size: .75rem; font-weight: 600;
}
.badge-green  { background: #dcfce7; color: var(--green); }
.badge-yellow { background: #fef9c3; color: #92400e; }
.badge-red    { background: #fee2e2; color: #991b1b; }
.badge-blue   { background: var(--accent); color: var(--blue); }

/* ─── QR CONTAINER ──────────────────────────────────────── */
.qr-wrap {
  display: flex; flex-direction: column; align-items: center; gap: 1rem;
  padding: 2rem; background: #f8fafc; border-radius: var(--radius);
  border: 1px solid var(--border);
}
.qr-wrap img { width: 240px; height: 240px; image-rendering: pixelated; border-radius: 8px; }
.qr-wrap #qr-div img { width: 240px !important; height: 240px !important; border-radius: 8px; display: block; }
.qr-wrap canvas { border-radius: 8px; }
.secret-display {
  background: var(--ink); color: var(--teal); padding: .6rem 1.2rem;
  border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: .85rem;
  letter-spacing: .08em; word-break: break-all; text-align: center;
}

/* ─── PDF VIEWER ────────────────────────────────────────── */
#pdf-canvas-wrap {
  position: relative; border: 1.5px solid var(--border);
  border-radius: var(--radius); overflow: hidden; cursor: crosshair;
  background: #e5e7eb; min-height: 400px;
}
#pdf-canvas { display: block; max-width: 100%; }
#sign-overlay {
  position: absolute; border: 2px dashed var(--warn); background: rgba(231,76,60,.12);
  cursor: move; border-radius: 4px;
  display: flex; align-items: center; justify-content: center;
  font-size: .75rem; color: var(--warn); font-weight: 600;
}
.pdf-controls { display: flex; align-items: center; gap: .7rem; margin-bottom: .8rem; flex-wrap: wrap; }

/* ─── HERO (Home only) ──────────────────────────────────── */
.hero {
  background: linear-gradient(135deg, var(--blue2) 0%, var(--blue) 60%, #2563eb 100%);
  color: #fff; padding: 4rem 2rem 3.5rem; text-align: center;
  position: relative; overflow: hidden;
}
.hero::before {
  content: ''; position: absolute; inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
}
.hero h1 { font-family: 'Syne', sans-serif; font-size: clamp(1.8rem,4vw,2.8rem); font-weight: 800; margin-bottom: .8rem; }
.hero h1 em { font-style: normal; color: #93c5fd; }
.hero p { opacity: .85; max-width: 520px; margin: 0 auto 1.8rem; line-height: 1.6; }

/* ─── GRID CARDS ────────────────────────────────────────── */
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.grid-3 { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; }
@media(max-width: 720px) { .grid-2,.grid-3 { grid-template-columns: 1fr; } .form-row { grid-template-columns: 1fr; } }

/* ─── UPLOAD DROPZONE ───────────────────────────────────── */
.dropzone {
  border: 2px dashed var(--border); border-radius: var(--radius);
  padding: 2.5rem; text-align: center; cursor: pointer;
  transition: all .2s; background: #fafafa;
}
.dropzone:hover, .dropzone.drag { border-color: var(--blue); background: var(--accent); }
.dropzone p { color: var(--mid); font-size: .9rem; margin-top: .5rem; }

/* ─── ANIMATIONS ────────────────────────────────────────── */
@keyframes fadeUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:none; } }
.animate { animation: fadeUp .4s ease both; }
.d1 { animation-delay: .1s; } .d2 { animation-delay: .2s; } .d3 { animation-delay: .3s; }

/* ─── MISC ──────────────────────────────────────────────── */
.divider { height: 3px; width: 40px; background: var(--blue); border-radius: 2px; margin: .4rem 0 1.5rem; }
hr.sep { border: none; border-top: 1px solid var(--border); margin: 1.5rem 0; }
.text-center { text-align: center; }
.text-mid { color: var(--mid); }
.mt-1 { margin-top: .5rem; } .mt-2 { margin-top: 1rem; } .mt-3 { margin-top: 1.5rem; }
.mb-0 { margin-bottom: 0; }
.validity { display: inline-flex; align-items: center; gap: .4rem; background: #f0fdf4;
  border: 1px solid #86efac; color: #15803d; border-radius: 99px;
  padding: .3rem .9rem; font-size: .82rem; font-weight: 600; margin-top: .8rem; }
#btt {
  position: fixed; bottom: 2rem; right: 1.8rem; width: 44px; height: 44px;
  border-radius: 50%; background: var(--blue); color: #fff; font-size: 1.1rem;
  display: none; align-items: center; justify-content: center;
  cursor: pointer; box-shadow: 0 4px 14px rgba(26,79,214,.35); border: none; z-index: 150;
}
#btt.show { display: flex; }
</style>
</head>
<body>

<div id="progress-bar"></div>

<!-- NAV -->
<nav>
  <a class="nav-logo" href="<?= BASE_URL ?>">Sign<span>Ku</span></a>
  <ul class="nav-links">
    <li><a href="?page=panduan">Panduan</a></li>
    <li><a href="?page=setup-otp">OTP</a></li>
    <li><a href="?page=request-id">Digital ID</a></li>
    <li><a href="?page=sign-document">Tanda Tangani</a></li>
    <li><a href="?page=signing-list">Riwayat</a></li>
  </ul>
  <div class="nav-right">
    <span style="font-size:.85rem;color:var(--mid);">👤 <strong style="color:var(--ink);">Tri Lindu Aji</strong></span>
  </div>
</nav>

<!-- FLASH -->
<?php if ($flash): ?>
<div style="padding:0 2rem;margin-top:1rem;">
  <div class="flash <?= h($flash['type']) ?>">
    <?= $flash['type'] === 'success' ? '✅' : ($flash['type'] === 'error' ? '❌' : 'ℹ️') ?>
    <?= $flash['msg'] ?>
  </div>
</div>
<?php endif; ?>

<?php
$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (file_exists($pageFile)) {
    require $pageFile;
} else {
    require __DIR__ . '/pages/home.php';
}
?>

<button id="btt" title="Ke atas">↑</button>
<script>
const bar = document.getElementById('progress-bar');
const btt = document.getElementById('btt');
window.addEventListener('scroll', () => {
  const pct = (window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100;
  bar.style.width = pct + '%';
  btt.classList.toggle('show', window.scrollY > 400);
});
btt.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

document.querySelectorAll('.tabs').forEach(group => {
  group.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const target = document.getElementById(tab.dataset.target);
      if (!target) return;
      const wrap = group.closest('.card, .step-section') || document.body;
      group.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      wrap.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      target.classList.add('active');
    });
  });
});
</script>
</body>
</html>
