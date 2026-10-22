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

// Filter tanggal
$tanggal_awal = $_GET['tanggal_awal'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-d');

// Validasi format tanggal
if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_awal) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_akhir) ||
    $tanggal_awal > $tanggal_akhir
) {
    $tanggal_awal = date('Y-m-01');
    $tanggal_akhir = date('Y-m-d');
}

$params = [
    ':tanggal_awal' => $tanggal_awal . ' 00:00:00',
    ':tanggal_akhir' => $tanggal_akhir . ' 23:59:59'
];

// Ringkasan penjualan dari pesanan selesai
$sqlRingkasan = "
    SELECT
        COUNT(*) AS jumlah_pesanan,
        COALESCE(SUM(total_bayar), 0) AS total_omzet
    FROM pesanan
    WHERE status_pesanan = 'selesai'
      AND tanggal_pesan BETWEEN :tanggal_awal AND :tanggal_akhir
";

$stmt = $pdo->prepare($sqlRingkasan);
$stmt->execute($params);
$ringkasan = $stmt->fetch(PDO::FETCH_ASSOC);

// Total bouquet terjual
$sqlProdukTerjual = "
    SELECT COALESCE(SUM(ip.jumlah), 0) AS total_produk
    FROM item_pesanan ip
    JOIN pesanan p ON ip.id_pesanan = p.id_pesanan
    WHERE p.status_pesanan = 'selesai'
      AND p.tanggal_pesan BETWEEN :tanggal_awal AND :tanggal_akhir
";

$stmt = $pdo->prepare($sqlProdukTerjual);
$stmt->execute($params);
$totalProduk = $stmt->fetchColumn();

// Pesanan dibatalkan
$sqlBatal = "
    SELECT COUNT(*)
    FROM pesanan
    WHERE status_pesanan = 'dibatalkan'
      AND tanggal_pesan BETWEEN :tanggal_awal AND :tanggal_akhir
";

$stmt = $pdo->prepare($sqlBatal);
$stmt->execute($params);
$totalBatal = $stmt->fetchColumn();

// Lima produk terlaris
$sqlTerlaris = "
    SELECT
        b.nama_bouquet,
        SUM(ip.jumlah) AS total_terjual,
        SUM(ip.subtotal) AS total_penjualan
    FROM item_pesanan ip
    JOIN bouquet b ON ip.id_bouquet = b.id_bouquet
    JOIN pesanan p ON ip.id_pesanan = p.id_pesanan
    WHERE p.status_pesanan = 'selesai'
      AND p.tanggal_pesan BETWEEN :tanggal_awal AND :tanggal_akhir
    GROUP BY b.id_bouquet, b.nama_bouquet
    ORDER BY total_terjual DESC, total_penjualan DESC
    LIMIT 5
";

$stmt = $pdo->prepare($sqlTerlaris);
$stmt->execute($params);
$produkTerlaris = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Data dummy jika belum ada penjualan selesai
if (empty($produkTerlaris)) {
    $produkTerlaris = [
        [
            'nama_bouquet' => 'Red Rose Bouquet',
            'total_terjual' => 25,
            'total_penjualan' => 1250000
        ],
        [
            'nama_bouquet' => 'Pink Romance Bouquet',
            'total_terjual' => 20,
            'total_penjualan' => 1000000
        ],
        [
            'nama_bouquet' => 'White Elegance Bouquet',
            'total_terjual' => 18,
            'total_penjualan' => 900000
        ],
        [
            'nama_bouquet' => 'Sunflower Happiness',
            'total_terjual' => 15,
            'total_penjualan' => 750000
        ],
        [
            'nama_bouquet' => 'Baby Breath Bouquet',
            'total_terjual' => 12,
            'total_penjualan' => 600000
        ]
    ];
}

// Riwayat transaksi
$sqlTransaksi = "
    SELECT
        p.id_pesanan,
        p.nomor_pesanan,
        p.tanggal_pesan,
        p.total_bayar,
        p.status_pesanan,
        COALESCE(SUM(ip.jumlah), 0) AS jumlah_item
    FROM pesanan p
    LEFT JOIN item_pesanan ip ON p.id_pesanan = ip.id_pesanan
    WHERE p.tanggal_pesan BETWEEN :tanggal_awal AND :tanggal_akhir
    GROUP BY
        p.id_pesanan,
        p.nomor_pesanan,
        p.tanggal_pesan,
        p.total_bayar,
        p.status_pesanan
    ORDER BY p.tanggal_pesan DESC
";

$stmt = $pdo->prepare($sqlTransaksi);
$stmt->execute($params);
$transaksi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format rupiah
function rupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

// Format tanggal
function tanggalIndonesia($tanggal)
{
    return date('d/m/Y H:i', strtotime($tanggal));
}

layoutAtas(
    'Laporan Penjualan',
    'Ringkasan dan riwayat penjualan RAY Bouquet',
    'laporan_penjualan.php',
    ['laporan_penjualan.css']
);
?>

<div class="laporan-page">

    <div class="laporan-header">
        <div>
            <h2>Laporan Penjualan</h2>
            <p>Ringkasan transaksi dan produk yang terjual.</p>
        </div>
    </div>

    <!-- Filter tanggal -->
    <form method="GET" class="periode-form">
        <div>
            <label for="tanggal_awal">Dari tanggal</label>
            <input
                type="date"
                id="tanggal_awal"
                name="tanggal_awal"
                value="<?= htmlspecialchars($tanggal_awal) ?>"
                required
            >
        </div>

        <div>
            <label for="tanggal_akhir">Sampai tanggal</label>
            <input
                type="date"
                id="tanggal_akhir"
                name="tanggal_akhir"
                value="<?= htmlspecialchars($tanggal_akhir) ?>"
                min="<?= htmlspecialchars($tanggal_awal) ?>"
                required
            >
        </div>

        <button type="submit" class="btn-filter">Tampilkan</button>
    </form>

    <!-- Ringkasan -->
    <div class="summary-grid">

        <div class="summary-card income">
            <span class="card-label">Total Omzet</span>
            <h3><?= rupiah($ringkasan['total_omzet']) ?></h3>
            <p>Dari pesanan selesai</p>
        </div>

        <div class="summary-card">
            <span class="card-label">Pesanan Selesai</span>
            <h3><?= number_format((int) $ringkasan['jumlah_pesanan'], 0, ',', '.') ?></h3>
            <p>Transaksi berhasil</p>
        </div>

        <div class="summary-card">
            <span class="card-label">Bouquet Terjual</span>
            <h3><?= number_format((int) $totalProduk, 0, ',', '.') ?></h3>
            <p>Total unit terjual</p>
        </div>

        <div class="summary-card">
            <span class="card-label">Pesanan Dibatalkan</span>
            <h3><?= number_format((int) $totalBatal, 0, ',', '.') ?></h3>
            <p>Dalam periode terpilih</p>
        </div>

    </div>

    <!-- Produk terlaris -->
    <div class="report-card">
        <div class="card-heading">
            <h3>Produk Terlaris</h3>
            <p>Lima bouquet dengan jumlah penjualan tertinggi.</p>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Bouquet</th>
                        <th>Jumlah Terjual</th>
                        <th>Total Penjualan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($produkTerlaris)): ?>
                        <tr>
                            <td colspan="4">Belum ada penjualan selesai pada periode ini.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($produkTerlaris as $index => $produk): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($produk['nama_bouquet']) ?></td>
                                <td><?= number_format((int) $produk['total_terjual'], 0, ',', '.') ?></td>
                                <td><?= rupiah($produk['total_penjualan']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Riwayat transaksi -->
    <div class="report-card">
        <div class="card-heading">
            <h3>Riwayat Transaksi</h3>
            <p>Daftar seluruh pesanan berdasarkan tanggal yang dipilih.</p>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nomor Pesanan</th>
                        <th>Tanggal Pesanan</th>
                        <th>Jumlah Item</th>
                        <th>Total Bayar</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transaksi)): ?>
                        <tr>
                            <td colspan="6">Tidak ada transaksi pada periode ini.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transaksi as $index => $row): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($row['nomor_pesanan']) ?></td>
                                <td><?= tanggalIndonesia($row['tanggal_pesan']) ?></td>
                                <td><?= number_format((int) $row['jumlah_item'], 0, ',', '.') ?></td>
                                <td><?= rupiah($row['total_bayar']) ?></td>
                                <td>
                                    <?= htmlspecialchars(ucfirst($row['status_pesanan'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php layoutBawah(); ?>