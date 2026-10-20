<?php
require 'config.php';

// Kode program studi (dipakai membentuk NIM). Silakan sesuaikan.
$daftar_prodi = daftar_prodi(); // daftar & kode prodi ada di config.php

// Opsi Kelas Reguler
$daftar_reguler = [
    'Reguler A (Pagi)',
    'Reguler B (Malam)',
    'Reguler CK (Karyawan Kamis)',
    'Reguler CS (Karyawan Sabtu)'
];

$tahun_ini = (int)date('Y');
$daftar_tahun = range($tahun_ini, $tahun_ini - 7); // 8 tahun terakhir

// Nama field untuk pesan error (dipakai validasi & ringkasan error)
$label = [
    'nama'=>'Nama lengkap','tahun'=>'Tahun masuk','prodi'=>'Program studi',
    'reguler'=>'Kelas reguler','tempat'=>'Tempat lahir','tgl'=>'Tanggal lahir',
    'jk'=>'Jenis kelamin','email'=>'Email','hp'=>'No. HP','alamat'=>'Alamat',
    'kode'=>'Kode pendaftaran','sekolah'=>'Asal sekolah','pekerjaan'=>'Pekerjaan orang tua',
    'nilai_mat'=>'Nilai Matematika','nilai_ing'=>'Nilai Bahasa Inggris','nilai_umum'=>'Nilai Pelajaran Umum'
];

$errors = [];
$old = ['kode'=>'','nama'=>'','tahun'=>'','prodi'=>'','reguler'=>'','tempat'=>'','tgl'=>'','jk'=>'','email'=>'','hp'=>'','alamat'=>'',
        'sekolah'=>'','pekerjaan'=>'','nilai_mat'=>'','nilai_ing'=>'','nilai_umum'=>''];
$id_lama = ''; // terisi hanya saat mode edit

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'batal') {
        unset($_SESSION['edit_id']);
        header('Location: data.php'); exit;
    }

    if ($aksi === 'hapus') {
        $id = $_POST['id_lama'] ?? '';
        [$idx, $rec] = cari_berdasarkan_id($id);
        if ($idx !== null) {
            if (!empty($rec['nim']) && ($_SESSION['login'] ?? '') === $rec['nim']) unset($_SESSION['login']);
            $_SESSION['mahasiswa'] = array_values(array_filter(
                $_SESSION['mahasiswa'], fn($m) => $m['id'] !== $id
            ));
            simpan_data_ke_json($_SESSION['mahasiswa']);
        }
        unset($_SESSION['edit_id']);
        $_SESSION['flash'] = 'Data berhasil dihapus.';
        $_SESSION['flash_type'] = 'ok';
        header('Location: data.php'); exit;
    }

    if ($aksi === 'simpan') {
        $id_lama = trim($_POST['id_lama'] ?? '');
        foreach ($old as $k => $_) { $old[$k] = trim($_POST[$k] ?? ''); }

        // ---- VALIDASI: semua field wajib diisi ----
        foreach ($label as $k => $nama_field) {
            if ($old[$k] === '') $errors[$k] = $nama_field . ' wajib diisi.';
        }

        // ---- VALIDASI: format & duplikat ----
        if (!isset($errors['nama'])) {
            if (mb_strlen($old['nama']) < 3) $errors['nama'] = 'Nama minimal 3 karakter.';
            elseif (cari_berdasarkan_nama($old['nama'], $id_lama)) $errors['nama'] = 'Nama ini sudah terdaftar.';
        }
        if (!isset($errors['kode'])) {
            if (uraikan_kode($old['kode']) === null) $errors['kode'] = 'Format kode salah. Contoh: A2-101-9 (Gedung A, Gelombang 2, bulan September).';
            elseif (kode_sudah_ada($old['kode'], $id_lama)) $errors['kode'] = 'Kode pendaftaran ini sudah dipakai.';
        }
        if (!isset($errors['sekolah']) && mb_strlen($old['sekolah']) < 3) $errors['sekolah'] = 'Asal sekolah minimal 3 karakter.';
        if (!isset($errors['pekerjaan']) && mb_strlen($old['pekerjaan']) < 3) $errors['pekerjaan'] = 'Pekerjaan orang tua minimal 3 karakter.';
        foreach (['nilai_mat','nilai_ing','nilai_umum'] as $k) {
            if (isset($errors[$k])) continue;
            $v = str_replace(',', '.', $old[$k]);
            if (!is_numeric($v) || $v < 0 || $v > 100) $errors[$k] = 'Isi angka 0–100.';
        }
        if (!isset($errors['tahun']) && !in_array((int)$old['tahun'], $daftar_tahun, true)) $errors['tahun'] = 'Tahun masuk tidak valid.';
        if (!isset($errors['prodi']) && !isset($daftar_prodi[$old['prodi']])) $errors['prodi'] = 'Program studi tidak valid.';
        if (!isset($errors['reguler']) && !in_array($old['reguler'], $daftar_reguler, true)) $errors['reguler'] = 'Pilih kelas reguler yang valid.';
        if (!isset($errors['email']) && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Format email tidak valid.';
        if (!isset($errors['hp']) && !preg_match('/^(08|\+628)\d{8,11}$/', $old['hp'])) $errors['hp'] = 'Gunakan format 08xxxxxxxxxx.';
        if (!isset($errors['jk']) && !in_array($old['jk'], ['Laki-laki','Perempuan'], true)) $errors['jk'] = 'Jenis kelamin tidak valid.';

        if (!$errors) {
            $data = $old;
            $data['kode'] = strtoupper($old['kode']);
            foreach (['nilai_mat','nilai_ing','nilai_umum'] as $k) {
                $data[$k] = round((float)str_replace(',', '.', $old[$k]), 2);
            }
            $kode   = $daftar_prodi[$old['prodi']];
            $rata   = hitung_rata_nilai($data);
            $status = hitung_status($data);   // Lulus / Cadangan / Tidak Lulus
            $lulus  = ($status === 'Lulus');
            $tipe   = $lulus ? 'ok' : ($status === 'Cadangan' ? 'warn' : 'err');

            if ($id_lama !== '') {
                // ===== MODE EDIT =====
                [$idx, $rec] = cari_berdasarkan_id($id_lama);
                if ($idx !== null) {
                    $data['id'] = $rec['id'];
                    $nim_lama = $rec['nim'] ?? '';

                    if ($lulus) {
                        // NIM tetap jika sudah ada dan tahun & prodi tidak berubah
                        $sama = $nim_lama !== '' && ($rec['tahun'] ?? '') === $old['tahun'] && ($rec['prodi'] ?? '') === $old['prodi'];
                        $data['nim'] = $sama ? $nim_lama : buat_nim($old['tahun'], $kode, $id_lama);
                        $data['password'] = password_hash($data['nim'], PASSWORD_DEFAULT);
                        if ($nim_lama !== '' && ($_SESSION['login'] ?? '') === $nim_lama) $_SESSION['login'] = $data['nim'];
                        $_SESSION['flash'] = $sama
                            ? "Data diperbarui. Hasil tes: LULUS (rata-rata $rata)."
                            : "Data diperbarui. Hasil tes: LULUS (rata-rata $rata). NIM: " . $data['nim'];
                    } else {
                        // Tidak lulus / cadangan: tanpa NIM & tanpa password
                        if ($nim_lama !== '' && ($_SESSION['login'] ?? '') === $nim_lama) unset($_SESSION['login']);
                        $_SESSION['flash'] = 'Data diperbarui. Hasil tes: ' . strtoupper($status) . " (rata-rata $rata). NIM tidak dibuat"
                            . ($nim_lama !== '' ? ' dan NIM sebelumnya dihapus.' : '.');
                    }
                    $_SESSION['mahasiswa'][$idx] = $data;
                    simpan_data_ke_json($_SESSION['mahasiswa']);
                    $_SESSION['flash_type'] = $tipe;
                }
                unset($_SESSION['edit_id']);
                header('Location: data.php'); exit;
            }

            // ===== MODE TAMBAH =====
            $data['id'] = bin2hex(random_bytes(6));
            if ($lulus) {
                $data['nim'] = buat_nim($old['tahun'], $kode);
                $data['password'] = password_hash($data['nim'], PASSWORD_DEFAULT);
                $_SESSION['flash'] = "Selamat, kamu LULUS (rata-rata $rata). NIM kamu: " . $data['nim'] . '. Login dengan username = nama lengkap, password = NIM.';
            } elseif ($status === 'Cadangan') {
                $_SESSION['flash'] = "Hasil tes: CADANGAN (rata-rata $rata). NIM belum dibuat.";
            } else {
                $_SESSION['flash'] = "Hasil tes: TIDAK LULUS (rata-rata $rata). NIM tidak dibuat.";
            }
            $_SESSION['flash_type'] = $tipe;
            $_SESSION['mahasiswa'][] = $data;
            simpan_data_ke_json($_SESSION['mahasiswa']);
            header('Location: index.php'); exit;
        }
    }
} elseif (isset($_SESSION['edit_id'])) {
    [$idx, $m] = cari_berdasarkan_id($_SESSION['edit_id']);
    if ($m) {
        $id_lama = $m['id'];
        foreach ($old as $k => $_) { $old[$k] = $m[$k] ?? ''; }
    } else {
        unset($_SESSION['edit_id']);
    }
}

$mode_edit = $id_lama !== '';
$nim_tampil = '';
if ($mode_edit) {
    [, $rec_tampil] = cari_berdasarkan_id($id_lama);
    $nim_tampil = $rec_tampil['nim'] ?? '';
}

$flash = $_SESSION['flash'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'ok';
unset($_SESSION['flash'], $_SESSION['flash_type']);

function field_error($errors, $k) {
    return '<div class="error" id="err-'.$k.'">' . (isset($errors[$k]) ? e($errors[$k]) : '') . '</div>';
}
function cls($errors, $k) {
    return isset($errors[$k]) ? ' class="invalid" aria-invalid="true" aria-describedby="err-'.$k.'"' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pendaftaran Mahasiswa</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="form.css">
</head>
<body class="reg-body">
<header>
  <h1>Pendaftaran Mahasiswa Baru</h1>
  <nav><a href="index.php" aria-current="page">Pendaftaran</a><a href="data.php">Data Mahasiswa</a><a href="login.php">Login</a></nav>
</header>

<main class="reg">
  <div class="reg-head">
    <h2><?= $mode_edit ? 'Edit data mahasiswa' : 'Formulir pendaftaran' ?></h2>
    <p>Semua isian wajib diisi. Kelengkapan dan hasil seleksi dihitung otomatis saat kamu mengetik.</p>
  </div>

  <?php if ($flash): ?><div class="alert <?= e($flash_type) ?>" role="status"><?= e($flash) ?></div><?php endif; ?>

  <?php if ($errors): ?>
    <div class="reg-alert" role="alert" tabindex="-1" id="ringkasanError">
      <strong><?= count($errors) ?> isian perlu diperbaiki sebelum data bisa disimpan:</strong>
      <ul>
        <?php foreach ($errors as $k => $msg): ?>
          <li><a href="#<?= e($k) ?>"><?= e($label[$k] ?? $k) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" action="index.php" novalidate id="formDaftar">
    <input type="hidden" name="id_lama" value="<?= e($id_lama) ?>">

    <div class="reg-layout">
      <div class="reg-sheet">

        <!-- Bagian 1: kode & program -->
        <div class="sec">
          <div>
            <h3>Kode dan program studi</h3>
            <p class="sec-note">Kode ada di kartu tes kamu. Program studi menentukan NIM.</p>
          </div>
          <div class="fields">
            <?php if ($mode_edit): ?>
              <div class="field">
                <label for="nim">NIM</label>
                <input id="nim" value="<?= e($nim_tampil !== '' ? $nim_tampil : 'Belum ada (belum lulus)') ?>" readonly>
              </div>
            <?php endif; ?>

            <div class="field">
              <label for="kode">Kode pendaftaran</label>
              <input id="kode" name="kode" maxlength="8" placeholder="A2-101-9" autocomplete="off" autocapitalize="characters" spellcheck="false" value="<?= e($old['kode']) ?>"<?= cls($errors,'kode') ?>>
              <?= field_error($errors,'kode') ?>
              <p class="help">Contoh A2-101-9. Tanda hubung ditambahkan otomatis.</p>
            </div>

            <div class="row2">
              <div class="field">
                <label for="tahun">Tahun masuk</label>
                <select id="tahun" name="tahun"<?= cls($errors,'tahun') ?>>
                  <option value="">Pilih tahun</option>
                  <?php foreach ($daftar_tahun as $t): ?>
                    <option value="<?= $t ?>" <?= $old['tahun']===(string)$t?'selected':'' ?>><?= $t ?></option>
                  <?php endforeach; ?>
                </select>
                <?= field_error($errors,'tahun') ?>
              </div>
              <div class="field">
                <label for="reguler">Kelas reguler</label>
                <select id="reguler" name="reguler"<?= cls($errors,'reguler') ?>>
                  <option value="">Pilih kelas</option>
                  <?php foreach ($daftar_reguler as $r): ?>
                    <option <?= $old['reguler']===$r?'selected':'' ?>><?= e($r) ?></option>
                  <?php endforeach; ?>
                </select>
                <?= field_error($errors,'reguler') ?>
              </div>
            </div>

            <div class="field">
              <label for="prodi">Program studi</label>
              <select id="prodi" name="prodi"<?= cls($errors,'prodi') ?>>
                <option value="">Pilih program studi</option>
                <?php foreach ($daftar_prodi as $p => $kode_p): ?>
                  <option <?= $old['prodi']===$p?'selected':'' ?>><?= e($p) ?></option>
                <?php endforeach; ?>
              </select>
              <?= field_error($errors,'prodi') ?>
              <p class="help">
                <?= $mode_edit
                  ? 'NIM berubah otomatis jika tahun masuk atau program studi diganti.'
                  : 'NIM dibuat otomatis dari tahun masuk dan program studi, hanya jika hasil tes Lulus.' ?>
              </p>
            </div>
          </div>
        </div>

        <!-- Bagian 2: data diri -->
        <div class="sec">
          <div>
            <h3>Data diri</h3>
            <p class="sec-note">Isi sesuai KTP atau akta kelahiran.</p>
          </div>
          <div class="fields">
            <div class="field">
              <label for="nama">Nama lengkap</label>
              <input id="nama" name="nama" autocomplete="name" value="<?= e($old['nama']) ?>"<?= cls($errors,'nama') ?>>
              <?= field_error($errors,'nama') ?>
              <p class="help">Nama ini jadi username saat login.</p>
            </div>

            <div class="row2">
              <div class="field">
                <label for="tempat">Tempat lahir</label>
                <input id="tempat" name="tempat" placeholder="Tangerang" value="<?= e($old['tempat']) ?>"<?= cls($errors,'tempat') ?>>
                <?= field_error($errors,'tempat') ?>
              </div>
              <div class="field">
                <label for="tgl">Tanggal lahir</label>
                <input id="tgl" name="tgl" type="date" value="<?= e($old['tgl']) ?>"<?= cls($errors,'tgl') ?>>
                <?= field_error($errors,'tgl') ?>
              </div>
            </div>

            <div class="field" id="jk" role="radiogroup" aria-labelledby="jkLabel" tabindex="-1">
              <span class="label" id="jkLabel">Jenis kelamin</span>
              <div class="jk-row">
                <?php foreach (['Laki-laki','Perempuan'] as $jk): ?>
                  <label class="jk-item">
                    <input type="radio" name="jk" value="<?= $jk ?>" <?= $old['jk']===$jk?'checked':'' ?>>
                    <?= $jk ?>
                  </label>
                <?php endforeach; ?>
              </div>
              <?= field_error($errors,'jk') ?>
            </div>
          </div>
        </div>

        <!-- Bagian 3: kontak -->
        <div class="sec">
          <div>
            <h3>Kontak dan alamat</h3>
            <p class="sec-note">Dipakai panitia untuk menghubungi kamu.</p>
          </div>
          <div class="fields">
            <div class="row2">
              <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" autocomplete="email" placeholder="nama@gmail.com" value="<?= e($old['email']) ?>"<?= cls($errors,'email') ?>>
                <?= field_error($errors,'email') ?>
              </div>
              <div class="field">
                <label for="hp">No. HP</label>
                <input id="hp" name="hp" type="tel" inputmode="tel" autocomplete="tel" placeholder="08xxxxxxxxxx" value="<?= e($old['hp']) ?>"<?= cls($errors,'hp') ?>>
                <?= field_error($errors,'hp') ?>
              </div>
            </div>
            <div class="field">
              <label for="alamat">Alamat</label>
              <textarea id="alamat" name="alamat" autocomplete="street-address" placeholder="Jl. Merdeka No. 12, RT 03/RW 05, Pamulang, Tangerang Selatan"<?= cls($errors,'alamat') ?>><?= e($old['alamat']) ?></textarea>
              <?= field_error($errors,'alamat') ?>
            </div>
          </div>
        </div>

        <!-- Bagian 4: latar belakang -->
        <div class="sec">
          <div>
            <h3>Latar belakang</h3>
            <p class="sec-note">Pendidikan terakhir dan keluarga.</p>
          </div>
          <div class="fields">
            <div class="row2">
              <div class="field">
                <label for="sekolah">Asal sekolah</label>
                <input id="sekolah" name="sekolah" placeholder="SMAN 1 Pamulang" value="<?= e($old['sekolah']) ?>"<?= cls($errors,'sekolah') ?>>
                <?= field_error($errors,'sekolah') ?>
              </div>
              <div class="field">
                <label for="pekerjaan">Pekerjaan orang tua</label>
                <input id="pekerjaan" name="pekerjaan" placeholder="Wiraswasta" value="<?= e($old['pekerjaan']) ?>"<?= cls($errors,'pekerjaan') ?>>
                <?= field_error($errors,'pekerjaan') ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Bagian 5: nilai -->
        <div class="sec">
          <div>
            <h3>Nilai tes</h3>
            <p class="sec-note">Isi nilai tiap pelajaran dengan angka 0–100. Boleh pakai desimal.</p>
          </div>
          <div class="fields">
            <div class="row3">
              <div class="field">
                <label for="nilai_mat">Matematika</label>
                <input id="nilai_mat" name="nilai_mat" type="number" min="0" max="100" step="0.01" inputmode="decimal" placeholder="0–100" value="<?= e($old['nilai_mat']) ?>"<?= cls($errors,'nilai_mat') ?>>
                <?= field_error($errors,'nilai_mat') ?>
              </div>
              <div class="field">
                <label for="nilai_ing">Bahasa Inggris</label>
                <input id="nilai_ing" name="nilai_ing" type="number" min="0" max="100" step="0.01" inputmode="decimal" placeholder="0–100" value="<?= e($old['nilai_ing']) ?>"<?= cls($errors,'nilai_ing') ?>>
                <?= field_error($errors,'nilai_ing') ?>
              </div>
              <div class="field">
                <label for="nilai_umum">Pelajaran Umum</label>
                <input id="nilai_umum" name="nilai_umum" type="number" min="0" max="100" step="0.01" inputmode="decimal" placeholder="0–100" value="<?= e($old['nilai_umum']) ?>"<?= cls($errors,'nilai_umum') ?>>
                <?= field_error($errors,'nilai_umum') ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Tombol -->
        <div class="reg-actions">
          <?php if ($mode_edit): ?>
            <button type="submit" name="aksi" value="simpan" class="btn btn-primary">Simpan perubahan</button>
            <button type="submit" name="aksi" value="batal" class="btn btn-ghost">Batal</button>
            <button type="submit" name="aksi" value="hapus" class="btn btn-danger" onclick="return confirm('Hapus data mahasiswa ini?')">Hapus data</button>
          <?php else: ?>
            <button type="submit" name="aksi" value="simpan" class="btn btn-primary">Kirim pendaftaran</button>
            <a class="btn btn-ghost" href="index.php">Kosongkan isian</a>
          <?php endif; ?>
        </div>
        <p class="reg-foot">Jika dinyatakan Lulus, login memakai nama lengkap sebagai username dan NIM sebagai password.</p>
      </div>

      <!-- Panel samping: kelengkapan, arti kode, hasil seleksi -->
      <aside class="reg-side" aria-label="Ringkasan pendaftaran">
        <div class="side-progress">
          <div class="progress-top">
            <h3>Kelengkapan</h3>
            <span class="progress-count" id="progCount">0 dari 16 isian</span>
          </div>
          <div class="bar" id="progBar" role="progressbar" aria-valuemin="0" aria-valuemax="16" aria-valuenow="0"><i></i></div>
        </div>

        <div class="side-ticket">
          <h3>Arti kode pendaftaran</h3>
          <dl class="ticket">
            <div><dt>Gedung</dt><dd class="empty" id="kGedung">–</dd></div>
            <div><dt>Gelombang</dt><dd class="empty" id="kGel">–</dd></div>
            <div><dt>Nomor urut</dt><dd class="empty" id="kNo">–</dd></div>
            <div><dt>Bulan tes</dt><dd class="empty" id="kBulan">–</dd></div>
          </dl>
          <p class="ticket-msg" id="kodeMsg" aria-live="polite"></p>
        </div>

        <div class="side-result">
          <h3>Hasil seleksi</h3>
          <div class="score-row">
            <div class="score-num empty" id="rataNum">–</div>
            <span class="pill" id="statusPill">Belum lengkap</span>
          </div>
          <div class="meter" id="meter" aria-hidden="true">
            <span class="meter-marker" id="meterMarker"></span>
            <span class="meter-tick" style="left:0">0</span>
            <span class="meter-tick" style="left:60%">60</span>
            <span class="meter-tick" style="left:70%">70</span>
            <span class="meter-tick" style="left:100%">100</span>
          </div>
          <ul class="score-legend">
            <li><b>70 ke atas</b> Lulus dan mendapat NIM</li>
            <li><b>60 sampai 69,99</b> Cadangan</li>
            <li><b>Di bawah 60</b> Tidak lulus</li>
          </ul>
        </div>
      </aside>
    </div>
  </form>
</main>

<script>
(function () {
  var $ = function (id) { return document.getElementById(id); };
  var form = $('formDaftar');

  /* ---------- Kode pendaftaran: format otomatis + arti ---------- */
  var gedung = { A: 'Gedung A', B: 'Gedung B', V: 'Gedung Viktor' };
  var bulan = { '1':'Januari','2':'Februari','3':'Maret','4':'April','5':'Mei','6':'Juni','7':'Juli','8':'Agustus','9':'September','A':'Oktober','B':'November','C':'Desember' };
  var kode = $('kode');

  function setRow(id, text) {
    var el = $(id);
    el.textContent = text || '–';
    el.className = text ? '' : 'empty';
  }

  function formatKode() {
    var raw = kode.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
    var parts = [raw.slice(0, 2), raw.slice(2, 5), raw.slice(5, 6)].filter(Boolean);
    kode.value = parts.join('-');
    return raw;
  }

  function updateKode() {
    var raw = formatKode();
    var msg = $('kodeMsg');
    setRow('kGedung', gedung[raw.charAt(0)]);
    setRow('kGel', /^[1-9]$/.test(raw.charAt(1)) ? 'Gelombang ' + raw.charAt(1) : '');
    setRow('kNo', /^\d{3}$/.test(raw.slice(2, 5)) ? raw.slice(2, 5) : '');
    setRow('kBulan', bulan[raw.charAt(5)]);

    var ok = /^[ABV][1-9]\d{3}[1-9ABC]$/.test(raw);
    if (ok) { msg.textContent = 'Format kode sudah benar.'; msg.className = 'ticket-msg good'; }
    else if (raw.length === 6) { msg.textContent = 'Format belum sesuai. Contoh: A2-101-9'; msg.className = 'ticket-msg bad'; }
    else if (raw.length && !gedung[raw.charAt(0)]) { msg.textContent = 'Huruf pertama harus A, B, atau V.'; msg.className = 'ticket-msg bad'; }
    else { msg.textContent = raw.length ? '' : 'Ketik kode untuk melihat artinya.'; msg.className = 'ticket-msg'; }
  }
  kode.addEventListener('input', function () { updateKode(); updateProgress(); });

  /* ---------- Kelengkapan ---------- */
  var wajib = ['kode','nama','tahun','prodi','reguler','tempat','tgl','email','hp','alamat','sekolah','pekerjaan','nilai_mat','nilai_ing','nilai_umum'];
  function updateProgress() {
    var terisi = 0;
    wajib.forEach(function (id) { if ($(id).value.trim() !== '') terisi++; });
    if (form.querySelector('input[name="jk"]:checked')) terisi++;
    var total = wajib.length + 1;
    $('progCount').textContent = terisi + ' dari ' + total + ' isian';
    $('progBar').firstElementChild.style.width = (terisi / total * 100) + '%';
    $('progBar').setAttribute('aria-valuenow', terisi);
    $('progBar').className = 'bar' + (terisi === total ? ' done' : '');
  }

  /* ---------- Rata-rata & status ---------- */
  var nilaiIds = ['nilai_mat', 'nilai_ing', 'nilai_umum'];
  function updateNilai() {
    var total = 0, n = 0;
    nilaiIds.forEach(function (id) {
      var v = parseFloat(String($(id).value).replace(',', '.'));
      if (!isNaN(v) && v >= 0 && v <= 100) { total += v; n++; }
    });
    var num = $('rataNum'), pill = $('statusPill'), meter = $('meter');
    if (n < 3) {
      num.textContent = '–'; num.className = 'score-num empty';
      pill.textContent = 'Belum lengkap'; pill.className = 'pill';
      meter.className = 'meter';
      return;
    }
    var rata = Math.round(total / 3 * 100) / 100;
    var st = rata >= 70 ? ['Lulus', 'ok'] : (rata >= 60 ? ['Cadangan', 'warn'] : ['Tidak lulus', 'err']);
    num.textContent = String(rata).replace('.', ',');
    num.className = 'score-num';
    pill.textContent = st[0]; pill.className = 'pill ' + st[1];
    meter.className = 'meter has';
    $('meterMarker').style.left = Math.min(100, Math.max(0, rata)) + '%';
  }
  nilaiIds.forEach(function (id) { $(id).addEventListener('input', updateNilai); });

  /* ---------- Hapus pesan error saat isian diperbaiki ---------- */
  function bersihkanError(e) {
    var field = e.target.closest('.field');
    if (!field) return;
    field.querySelectorAll('.invalid').forEach(function (el) {
      el.classList.remove('invalid');
      el.removeAttribute('aria-invalid');
    });
    var err = field.querySelector('.error');
    if (err) err.textContent = '';
  }
  form.addEventListener('input', function (e) { bersihkanError(e); updateProgress(); });
  form.addEventListener('change', function (e) { bersihkanError(e); updateProgress(); });

  /* ---------- Ringkasan error: klik langsung ke isian ---------- */
  document.querySelectorAll('.reg-alert a').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var target = document.getElementById(a.getAttribute('href').slice(1));
      if (!target) return;
      e.preventDefault();
      target.scrollIntoView({ block: 'center', behavior: 'smooth' });
      var fokus = target.matches('input,select,textarea') ? target : target.querySelector('input');
      if (fokus) fokus.focus({ preventScroll: true });
    });
  });
  var ringkasan = $('ringkasanError');
  if (ringkasan) ringkasan.focus();

  updateKode();
  updateProgress();
  updateNilai();
})();
</script>
</body>
</html>