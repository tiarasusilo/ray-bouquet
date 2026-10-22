<?php

session_start();

require_once 'koneksi.php';
require_once 'auth.php';

require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;


/* =========================
   CEK LOGIN
========================= */

if (!isset($_SESSION['tipe_akun'])) {
    header('Location: login.php');
    exit;
}

if (!hasPrivilege('view_laporan')) {
    die('Anda tidak memiliki akses.');
}


/* =========================
   PERIODE
========================= */

$bulan = isset($_GET['bulan']) ? (int) $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : date('Y');

$namaBulan = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];

$namaPeriode = $namaBulan[$bulan] . ' ' . $tahun;

$periode = sprintf('%04d-%02d', $tahun, $bulan);


/* =========================
   DATA KEUANGAN
========================= */

$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(
            CASE
                WHEN jenis = 'Pemasukan'
                THEN nominal
                ELSE 0
            END
        ), 0) AS pemasukan,

        COALESCE(SUM(
            CASE
                WHEN jenis = 'Pengeluaran'
                THEN nominal
                ELSE 0
            END
        ), 0) AS pengeluaran,

        COUNT(*) AS jumlah_transaksi

    FROM keuangan

    WHERE status = 'Disetujui'
      AND DATE_FORMAT(tanggal, '%Y-%m') = ?
");

$stmt->execute([$periode]);

$keuangan = $stmt->fetch();

$pemasukan = (float) $keuangan['pemasukan'];
$pengeluaran = (float) $keuangan['pengeluaran'];
$saldo = $pemasukan - $pengeluaran;
$jumlahTransaksi = (int) $keuangan['jumlah_transaksi'];


/* =========================
   INVENTORY
========================= */

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total_item,
        COALESCE(SUM(jumlah_stok), 0) AS total_stok,
        COALESCE(SUM(
            CASE
                WHEN jumlah_stok <= stok_minimum THEN 1
                ELSE 0
            END
        ), 0) AS stok_menipis
    FROM inventory
");

$inventory = $stmt->fetch();


/* =========================
   TRANSAKSI
========================= */

$stmt = $pdo->prepare("
    SELECT
        tanggal,
        jenis,
        kategori,
        keterangan,
        nominal
    FROM keuangan
    WHERE status = 'Disetujui'
      AND DATE_FORMAT(tanggal, '%Y-%m') = ?
    ORDER BY tanggal ASC
");

$stmt->execute([$periode]);

$transaksi = $stmt->fetchAll();


/* =========================
   BUAT FOLDER
========================= */

$folder = __DIR__ . '/uploads/dokumen';

if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}


$namaFile = 'laporan_' . $tahun . '_' . str_pad($bulan, 2, '0', STR_PAD_LEFT) . '_' . time() . '.pdf';

$pathFile = $folder . '/' . $namaFile;

$relativePath = 'uploads/dokumen/' . $namaFile;


/* =========================
   HTML PDF
========================= */

$html = '

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<style>

@page {
    margin: 35px 40px;
}

body {
    font-family: DejaVu Sans, sans-serif;
    color: #4b403b;
    font-size: 11px;
}

.header {
    border-bottom: 3px solid #640D14;
    padding-bottom: 16px;
    margin-bottom: 22px;
}

.logo {
    font-size: 22px;
    font-weight: bold;
    color: #640D14;
}

.subtitle {
    color: #8a7770;
    margin-top: 4px;
}

.title {
    margin-top: 22px;
    font-size: 21px;
    color: #640D14;
    font-weight: bold;
}

.period {
    color: #AD2831;
    font-size: 11px;
}

.summary {
    width: 100%;
    border-collapse: separate;
    border-spacing: 8px;
    margin: 0 -8px 20px;
}

.summary td {
    background: #FFF9EA;
    border: 1px solid #eadfc9;
    padding: 13px;
    width: 25%;
}

.summary-label {
    color: #8a7770;
    font-size: 9px;
}

.summary-value {
    color: #640D14;
    font-size: 13px;
    font-weight: bold;
    margin-top: 5px;
}

.section-title {
    color: #640D14;
    font-size: 14px;
    font-weight: bold;
    margin: 22px 0 10px;
}

table.data {
    width: 100%;
    border-collapse: collapse;
}

table.data th {
    background: #640D14;
    color: white;
    padding: 8px;
    text-align: left;
    font-size: 9px;
}

table.data td {
    padding: 8px;
    border-bottom: 1px solid #eadfc9;
    font-size: 9px;
}

.income {
    color: #9a681d;
}

.expense {
    color: #AD2831;
}

.footer {
    margin-top: 35px;
    border-top: 1px solid #eadfc9;
    padding-top: 10px;
    color: #9a8c84;
    font-size: 8px;
}

</style>

</head>

<body>

<div class="header">

    <div class="logo">
        RAY BOUQUET
    </div>

    <div class="subtitle">
        Laporan Sistem Informasi Manajemen
    </div>

    <div class="title">
        Laporan Keuangan & Operasional
    </div>

    <div class="period">
        Periode ' . htmlspecialchars($namaPeriode) . '
    </div>

</div>


<table class="summary">

<tr>

<td>

    <div class="summary-label">
        PEMASUKAN
    </div>

    <div class="summary-value">
        Rp ' . number_format($pemasukan, 0, ',', '.') . '
    </div>

</td>

<td>

    <div class="summary-label">
        PENGELUARAN
    </div>

    <div class="summary-value">
        Rp ' . number_format($pengeluaran, 0, ',', '.') . '
    </div>

</td>

<td>

    <div class="summary-label">
        SALDO BERSIH
    </div>

    <div class="summary-value">
        Rp ' . number_format($saldo, 0, ',', '.') . '
    </div>

</td>

<td>

    <div class="summary-label">
        TRANSAKSI
    </div>

    <div class="summary-value">
        ' . number_format($jumlahTransaksi) . '
    </div>

</td>

</tr>

</table>


<div class="section-title">
    Ringkasan Inventory
</div>

<table class="data">

<tr>

<th>Total Item</th>
<th>Total Stok</th>
<th>Stok Menipis</th>

</tr>

<tr>

<td>' . $inventory['total_item'] . '</td>

<td>' . $inventory['total_stok'] . '</td>

<td>' . $inventory['stok_menipis'] . '</td>

</tr>

</table>


<div class="section-title">
    Detail Transaksi
</div>

<table class="data">

<tr>

<th width="12%">Tanggal</th>
<th width="15%">Jenis</th>
<th width="15%">Kategori</th>
<th>Keterangan</th>
<th width="18%">Nominal</th>

</tr>
';

foreach ($transaksi as $row) {

    $class = $row['jenis'] === 'Pemasukan'
        ? 'income'
        : 'expense';

    $html .= '

    <tr>

        <td>
            ' . date('d/m/Y', strtotime($row['tanggal'])) . '
        </td>

        <td class="' . $class . '">
            ' . htmlspecialchars($row['jenis']) . '
        </td>

        <td>
            ' . htmlspecialchars($row['kategori']) . '
        </td>

        <td>
            ' . htmlspecialchars($row['keterangan']) . '
        </td>

        <td>
            Rp ' . number_format($row['nominal'], 0, ',', '.') . '
        </td>

    </tr>

    ';
}


$html .= '

</table>


<div class="footer">

    Dokumen ini dibuat secara otomatis oleh Sistem Informasi Manajemen RAY Bouquet.

    <br>

    Dicetak pada ' . date('d/m/Y H:i') . '

</div>


</body>

</html>

';


/* =========================
   GENERATE PDF
========================= */

$options = new Options();

$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);

$dompdf->setPaper('A4', 'portrait');

$dompdf->render();

$output = $dompdf->output();

file_put_contents($pathFile, $output);


/* =========================
   SIMPAN KE DOKUMEN
========================= */

$idAkun = $_SESSION['id_akun'] ?? null;

$stmt = $pdo_oas->prepare("
    INSERT INTO dokumen
    (
        nama_dokumen,
        jenis_dokumen,
        id_akun,
        tanggal_dokumen,
        status,
        keterangan,
        file_dokumen
    )
    VALUES (?, 'Laporan', ?, CURDATE(), 'Disetujui', ?, ?)
");

$stmt->execute([
    'Laporan ' . $namaPeriode,
    $idAkun,
    'Laporan keuangan dan operasional periode ' . $namaPeriode,
    $relativePath
]);


/* =========================
   DOWNLOAD PDF
========================= */

$dompdf->stream(
    'Laporan_' . $namaPeriode,
    [
        'Attachment' => true
    ]
);

exit;