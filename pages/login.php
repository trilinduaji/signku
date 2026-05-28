<?php // pages/login.php
if (isLoggedIn()) { header('Location: ' . BASE_URL); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) { flash('error','Token tidak valid.'); header('Location: ?page=login'); exit; }
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $stmt  = db()->prepare('SELECT * FROM users WHERE email=?');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if ($u && password_verify($pass, $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $u['id'];
        logActivity('login', 'Login berhasil');
        flash('success', 'Selamat datang, ' . $u['name'] . '!');
        header('Location: ' . BASE_URL); exit;
    }
    flash('error', 'Email atau password salah.');
    header('Location: ?page=login'); exit;
}
?>
<div style="max-width:440px;margin:4rem auto;padding:0 1.5rem;">
<div class="card animate">
  <div style="text-align:center;margin-bottom:1.8rem;">
    <div style="font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:800;color:var(--blue);">Sign<span style="color:var(--teal);">Ku</span></div>
    <p style="color:var(--mid);font-size:.9rem;margin-top:.3rem;">Masuk ke akun Anda</p>
  </div>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required placeholder="nama@institusi.id">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" required placeholder="••••••••">
    </div>
    <button class="btn btn-primary btn-lg" style="width:100%;justify-content:center;" type="submit">Masuk</button>
  </form>
  <p style="text-align:center;margin-top:1.2rem;font-size:.88rem;color:var(--mid);">
    Belum punya akun? <a href="?page=register">Daftar di sini</a>
  </p>
</div>
</div>
