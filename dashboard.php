<?php
require 'config.php';
if (!isset($_SESSION['login'])) { header('Location: login.php'); exit; }
$saya = null;
foreach ($_SESSION['mahasiswa'] as $m) {
    if (($m['nim'] ?? '') === $_SESSION['login']) { $saya = $m; break; }
}
if (!$saya) { unset($_SESSION['login']); header('Location: login.php'); exit; }

$rata   = hitung_rata_nilai($saya);
$status = hitung_status($saya);
$inisial = mb_strtoupper(mb_substr(trim($saya['nama']), 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Mahasiswa</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="form.css">
</head>
<body class="reg-body">
<header>
  <h1>Dashboard Mahasiswa</h1>
  <nav>
    <a href="dashboard.php" aria-current="page">Dashboard</a>
    <button type="button" class="nav-btn" id="btnLogout">Logout</button>
  </nav>
</header>

<main class="reg">
  <!-- Identitas singkat -->
  <section class="profile-hero">
    <div class="avatar" aria-hidden="true"><?= e($inisial) ?></div>
    <div class="hero-text">
      <h2>Halo, <?= e($saya['nama']) ?></h2>
      <p class="hero-facts">
        <span>NIM <strong><?= e($saya['nim']) ?></strong></span>
        <span><?= e($saya['prodi']) ?></span>
        <span>Semester <?= e(semester_tampil($saya)) ?></span>
      </p>
    </div>
    <div class="hero-badge"><?= badge_status($status) ?></div>
  </section>

  <div class="reg-sheet">

    <div class="sec">
      <div>
        <h3>Data akademik</h3>
        <p class="sec-note">Program studi, kelas, dan informasi tes masuk.</p>
      </div>
      <dl class="kv">
        <div><dt>NIM</dt><dd><?= e($saya['nim']) ?></dd></div>
        <div><dt>Program studi</dt><dd><?= e($saya['prodi']) ?></dd></div>
        <div><dt>Kelas / Reguler</dt><dd><?= e($saya['reguler'] ?? '-') ?></dd></div>
        <div><dt>Tahun masuk</dt><dd><?= e($saya['tahun'] ?? '-') ?></dd></div>
        <div><dt>Semester saat ini</dt><dd>Semester <?= e(semester_tampil($saya)) ?></dd></div>
        <div><dt>Kode pendaftaran</dt><dd><?= e($saya['kode'] ?? '-') ?></dd></div>
        <div class="full"><dt>Keterangan tes</dt><dd><?= e(keterangan_kode($saya['kode'] ?? '')) ?></dd></div>
      </dl>
    </div>

    <div class="sec">
      <div>
        <h3>Data diri</h3>
        <p class="sec-note">Data pribadi yang kamu isi saat mendaftar.</p>
      </div>
      <dl class="kv">
        <div class="full"><dt>Nama lengkap</dt><dd><?= e($saya['nama']) ?></dd></div>
        <div><dt>Tempat, tanggal lahir</dt><dd><?= e($saya['tempat']) ?>, <?= e($saya['tgl']) ?></dd></div>
        <div><dt>Jenis kelamin</dt><dd><?= e($saya['jk']) ?></dd></div>
        <div><dt>Email</dt><dd><?= e($saya['email']) ?></dd></div>
        <div><dt>No. HP</dt><dd><?= e($saya['hp']) ?></dd></div>
        <div class="full"><dt>Alamat</dt><dd><?= e($saya['alamat']) ?></dd></div>
        <div><dt>Asal sekolah</dt><dd><?= e($saya['sekolah'] ?? '-') ?></dd></div>
        <div><dt>Pekerjaan orang tua</dt><dd><?= e($saya['pekerjaan'] ?? '-') ?></dd></div>
      </dl>
    </div>

    <div class="sec">
      <div>
        <h3>Nilai tes</h3>
        <p class="sec-note">Rata-rata 70 ke atas dinyatakan Lulus.</p>
      </div>
      <div class="fields">
        <div class="row3">
          <div class="score-box"><span>Matematika</span><strong><?= e($saya['nilai_mat'] ?? '-') ?></strong></div>
          <div class="score-box"><span>B. Inggris</span><strong><?= e($saya['nilai_ing'] ?? '-') ?></strong></div>
          <div class="score-box"><span>Pel. Umum</span><strong><?= e($saya['nilai_umum'] ?? '-') ?></strong></div>
        </div>
        <div>
          <div class="avg-row">
            <span class="label">Rata-rata nilai tes: <strong><?= e($rata) ?></strong></span>
            <?= badge_status($status) ?>
          </div>
          <?php if (is_numeric($rata)): ?>
            <div class="meter has" aria-hidden="true">
              <span class="meter-marker" style="left:<?= min(100, max(0, (float)$rata)) ?>%"></span>
              <span class="meter-tick" style="left:0">0</span>
              <span class="meter-tick" style="left:60%">60</span>
              <span class="meter-tick" style="left:70%">70</span>
              <span class="meter-tick" style="left:100%">100</span>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>

  <dialog id="dlgLogout" aria-labelledby="judulLogout">
    <h3 id="judulLogout">Yakin ingin logout?</h3>
    <p>Kamu akan keluar dari akun <strong><?= e($saya['nama']) ?></strong>.</p>
    <form method="post" action="logout.php" class="dlg-actions">
      <button type="button" class="btn btn-ghost" id="batalLogout">Batal</button>
      <button type="submit" class="btn btn-solid-danger">Ya, logout</button>
    </form>
  </dialog>
</main>

<script>
var dlg = document.getElementById('dlgLogout');
document.getElementById('btnLogout').addEventListener('click', function () { dlg.showModal(); });
document.getElementById('batalLogout').addEventListener('click', function () { dlg.close(); });
</script>
</body>
</html>