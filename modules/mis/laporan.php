<?php
session_start();

require_once 'koneksi.php';
require_once 'auth.php';
require_once '_layout.php';

if (!isset($_SESSION['tipe_akun'])) {
    header('Location: login.php');
    exit;
}

if (!hasPrivilege('view_laporan')) {
    die('Anda tidak memiliki akses ke halaman laporan.');
}

/* =========================
   FILTER PERIODE
========================= */

$bulan = isset($_GET['bulan']) ? (int) $_GET['bulan'] : (int) date('m');
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');

if ($bulan < 1 || $bulan > 12) {
    $bulan = (int) date('m');
}

if ($tahun < 2020 || $tahun > 2100) {
    $tahun = (int) date('Y');
}

$periode = sprintf('%04d-%02d', $tahun, $bulan);

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


/* =========================
   RINGKASAN KEUANGAN
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
   PEMASUKAN PER KATEGORI
========================= */

$stmt = $pdo->prepare("
    SELECT
        kategori,
        SUM(nominal) AS total
    FROM keuangan
    WHERE status = 'Disetujui'
      AND jenis = 'Pemasukan'
      AND DATE_FORMAT(tanggal, '%Y-%m') = ?
    GROUP BY kategori
    ORDER BY total DESC
");

$stmt->execute([$periode]);
$pemasukanKategori = $stmt->fetchAll();


/* =========================
   PENGELUARAN PER KATEGORI
========================= */

$stmt = $pdo->prepare("
    SELECT
        kategori,
        SUM(nominal) AS total
    FROM keuangan
    WHERE status = 'Disetujui'
      AND jenis = 'Pengeluaran'
      AND DATE_FORMAT(tanggal, '%Y-%m') = ?
    GROUP BY kategori
    ORDER BY total DESC
");

$stmt->execute([$periode]);
$pengeluaranKategori = $stmt->fetchAll();


/* =========================
   DATA INVENTORY
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

$totalItem = (int) $inventory['total_item'];
$totalStok = (int) $inventory['total_stok'];
$stokMenipis = (int) $inventory['stok_menipis'];


/* =========================
   DATA PRODUK & PESANAN
========================= */

$totalProduk = 0;
$totalPesanan = 0;

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM bouquet");
    $totalProduk = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    $totalProduk = 0;
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM pesanan");
    $totalPesanan = (int) $stmt->fetchColumn();
} catch (PDOException $e) {
    $totalPesanan = 0;
}


/* =========================
   TRANSAKSI TERBARU
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
    ORDER BY tanggal DESC
    LIMIT 10
");

$stmt->execute([$periode]);
$transaksi = $stmt->fetchAll();


/* =========================
   DATA CHART
========================= */

$chartLabels = [];
$chartIncome = [];
$chartExpense = [];

for ($i = 1; $i <= 12; $i++) {

    $chartLabels[] = $namaBulan[$i];

    $chartStmt = $pdo->prepare("
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
            ), 0) AS pengeluaran

        FROM keuangan

        WHERE status = 'Disetujui'
          AND YEAR(tanggal) = ?
          AND MONTH(tanggal) = ?
    ");

    $chartStmt->execute([$tahun, $i]);
    $chart = $chartStmt->fetch();

    $chartIncome[] = (float) $chart['pemasukan'];
    $chartExpense[] = (float) $chart['pengeluaran'];
}


/* =========================
   LAYOUT
========================= */

layoutAtas(
    'Laporan',
    'Ringkasan aktivitas dan keuangan RAY Bouquet',
    'laporan.php',
    ['laporan.css']
);
?>

<div class="laporan-page">

    <!-- HEADER -->
    <div class="laporan-header">

        <div>
            <span class="eyebrow">REPORT CENTER</span>

            <h1>
                Laporan <?= htmlspecialchars($namaPeriode) ?>
            </h1>

            <p>
                Ringkasan kondisi keuangan, produk, pesanan, dan inventory RAY Bouquet.
            </p>
        </div>

        <div class="header-actions">

            <form method="GET" class="periode-form">

                <select name="bulan">
                    <?php foreach ($namaBulan as $nomor => $nama): ?>
                        <option
                            value="<?= $nomor ?>"
                            <?= $bulan == $nomor ? 'selected' : '' ?>
                        >
                            <?= $nama ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="tahun">
                    <?php for ($y = date('Y') - 3; $y <= date('Y') + 1; $y++): ?>
                        <option
                            value="<?= $y ?>"
                            <?= $tahun == $y ? 'selected' : '' ?>
                        >
                            <?= $y ?>
                        </option>
                    <?php endfor; ?>
                </select>

                <button type="submit" class="btn-filter">
                    Tampilkan
                </button>

            </form>

            <a
                href="export_laporan.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>"
                target="_blank"
                class="btn-pdf"
            >
                <span>↓</span>
                Export PDF
            </a>

        </div>

    </div>


    <!-- SUMMARY -->
    <div class="summary-grid">

        <div class="summary-card income">

            <div class="summary-icon">+</div>

            <div>
                <span>Pemasukan</span>

                <strong>
                    Rp <?= number_format($pemasukan, 0, ',', '.') ?>
                </strong>
            </div>

        </div>


        <div class="summary-card expense">

            <div class="summary-icon">−</div>

            <div>
                <span>Pengeluaran</span>

                <strong>
                    Rp <?= number_format($pengeluaran, 0, ',', '.') ?>
                </strong>
            </div>

        </div>


        <div class="summary-card balance">

            <div class="summary-icon">=</div>

            <div>
                <span>Saldo Bersih</span>

                <strong>
                    Rp <?= number_format($saldo, 0, ',', '.') ?>
                </strong>
            </div>

        </div>


        <div class="summary-card transaction">

            <div class="summary-icon">#</div>

            <div>
                <span>Transaksi</span>

                <strong>
                    <?= number_format($jumlahTransaksi) ?>
                </strong>
            </div>

        </div>

    </div>


    <!-- CHART -->
    <div class="report-grid">

        <section class="report-card chart-card">

            <div class="card-heading">

                <div>
                    <span class="card-label">KEUANGAN</span>
                    <h2>Arus Keuangan</h2>
                </div>

                <span class="period-badge">
                    <?= htmlspecialchars($tahun) ?>
                </span>

            </div>

            <div class="chart-wrapper">
                <canvas id="financeChart"></canvas>
            </div>

        </section>


        <!-- AKTIVITAS -->
        <section class="report-card activity-card">

            <div class="card-heading">

                <div>
                    <span class="card-label">OVERVIEW</span>
                    <h2>Aktivitas</h2>
                </div>

            </div>

            <div class="activity-list">

                <div class="activity-item">
                    <div class="activity-dot"></div>

                    <div>
                        <span>Produk</span>
                        <strong><?= $totalProduk ?></strong>
                    </div>
                </div>


                <div class="activity-item">
                    <div class="activity-dot"></div>

                    <div>
                        <span>Pesanan</span>
                        <strong><?= $totalPesanan ?></strong>
                    </div>
                </div>


                <div class="activity-item">
                    <div class="activity-dot"></div>

                    <div>
                        <span>Total Item Inventory</span>
                        <strong><?= $totalItem ?></strong>
                    </div>
                </div>


                <div class="activity-item warning">
                    <div class="activity-dot"></div>

                    <div>
                        <span>Stok Menipis</span>
                        <strong><?= $stokMenipis ?></strong>
                    </div>
                </div>


                <div class="activity-item">
                    <div class="activity-dot"></div>

                    <div>
                        <span>Total Stok</span>
                        <strong><?= $totalStok ?></strong>
                    </div>
                </div>

            </div>

        </section>

    </div>


    <!-- KATEGORI -->
    <div class="report-grid category-grid">

        <section class="report-card">

            <div class="card-heading">
                <div>
                    <span class="card-label">PEMASUKAN</span>
                    <h2>Berdasarkan Kategori</h2>
                </div>
            </div>

            <?php if (count($pemasukanKategori) > 0): ?>

                <div class="category-list">

                    <?php foreach ($pemasukanKategori as $row): ?>

                        <div class="category-row">

                            <span>
                                <?= htmlspecialchars($row['kategori']) ?>
                            </span>

                            <strong>
                                Rp <?= number_format($row['total'], 0, ',', '.') ?>
                            </strong>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    Belum ada pemasukan pada periode ini.
                </div>

            <?php endif; ?>

        </section>


        <section class="report-card">

            <div class="card-heading">
                <div>
                    <span class="card-label">PENGELUARAN</span>
                    <h2>Berdasarkan Kategori</h2>
                </div>
            </div>

            <?php if (count($pengeluaranKategori) > 0): ?>

                <div class="category-list">

                    <?php foreach ($pengeluaranKategori as $row): ?>

                        <div class="category-row">

                            <span>
                                <?= htmlspecialchars($row['kategori']) ?>
                            </span>

                            <strong>
                                Rp <?= number_format($row['total'], 0, ',', '.') ?>
                            </strong>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    Belum ada pengeluaran pada periode ini.
                </div>

            <?php endif; ?>

        </section>

    </div>


    <!-- TRANSAKSI -->
    <section class="report-card transaction-card">

        <div class="card-heading">

            <div>
                <span class="card-label">TRANSAKSI</span>
                <h2>Transaksi Terbaru</h2>
            </div>

            <span class="transaction-period">
                <?= htmlspecialchars($namaPeriode) ?>
            </span>

        </div>


        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Kategori</th>
                        <th>Keterangan</th>
                        <th class="text-right">Nominal</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($transaksi) > 0): ?>

                        <?php foreach ($transaksi as $row): ?>

                            <tr>

                                <td>
                                    <?= date('d/m/Y', strtotime($row['tanggal'])) ?>
                                </td>

                                <td>

                                    <?php if ($row['jenis'] === 'Pemasukan'): ?>

                                        <span class="type-badge income-badge">
                                            Pemasukan
                                        </span>

                                    <?php else: ?>

                                        <span class="type-badge expense-badge">
                                            Pengeluaran
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?= htmlspecialchars($row['kategori']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['keterangan']) ?>
                                </td>

                                <td class="text-right nominal">
                                    Rp <?= number_format($row['nominal'], 0, ',', '.') ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" class="empty-table">
                                Belum ada transaksi pada periode ini.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const ctx = document.getElementById('financeChart');

new Chart(ctx, {

    type: 'bar',

    data: {

        labels: <?= json_encode($chartLabels) ?>,

        datasets: [

            {
                label: 'Pemasukan',

                data: <?= json_encode($chartIncome) ?>,

                backgroundColor: '#D8973C',

                borderRadius: 7,

                borderSkipped: false
            },

            {
                label: 'Pengeluaran',

                data: <?= json_encode($chartExpense) ?>,

                backgroundColor: '#AD2831',

                borderRadius: 7,

                borderSkipped: false
            }

        ]

    },

    options: {

        responsive: true,

        maintainAspectRatio: false,

        plugins: {

            legend: {
                position: 'bottom',

                labels: {
                    usePointStyle: true,
                    padding: 20
                }
            },

            tooltip: {

                callbacks: {

                    label: function(context) {

                        return ' Rp ' +
                            new Intl.NumberFormat('id-ID').format(context.raw);

                    }

                }

            }

        },

        scales: {

            y: {

                beginAtZero: true,

                grid: {
                    color: 'rgba(100, 13, 20, 0.06)'
                },

                ticks: {

                    callback: function(value) {

                        if (value >= 1000000) {
                            return 'Rp ' + (value / 1000000) + ' jt';
                        }

                        if (value >= 1000) {
                            return 'Rp ' + (value / 1000) + ' rb';
                        }

                        return value;

                    }

                }

            },

            x: {

                grid: {
                    display: false
                }

            }

        }

    }

});

</script>

<?php layoutBawah(); ?>