<?php // pages/register.php
if (isLoggedIn()) { header('Location: ' . BASE_URL); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { flash('error','Token tidak valid.'); header('Location: ?page=register'); exit; }
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $role    = $_POST['role'] ?? 'mahasiswa';

    if (strlen($name) < 3) { flash('error','Nama minimal 3 karakter.'); header('Location: ?page=register'); exit; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('error','Format email tidak valid.'); header('Location: ?page=register'); exit; }
    if (strlen($pass) < 8) { flash('error','Password minimal 8 karakter.'); header('Location: ?page=register'); exit; }
    if ($pass !== $confirm) { flash('error','Password tidak cocok.'); header('Location: ?page=register'); exit; }

    $check = db()->prepare('SELECT id FROM users WHERE email=?');
    $check->execute([$email]);
    if ($check->fetch()) { flash('error','Email sudah terdaftar.'); header('Location: ?page=register'); exit; }

    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost'=>12]);
    $ins  = db()->prepare('INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)');
    $ins->execute([$name,$email,$hash,$role]);
    $id = db()->lastInsertId();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    logActivity('register','Akun baru didaftarkan');
    flash('success','Akun berhasil dibuat! Silakan setup OTP sekarang.');
    header('Location: ?page=setup-otp'); exit;
}
?>
<div style="max-width:480px;margin:4rem auto;padding:0 1.5rem;">
<div class="card animate">
  <div style="text-align:center;margin-bottom:1.8rem;">
    <div style="font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:800;color:var(--blue);">Sign<span style="color:var(--teal);">Ku</span></div>
    <p style="color:var(--mid);font-size:.9rem;margin-top:.3rem;">Buat akun baru</p>
  </div>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <div class="form-group">
      <label>Nama Lengkap</label>
      <input type="text" name="name" required placeholder="Nama Lengkap Anda">
    </div>
    <div class="form-group">
      <label>Email Institusi</label>
      <input type="email" name="email" required placeholder="nama@institusi.id">
    </div>
    <div class="form-group">
      <label>Peran</label>
      <select name="role">
        <option value="mahasiswa">Mahasiswa</option>
        <option value="dosen">Dosen</option>
        <option value="staff">Staff</option>
      </select>
    </div>
    <div class="form-row">
      <div class="form-group mb-0">
        <label>Password</label>
        <input type="password" name="password" required placeholder="Min. 8 karakter">
      </div>
      <div class="form-group mb-0">
        <label>Konfirmasi Password</label>
        <input type="password" name="confirm" required placeholder="Ulangi password">
      </div>
    </div>
    <button class="btn btn-primary btn-lg" style="width:100%;justify-content:center;margin-top:1.4rem;" type="submit">Buat Akun</button>
  </form>
  <p style="text-align:center;margin-top:1.2rem;font-size:.88rem;color:var(--mid);">
    Sudah punya akun? <a href="?page=login">Masuk di sini</a>
  </p>
</div>
</div>
