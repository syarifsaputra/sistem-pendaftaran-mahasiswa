<?php
require 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $_SESSION['edit_id'] = $_POST['id'] ?? '';
    header('Location: index.php'); exit;
}
unset($_SESSION['edit_id']);
$flash = $_SESSION['flash'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'ok';
unset($_SESSION['flash'], $_SESSION['flash_type']);

// ---- Fungsi bantu tampilan ----
function isi($m, $k) { $v = $m[$k] ?? ''; return e($v === '' ? '-' : $v); }
function tgl_id($t) { $ts = $t ? strtotime($t) : false; return $ts ? date('d-m-Y', $ts) : '-'; }
function pct($n, $total) { return $total > 0 ? (int) round(($n / $total) * 100) : 0; }

// PENCARIAN HANYA: Kode Daftar, NIM, Nama
function teks_cari($m) {
    return mb_strtolower(implode(' ', [
        $m['kode'] ?? '',
        keterangan_kode($m['kode'] ?? ''),
        $m['nim'] ?? '',
        $m['nama'] ?? '',
    ]));
}

// ---- PENCARIAN ----
$cari = trim($_GET['cari'] ?? '');
$semua_data = $_SESSION['mahasiswa'];
$data_tampil = $semua_data;
if ($cari !== '') {
    $kata = preg_split('/\s+/', mb_strtolower($cari), -1, PREG_SPLIT_NO_EMPTY);
    $data_tampil = array_values(array_filter($semua_data, function ($m) use ($kata) {
        $teks = teks_cari($m);
        foreach ($kata as $k) {
            if (mb_strpos($teks, $k) === false) return false;
        }
        return true;
    }));
}

// ---- RINGKASAN ----
$kunci_status = ['Lulus' => 'lulus', 'Cadangan' => 'cadangan', 'Tidak Lulus' => 'tidak'];
$jumlah = ['semua' => count($semua_data), 'lulus' => 0, 'cadangan' => 0, 'tidak' => 0, 'belum' => 0];
foreach ($semua_data as $m) {
    $jumlah[$kunci_status[hitung_status($m)] ?? 'belum']++;
}
$jumlah_kolom = 22;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Mahasiswa</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="form.css">
<style>
:root{
  --bg:#EEF1F5; --card:#ffffff; --line:#D5DBE5; --txt:#1B2433; --muted:#5B6678;
  --pri:#2347C5; --pri-d:#1B3BA3;
  --ok:#12805C; --warn:#B7791F; --bad:#C53030; --info:#2347C5;
}
body{ background:var(--bg); color:var(--txt); }
main.full section{
  background:var(--card); border:1px solid var(--line); border-radius:18px;
  padding:24px; box-shadow:0 10px 30px rgba(31,39,51,.06);
}
.page-head{
  display:flex; align-items:flex-start; justify-content:space-between;
  gap:16px; flex-wrap:wrap; margin-bottom:6px;
}
.page-head h2{ margin:0; font-size:1.45rem; letter-spacing:-.2px; }
.page-head .sub{ margin:4px 0 0; color:var(--muted); font-size:.9rem; }
.page-head .btn{
  background:var(--pri); color:#fff; border-radius:12px; padding:11px 18px;
  font-weight:600; text-decoration:none; box-shadow:0 6px 14px rgba(35,71,197,.28);
  transition:.18s;
}
.page-head .btn:hover{ background:var(--pri-d); transform:translateY(-1px); }

/* Kartu ringkasan */
.stat-grid{
  display:grid; gap:14px; margin:18px 0 22px;
  grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
}
.stat-card{
  position:relative; overflow:hidden; display:flex; gap:14px; align-items:center;
  background:var(--card); border:1px solid var(--line); border-radius:16px;
  padding:16px 18px; transition:.2s;
}
.stat-card:hover{ transform:translateY(-3px); box-shadow:0 12px 24px rgba(31,39,51,.09); }
.stat-card::before{ content:""; position:absolute; left:0; top:0; bottom:0; width:5px; background:var(--c,var(--pri)); }
.stat-ico{
  width:44px; height:44px; flex:0 0 44px; border-radius:13px; display:grid; place-items:center;
  background:color-mix(in srgb, var(--c,var(--pri)) 14%, #fff); color:var(--c,var(--pri));
}
.stat-body{ min-width:0; }
.stat-label{ display:block; color:var(--muted); font-size:.78rem; font-weight:600; letter-spacing:.3px; text-transform:uppercase; }
.stat-value{ display:block; font-size:1.6rem; font-weight:800; line-height:1.25; }
.stat-meta{ display:block; font-size:.75rem; color:var(--muted); margin-top:2px; }
.stat-bar{ height:5px; border-radius:99px; background:#eef1f8; margin-top:8px; overflow:hidden; }
.stat-bar i{ display:block; height:100%; border-radius:99px; background:var(--c,var(--pri)); }
.c-semua  { --c:var(--info); }
.c-lulus  { --c:var(--ok); }
.c-cadang { --c:var(--warn); }
.c-tidak  { --c:var(--bad); }
.c-belum  { --c:#7a869c; }

/* Pencarian */
.search-row{ margin:0 0 18px; }
.search-form{
  display:flex; gap:10px; flex-wrap:wrap; align-items:center;
  background:#f8f9fd; border:1px solid var(--line); border-radius:16px; padding:10px;
}
.search-input{ position:relative; flex:1 1 260px; min-width:220px; }
.search-input svg{
  position:absolute; left:14px; top:50%; transform:translateY(-50%);
  color:var(--muted); pointer-events:none;
}
.search-input input{
  width:100%; box-sizing:border-box; padding:12px 14px 12px 42px;
  border:1px solid var(--line); border-radius:12px; background:#fff;
  font-size:.95rem; color:var(--txt); outline:none; transition:.18s;
}
.search-input input:focus{ border-color:var(--pri); box-shadow:0 0 0 4px rgba(35,71,197,.15); }
.search-form .btn{
  background:var(--pri); color:#fff; border:0; border-radius:12px;
  padding:12px 22px; font-weight:600; cursor:pointer; transition:.18s;
}
.search-form .btn:hover{ background:var(--pri-d); }
.btn-reset{
  border:1px solid var(--line); background:#fff; color:var(--muted);
  border-radius:12px; padding:12px 18px; text-decoration:none; font-weight:600; transition:.18s;
}
.btn-reset:hover{ color:var(--bad); border-color:var(--bad); }
.chip-cari{
  display:inline-flex; align-items:center; gap:8px; margin:12px 0 0;
  background:#eaf0ff; color:var(--pri-d); border-radius:99px;
  padding:6px 14px; font-size:.83rem; font-weight:600;
}

/* Tabel */
.table-wrap{
  border:1px solid var(--line); border-radius:16px; overflow:auto;
  max-height:62vh; background:#fff;
}
table.lebar{ width:100%; border-collapse:separate; border-spacing:0; font-size:.9rem; }
table.lebar thead th{
  position:sticky; top:0; z-index:2; background:#f7f8fc; color:var(--muted);
  font-size:.75rem; text-transform:uppercase; letter-spacing:.4px; font-weight:700;
  padding:13px 14px; text-align:left; border-bottom:1px solid var(--line); white-space:nowrap;
}
table.lebar tbody td{ padding:12px 14px; border-bottom:1px solid #f0f2f8; vertical-align:middle; }
table.lebar tbody tr:nth-child(even){ background:#fbfcfe; }
table.lebar tbody tr:hover{ background:#f2f6ff; }
table.lebar tbody tr:last-child td{ border-bottom:0; }
td.wrap{ max-width:220px; white-space:normal; }
td.empty{ text-align:center; color:var(--muted); padding:40px 16px; }
td.empty a{ color:var(--pri); }
.aksi{ text-align:center; }
.icon-btn.edit{
  border:1px solid var(--line); background:#fff; color:var(--pri);
  width:34px; height:34px; border-radius:10px; cursor:pointer;
  display:inline-grid; place-items:center; transition:.18s;
}
.icon-btn.edit:hover{ background:var(--pri); color:#fff; border-color:var(--pri); }

.bawah-tabel{
  display:flex; justify-content:flex-end; align-items:center;
  margin-top:14px; color:var(--muted); font-size:.88rem;
}
.bawah-tabel .menampilkan strong{ color:var(--txt); }
@media (max-width:640px){
  main.full section{ padding:16px; }
  .stat-value{ font-size:1.35rem; }
}
</style>
</head>
<body class="reg-body">
<header>
  <h1>Data Mahasiswa</h1>
  <nav><a href="index.php">Pendaftaran</a><a href="data.php" aria-current="page">Data Mahasiswa</a><a href="login.php">Login</a></nav>
</header>
<main class="full">
<section>

  <div class="page-head">
    <div>
      <h2>Data Pendaftar</h2>
      <p class="sub">Kelola dan pantau seluruh data pendaftar mahasiswa baru.</p>
    </div>
    <a class="btn" href="index.php">+ Daftar mahasiswa baru</a>
  </div>

  <?php if ($flash): ?><div class="alert <?= e($flash_type) ?>"><?= e($flash) ?></div><?php endif; ?>

  <!-- Ringkasan -->
  <div class="stat-grid">
    <div class="stat-card c-semua">
      <div class="stat-ico">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div class="stat-body">
        <span class="stat-label">Jumlah Pendaftar</span>
        <span class="stat-value"><?= $jumlah['semua'] ?></span>
        <span class="stat-meta">Total seluruh data</span>
      </div>
    </div>

    <div class="stat-card c-lulus">
      <div class="stat-ico">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <div class="stat-body">
        <span class="stat-label">Peserta Lulus</span>
        <span class="stat-value"><?= $jumlah['lulus'] ?></span>
        <span class="stat-meta"><?= pct($jumlah['lulus'], $jumlah['semua']) ?>% dari pendaftar</span>
        <span class="stat-bar"><i style="width:<?= pct($jumlah['lulus'], $jumlah['semua']) ?>%"></i></span>
      </div>
    </div>

    <div class="stat-card c-cadang">
      <div class="stat-ico">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div class="stat-body">
        <span class="stat-label">Peserta Cadangan</span>
        <span class="stat-value"><?= $jumlah['cadangan'] ?></span>
        <span class="stat-meta"><?= pct($jumlah['cadangan'], $jumlah['semua']) ?>% dari pendaftar</span>
        <span class="stat-bar"><i style="width:<?= pct($jumlah['cadangan'], $jumlah['semua']) ?>%"></i></span>
      </div>
    </div>

    <div class="stat-card c-tidak">
      <div class="stat-ico">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      </div>
      <div class="stat-body">
        <span class="stat-label">Tidak Lulus</span>
        <span class="stat-value"><?= $jumlah['tidak'] ?></span>
        <span class="stat-meta"><?= pct($jumlah['tidak'], $jumlah['semua']) ?>% dari pendaftar</span>
        <span class="stat-bar"><i style="width:<?= pct($jumlah['tidak'], $jumlah['semua']) ?>%"></i></span>
      </div>
    </div>

    <?php if ($jumlah['belum'] > 0): ?>
    <div class="stat-card c-belum">
      <div class="stat-ico">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      </div>
      <div class="stat-body">
        <span class="stat-label">Belum Ada Nilai</span>
        <span class="stat-value"><?= $jumlah['belum'] ?></span>
        <span class="stat-meta">Menunggu input nilai</span>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Pencarian: HANYA Kode Daftar, NIM, Nama -->
  <div class="search-row">
    <form method="get" action="data.php" class="search-form">
      <div class="search-input">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="search" name="cari" placeholder="Cari berdasarkan Kode Daftar, NIM, atau Nama..." value="<?= e($cari) ?>" aria-label="Cari data" autocomplete="off">
      </div>
      <button type="submit" class="btn">Cari</button>
      <?php if ($cari !== ''): ?><a href="data.php" class="btn-reset">Reset</a><?php endif; ?>
    </form>
    <?php if ($cari !== ''): ?>
      <span class="chip-cari">Kata kunci: <?= e($cari) ?> &middot; <?= count($data_tampil) ?> hasil</span>
    <?php endif; ?>
  </div>

  <!-- Tabel -->
  <div class="table-wrap">
    <table class="lebar">
      <thead>
        <tr>
          <th>No</th><th>NIM</th><th>Kode Daftar</th><th>Nama Lengkap</th><th>Tahun Masuk</th>
          <th>Program Studi</th><th>Kelas</th><th>Smt</th><th>Tempat Lahir</th><th>Tgl Lahir</th>
          <th>Jenis Kelamin</th><th>Email</th><th>Telepon</th><th>Alamat</th><th>Asal Sekolah</th>
          <th>Pekerjaan Ortu</th><th>Matematika</th><th>B. Inggris</th><th>P. Umum</th>
          <th>Rata-rata</th><th>Keterangan</th><th class="aksi">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$data_tampil): ?>
        <tr>
          <td colspan="<?= $jumlah_kolom ?>" class="empty">
            <?php if ($cari !== ''): ?>
              Data dengan kata kunci "<strong><?= e($cari) ?></strong>" tidak ditemukan. <a href="data.php">Lihat semua data</a>.
            <?php else: ?>
              Belum ada data. Klik "Daftar mahasiswa baru" untuk menambah.
            <?php endif; ?>
          </td>
        </tr>
      <?php else: foreach ($data_tampil as $i => $m): $status = hitung_status($m); ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= isi($m, 'nim') ?></td>
          <td title="<?= e(keterangan_kode($m['kode'] ?? '')) ?>"><?= isi($m, 'kode') ?></td>
          <td><strong><?= e($m['nama']) ?></strong></td>
          <td><?= isi($m, 'tahun') ?></td>
          <td><?= isi($m, 'prodi') ?></td>
          <td><?= isi($m, 'reguler') ?></td>
          <td><?= e(semester_tampil($m)) ?></td>
          <td><?= isi($m, 'tempat') ?></td>
          <td><?= e(tgl_id($m['tgl'] ?? '')) ?></td>
          <td><?= isi($m, 'jk') ?></td>
          <td><?= isi($m, 'email') ?></td>
          <td><?= isi($m, 'hp') ?></td>
          <td class="wrap"><?= isi($m, 'alamat') ?></td>
          <td><?= isi($m, 'sekolah') ?></td>
          <td><?= isi($m, 'pekerjaan') ?></td>
          <td><?= isi($m, 'nilai_mat') ?></td>
          <td><?= isi($m, 'nilai_ing') ?></td>
          <td><?= isi($m, 'nilai_umum') ?></td>
          <td><strong><?= e(hitung_rata_nilai($m)) ?></strong></td>
          <td><?= badge_status($status) ?></td>
          <td class="aksi">
            <form method="post" action="data.php" class="icon-form">
              <input type="hidden" name="id" value="<?= e($m['id']) ?>">
              <button class="icon-btn edit" type="submit" name="aksi" value="edit" title="Edit" aria-label="Edit <?= e($m['nama']) ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="bawah-tabel">
    <div class="menampilkan">Menampilkan <strong><?= count($data_tampil) ?></strong> dari <strong><?= $jumlah['semua'] ?></strong> data</div>
  </div>

</section>
</main>
</body>
</html>