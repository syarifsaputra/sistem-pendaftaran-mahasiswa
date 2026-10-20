<?php
// File ini di-include di semua halaman: memulai session & fungsi bantu.
session_start();

// ==========================================
// OPSI B: BACKUP DATA KE FILE JSON (Tanpa Database)
// ==========================================
$file_data_json = __DIR__ . '/data_mahasiswa.json';

function load_data_dari_json() {
    global $file_data_json;
    if (!file_exists($file_data_json)) return [];

    $isi = file_get_contents($file_data_json);
    if (trim($isi) === '') return [];

    $data = json_decode($isi, true);
    if (!is_array($data)) {
        // File JSON rusak (misalnya salah ketik saat diedit manual).
        // Berhenti di sini supaya data lama tidak tertimpa data kosong.
        http_response_code(500);
        echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Data tidak terbaca</title>'
           . '<link rel="stylesheet" href="style.css"><link rel="stylesheet" href="form.css"></head><body class="reg-body"><main class="reg"><section>'
           . '<h2>File data tidak valid</h2>'
           . '<div class="alert err">data_mahasiswa.json tidak bisa dibaca: ' . htmlspecialchars(json_last_error_msg()) . '.</div>'
           . '<p>Periksa tanda koma, kurung, dan tanda kutip di file tersebut, lalu muat ulang halaman ini. Data tidak diubah.</p>'
           . '</section></main></body></html>';
        exit;
    }
    return array_values($data);
}

function simpan_data_ke_json($data) {
    global $file_data_json;
    // LOCK_EX mencegah konflik jika ada proses tulis bersamaan
    file_put_contents(
        $file_data_json,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

// JSON adalah sumber data utama: dibaca ulang SETIAP halaman dimuat,
// jadi perubahan manual pada data_mahasiswa.json langsung terlihat di semua halaman.
$_SESSION['mahasiswa'] = load_data_dari_json();

// Setiap data punya 'id' unik (karena peserta yang tidak lulus tidak punya NIM).
// Data lama yang belum punya id memakai NIM-nya sebagai id.
foreach ($_SESSION['mahasiswa'] as $i => $m) {
    if (empty($m['id'])) {
        $_SESSION['mahasiswa'][$i]['id'] = $m['nim'] ?? md5(($m['kode'] ?? '') . ($m['nama'] ?? ''));
    }
}
// ==========================================

function e($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

// $kecuali_id = id data yang diabaikan (dipakai saat edit, supaya data sendiri tidak dianggap kembar)
function cari_berdasarkan_nama($nama, $kecuali_id = '') {
    foreach ($_SESSION['mahasiswa'] as $m) {
        if ($m['id'] === $kecuali_id) continue;
        if (mb_strtolower($m['nama']) === mb_strtolower(trim($nama))) {
            return $m;
        }
    }
    return null;
}

function nim_sudah_ada($nim, $kecuali_id = '') {
    foreach ($_SESSION['mahasiswa'] as $m) {
        if ($m['id'] === $kecuali_id) continue;
        if (($m['nim'] ?? '') === $nim) return true;
    }
    return false;
}

function kode_sudah_ada($kode, $kecuali_id = '') {
    foreach ($_SESSION['mahasiswa'] as $m) {
        if ($m['id'] === $kecuali_id) continue;
        if (isset($m['kode']) && strcasecmp($m['kode'], $kode) === 0) return true;
    }
    return false;
}

function cari_berdasarkan_id($id) {
    foreach ($_SESSION['mahasiswa'] as $i => $m) {
        if ($m['id'] === $id) return [$i, $m];
    }
    return [null, null];
}

// Membuat NIM 12 digit otomatis (hanya dipanggil untuk peserta yang LULUS):
function buat_nim($tahun, $kode_prodi, $kecuali_id = '') {
    $awalan = substr((string)$tahun, -2) . $kode_prodi; // 6 digit
    for ($urut = 1; $urut <= 999999; $urut++) {
        $nim = $awalan . str_pad((string)$urut, 6, '0', STR_PAD_LEFT);
        if (!nim_sudah_ada($nim, $kecuali_id)) return $nim;
    }
    return null;
}

// Menghitung semester berjalan dari tahun masuk.
function hitung_semester($tahun_masuk) {
    if (!ctype_digit((string)$tahun_masuk)) return '-';
    $selisih_bulan = ((int)date('Y') - (int)$tahun_masuk) * 12 + ((int)date('n') - 9);
    $semester = (int)floor($selisih_bulan / 6) + 1;
    return max(1, min(14, $semester));
}

// Semester hanya ditampilkan untuk yang punya NIM (sudah jadi mahasiswa).
function semester_tampil($m) {
    return !empty($m['nim']) ? hitung_semester($m['tahun'] ?? '') : '-';
}

// Rata-rata dari 3 nilai tes (Matematika, Bahasa Inggris, Pelajaran Umum).
function hitung_rata_nilai($m) {
    foreach (['nilai_mat', 'nilai_ing', 'nilai_umum'] as $k) {
        if (!isset($m[$k]) || !is_numeric($m[$k])) return '-';
    }
    return round(($m['nilai_mat'] + $m['nilai_ing'] + $m['nilai_umum']) / 3, 2);
}

// ATURAN SELEKSI:
//   rata-rata >= 70        -> Lulus (NIM dibuat)
//   rata-rata 60 - <70     -> Cadangan (NIM belum dibuat)
//   rata-rata < 60         -> Tidak Lulus (NIM tidak dibuat)
function hitung_status($m) {
    $rata = hitung_rata_nilai($m);
    if (!is_numeric($rata)) return '-';
    if ($rata >= 70) return 'Lulus';
    if ($rata >= 60) return 'Cadangan';
    return 'Tidak Lulus';
}

function badge_status($status) {
    $kelas = ['Lulus' => 'lulus', 'Cadangan' => 'cadangan', 'Tidak Lulus' => 'tidak'][$status] ?? 'netral';
    return '<span class="badge ' . $kelas . '">' . e($status) . '</span>';
}

// ==========================================
// ATURAN KODE PENDAFTARAN
// Format: [Gedung][Gelombang]-[3 angka]-[Bulan tes]
// Contoh: A2-101-9  => Gedung A, Gelombang 2, bulan September
// ==========================================
function daftar_gedung() {
    return ['A' => 'Gedung A', 'B' => 'Gedung B', 'V' => 'Gedung Viktor'];
}

// Bulan tes ditulis 1 karakter: 1-9 = Januari-September, A = Oktober, B = November, C = Desember
function daftar_bulan_tes() {
    return [
        '1' => 'Januari', '2' => 'Februari', '3' => 'Maret', '4' => 'April',
        '5' => 'Mei', '6' => 'Juni', '7' => 'Juli', '8' => 'Agustus', '9' => 'September',
        'A' => 'Oktober', 'B' => 'November', 'C' => 'Desember',
    ];
}

// Menguraikan kode; mengembalikan null jika format salah.
function uraikan_kode($kode) {
    if (!preg_match('/^([ABV])([1-9])-(\d{3})-([1-9ABC])$/', strtoupper(trim((string)$kode)), $mm)) {
        return null;
    }
    return [
        'gedung'    => daftar_gedung()[$mm[1]],
        'gelombang' => (int)$mm[2],
        'nomor'     => $mm[3],
        'bulan'     => daftar_bulan_tes()[$mm[4]],
    ];
}

function keterangan_kode($kode) {
    $u = uraikan_kode($kode);
    return $u ? $u['gedung'] . ' – Gelombang ' . $u['gelombang'] . ' – Bulan tes ' . $u['bulan'] : '-';
}


// ==========================================
// DAFTAR PRODI & KODE (dipakai membentuk NIM)
// ==========================================
function daftar_prodi() {
    return [
        'Teknik Informatika' => '1011',
        'Sistem Informasi'   => '1021',
        'Teknik Elektro'     => '1031',
        'Manajemen'          => '2011',
        'Akuntansi'          => '2021',
        'Hukum'              => '3011',
        'Ilmu Komunikasi'    => '4011',
    ];
}

// ==========================================
// SINKRONISASI SETELAH JSON DIEDIT MANUAL
// Status (Lulus/Cadangan/Tidak Lulus) selalu dihitung dari nilai, jadi ikut berubah.
// Fungsi ini menyesuaikan NIM & password dengan status terbaru:
//   - Lulus tapi belum punya NIM  -> NIM dibuat otomatis
//   - Lulus tapi password kosong  -> password dibuat dari NIM
//   - Cadangan / Tidak Lulus      -> NIM & password dihapus
// Data yang belum punya nilai tidak disentuh.
// ==========================================
function sinkronkan_data() {
    $ubah = false;
    foreach ($_SESSION['mahasiswa'] as $i => $m) {
        $status = hitung_status($m);
        if ($status === '-') continue;

        if ($status === 'Lulus') {
            if (empty($m['nim'])) {
                $kode_prodi = daftar_prodi()[$m['prodi'] ?? ''] ?? null;
                if ($kode_prodi === null || !ctype_digit((string)($m['tahun'] ?? ''))) continue; // data belum lengkap
                $_SESSION['mahasiswa'][$i]['nim'] = buat_nim($m['tahun'], $kode_prodi, $m['id']);
                $ubah = true;
            }
            if (empty($_SESSION['mahasiswa'][$i]['password'])) {
                $_SESSION['mahasiswa'][$i]['password'] = password_hash($_SESSION['mahasiswa'][$i]['nim'], PASSWORD_DEFAULT);
                $ubah = true;
            }
        } elseif (!empty($m['nim']) || isset($m['password'])) {
            unset($_SESSION['mahasiswa'][$i]['nim'], $_SESSION['mahasiswa'][$i]['password']);
            $ubah = true;
        }
    }
    if ($ubah) simpan_data_ke_json($_SESSION['mahasiswa']);
}

sinkronkan_data();