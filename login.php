<?php
require 'config.php';

if (isset($_SESSION['login'])) { header('Location: dashboard.php'); exit; }

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '') $errors['username'] = 'Username wajib diisi.';
    if ($password === '') $errors['password'] = 'Password wajib diisi.';

    if (!$errors) {
        $m = cari_berdasarkan_nama($username);
        if ($m && empty($m['nim'])) {
            // Peserta Cadangan / Tidak Lulus tidak punya NIM, jadi belum punya akun
            $errors['umum'] = 'Akun belum aktif. Hasil seleksi: ' . hitung_status($m) . ' (NIM hanya dibuat untuk peserta yang Lulus).';
        } elseif ($m && password_verify($password, $m['password'] ?? '')) {
            session_regenerate_id(true);
            $_SESSION['login'] = $m['nim'];
            header('Location: dashboard.php'); exit;
        } else {
            $errors['umum'] = 'Username atau password salah.';
        }
    }
}

function cls_login($errors, $k) {
    return isset($errors[$k]) ? ' class="invalid" aria-invalid="true" aria-describedby="err-'.$k.'"' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login Mahasiswa</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="form.css">
</head>
<body class="reg-body">
<header>
  <h1>Login Mahasiswa</h1>
  <nav><a href="index.php">Pendaftaran</a><a href="data.php">Data Mahasiswa</a><a href="login.php" aria-current="page">Login</a></nav>
</header>

<main class="reg auth">
  <div class="auth-card">
    <h2>Masuk</h2>
    <p class="lead">Masuk untuk melihat data diri dan hasil seleksi kamu.</p>

    <?php if (isset($errors['umum'])): ?><div class="alert err" role="alert"><?= e($errors['umum']) ?></div><?php endif; ?>

    <form method="post" novalidate>
      <div class="field">
        <label for="username">Username</label>
        <input id="username" name="username" autocomplete="username" autofocus value="<?= e($username) ?>"<?= cls_login($errors,'username') ?>>
        <div class="error" id="err-username"><?= e($errors['username'] ?? '') ?></div>
        <p class="help">Isi dengan nama lengkap sesuai saat mendaftar.</p>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="pw-wrap">
          <input id="password" name="password" type="password" autocomplete="current-password"<?= cls_login($errors,'password') ?>>
          <button type="button" class="pw-toggle" id="pwToggle" aria-pressed="false" aria-controls="password">Lihat</button>
        </div>
        <div class="error" id="err-password"><?= e($errors['password'] ?? '') ?></div>
        <p class="help">Isi dengan NIM kamu. NIM hanya dibuat untuk peserta yang dinyatakan Lulus.</p>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Masuk</button>
    </form>

    <p class="auth-foot">Belum punya akun? <a href="index.php">Daftar dulu</a></p>
  </div>
</main>

<script>
(function () {
  var pw = document.getElementById('password');
  var btn = document.getElementById('pwToggle');
  btn.addEventListener('click', function () {
    var tampil = pw.type === 'password';
    pw.type = tampil ? 'text' : 'password';
    btn.textContent = tampil ? 'Sembunyikan' : 'Lihat';
    btn.setAttribute('aria-pressed', tampil ? 'true' : 'false');
  });
  // Hapus pesan error saat isian diperbaiki
  document.querySelectorAll('.field input').forEach(function (inp) {
    inp.addEventListener('input', function () {
      inp.classList.remove('invalid');
      inp.removeAttribute('aria-invalid');
      var err = inp.closest('.field').querySelector('.error');
      if (err) err.textContent = '';
    });
  });
})();
</script>
</body>
</html>