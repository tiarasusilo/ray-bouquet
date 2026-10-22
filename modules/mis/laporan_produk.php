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
    die('Anda tidak memiliki akses ke halaman laporan produk.');
}

// Filter periode
$tanggal_awal = $_GET['tanggal_awal'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-d');

$formatTanggal = '/^\d{4}-\d{2}-\d{2}$/';

if (
    !preg_match($formatTanggal, $tanggal_awal) ||
    !preg_match($formatTanggal, $tanggal_akhir) ||
    $tanggal_awal > $tanggal_akhir
) {
    $tanggal_awal = date('Y-m-01');
    $tanggal_akhir = date('Y-m-d');
}

$awal = $tanggal_awal . ' 00:00:00';
$akhir = $tanggal_akhir . ' 23:59:59';

// Ringkasan katalog
$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total_produk,
        COALESCE(SUM(stok), 0) AS total_stok,
        COALESCE(SUM(CASE WHEN stok <= 5 THEN 1 ELSE 0 END), 0)
            AS stok_menipis
    FROM bouquet
");

$ringkasan = $stmt->fetch(PDO::FETCH_ASSOC);

// Ringkasan penjualan selama periode yang dipilih
$sqlRingkasanPenjualan = "
    SELECT
        COALESCE(SUM(ip.jumlah), 0) AS total_terjual,
        COALESCE(SUM(ip.subtotal), 0) AS total_pendapatan
    FROM item_pesanan ip
    JOIN pesanan p ON p.id_pesanan = ip.id_pesanan
    WHERE p.status_pesanan = 'selesai'
      AND p.tanggal_pesan BETWEEN :awal AND :akhir
";

$stmt = $pdo->prepare($sqlRingkasanPenjualan);
$stmt->execute([
    ':awal' => $awal,
    ':akhir' => $akhir
]);

$ringkasanPenjualan = $stmt->fetch(PDO::FETCH_ASSOC);

// Laporan penjualan per bouquet
$sqlProduk = "
    SELECT
        b.id_bouquet,
        b.nama_bouquet,
        b.foto,
        b.harga,
        b.stok,
        b.warna,
        b.ukuran,
        COALESCE(SUM(
            CASE
                WHEN p.id_pesanan IS NOT NULL THEN ip.jumlah
                ELSE 0
            END
        ), 0) AS jumlah_terjual,
        COALESCE(SUM(
            CASE
                WHEN p.id_pesanan IS NOT NULL THEN ip.subtotal
                ELSE 0
            END
        ), 0) AS total_pendapatan
    FROM bouquet b
    LEFT JOIN item_pesanan ip
        ON ip.id_bouquet = b.id_bouquet
    LEFT JOIN pesanan p
        ON p.id_pesanan = ip.id_pesanan
        AND p.status_pesanan = 'selesai'
        AND p.tanggal_pesan BETWEEN :awal AND :akhir
    GROUP BY
        b.id_bouquet,
        b.nama_bouquet,
        b.foto,
        b.harga,
        b.stok,
        b.warna,
        b.ukuran
    ORDER BY jumlah_terjual DESC, b.nama_bouquet ASC
";

$stmt = $pdo->prepare($sqlProduk);
$stmt->execute([
    ':awal' => $awal,
    ':akhir' => $akhir
]);

$produk = $stmt->fetchAll(PDO::FETCH_ASSOC);


$produk = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil maksimal 5 produk terlaris yang benar-benar terjual
$produkTerlaris = array_values(array_filter(
    $produk,
    fn($item) => (int) $item['jumlah_terjual'] > 0
));

$produkTerlaris = array_slice($produkTerlaris, 0, 5);

// Dummy hanya untuk kebutuhan tampilan/demo
$pakaiDummy = empty($produkTerlaris);

if ($pakaiDummy) {
    $produkTerlaris = [
        ['nama_bouquet' => 'Red Rose Bouquet', 'jumlah_terjual' => 25, 'total_pendapatan' => 1250000],
        ['nama_bouquet' => 'Pink Romance Bouquet', 'jumlah_terjual' => 20, 'total_pendapatan' => 1000000],
        ['nama_bouquet' => 'White Elegance Bouquet', 'jumlah_terjual' => 18, 'total_pendapatan' => 900000],
        ['nama_bouquet' => 'Sunflower Happiness', 'jumlah_terjual' => 15, 'total_pendapatan' => 750000],
        ['nama_bouquet' => 'Baby Breath Bouquet', 'jumlah_terjual' => 12, 'total_pendapatan' => 600000],
    ];
}


// Format angka dan rupiah
function formatRupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function formatAngka($angka)
{
    return number_format((int) $angka, 0, ',', '.');
}

function e($nilai)
{
    return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
}

layoutAtas(
    'Laporan Produk',
    'Ringkasan performa produk RAY Bouquet',
    'laporan_produk.php',
    ['laporan_produk.css']
);
?>

<div class="laporan-produk-page">

    <!-- Header -->
    <div class="produk-report-header">
        <div>
            <h2>Laporan Produk</h2>
            <p>
                Pantau performa penjualan, persediaan, dan produk
                RAY Bouquet.
            </p>
        </div>
    </div>

    <!-- Filter periode -->
    <form method="GET" class="produk-filter">
        <div class="filter-field">
            <label for="tanggal_awal">Dari tanggal</label>
            <input
                type="date"
                id="tanggal_awal"
                name="tanggal_awal"
                value="<?= e($tanggal_awal) ?>"
                required
            >
        </div>

        <div class="filter-field">
            <label for="tanggal_akhir">Sampai tanggal</label>
            <input
                type="date"
                id="tanggal_akhir"
                name="tanggal_akhir"
                value="<?= e($tanggal_akhir) ?>"
                min="<?= e($tanggal_awal) ?>"
                required
            >
        </div>

        <button type="submit" class="produk-filter-btn">
            Tampilkan
        </button>
    </form>

    <!-- Ringkasan katalog -->
    <div class="produk-summary-grid">

        <div class="produk-summary-card">
            <span>Total Produk</span>
            <strong><?= formatAngka($ringkasan['total_produk']) ?></strong>
            <small>Bouquet terdaftar</small>
        </div>

        <div class="produk-summary-card">
            <span>Total Stok</span>
            <strong><?= formatAngka($ringkasan['total_stok']) ?></strong>
            <small>Unit tersedia di katalog</small>
        </div>

        <div class="produk-summary-card">
            <span>Stok Menipis (≤ 5)</span>
            <strong class="nilai-merah">
                <?= formatAngka($ringkasan['stok_menipis']) ?>
            </strong>
            <small>Produk yang perlu diperhatikan</small>
        </div>

        <div class="produk-summary-card">
            <span>Total Terjual</span>
            <strong class="nilai-emas">
                <?= formatAngka($ringkasanPenjualan['total_terjual']) ?>
            </strong>
            <small>Unit dari pesanan selesai</small>
        </div>

    </div>

    <!-- Produk terlaris -->
    <section class="produk-report-card">
        <div class="produk-card-header">
            <div>
                <h3>Produk Terlaris</h3>
                <p>
                    Urutan bouquet berdasarkan jumlah unit terjual
                    selama periode laporan.
                </p>
            </div>

            <span class="produk-period-badge">
                <?= e(date('d/m/Y', strtotime($tanggal_awal))) ?>
                –
                <?= e(date('d/m/Y', strtotime($tanggal_akhir))) ?>
            </span>
        </div>



<div class="produk-chart-wrapper">
    <?php if ($pakaiDummy): ?>
        <p class="produk-chart-note">
            Contoh visualisasi — belum ada data penjualan selesai
            pada periode ini.
        </p>
    <?php endif; ?>

    <canvas id="grafikProdukTerlaris"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const canvas = document.getElementById('grafikProdukTerlaris');
    if (!canvas || typeof Chart === 'undefined') return;

    const labels = <?= json_encode(
        array_column($produkTerlaris, 'nama_bouquet'),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>;

    const values = <?= json_encode(
        array_map(
            'intval',
            array_column($produkTerlaris, 'jumlah_terjual')
        )
    ) ?>;

    const pendapatan = <?= json_encode(
        array_map(
            'floatval',
            array_column($produkTerlaris, 'total_pendapatan')
        )
    ) ?>;

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Jumlah terjual',
                data: values,
                backgroundColor: [
                    '#640D14',
                    '#AD2831',
                    '#D8973C',
                    '#E8B7A7',
                    '#C9A6A0'
                ],
                borderRadius: 7,
                borderSkipped: false,
                barThickness: 26
            }]
        },

options: {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,

    // Samakan font grafik dengan halaman
    font: {
        family: "'Poppins', sans-serif",
        size: 12
    },

    scales: {
        x: {
            beginAtZero: true,
            ticks: {
                precision: 0,
                font: {
                    family: "'Poppins', sans-serif",
                    size: 11
                }
            },
            title: {
                display: true,
                text: 'Jumlah bouquet terjual',
                font: {
                    family: "'Poppins', sans-serif",
                    size: 12
                }
            },
            grid: {
                color: '#f0e8e2'
            }
        },
        y: {
            ticks: {
                font: {
                    family: "'Poppins', sans-serif",
                    size: 11
                }
            },
            grid: {
                display: false
            }
        }
    },

    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            callbacks: {
                label: function(context) {
                    const i = context.dataIndex;
                    return [
                        'Terjual: ' + context.raw + ' unit',
                        'Pendapatan: Rp ' +
                            Number(pendapatan[i]).toLocaleString('id-ID')
                    ];
                }
            }
        }
    }
}
    });
});
</script>

    </section>

    <!-- Seluruh produk -->
    <section class="produk-report-card">
        <div class="produk-card-header">
            <div>
                <h3>Ringkasan Seluruh Produk</h3>
                <p>
                    Informasi harga, stok, dan performa penjualan
                    setiap bouquet.
                </p>
            </div>
        </div>

        <div class="produk-table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Bouquet</th>
                        <th>Warna</th>
                        <th>Ukuran</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Terjual</th>
                        <th>Pendapatan</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($produk)): ?>
                        <tr>
                            <td colspan="8" class="produk-empty">
                                Belum ada produk yang terdaftar.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($produk as $index => $item): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>

                                <td class="nama-bouquet">
                                    <?= e($item['nama_bouquet']) ?>
                                </td>

                                <td><?= e($item['warna'] ?: '-') ?></td>

                                <td><?= e($item['ukuran'] ?: '-') ?></td>

                                <td class="produk-nominal">
                                    <?= formatRupiah($item['harga']) ?>
                                </td>

                                <td>
                                    <?php if ((int) $item['stok'] <= 5): ?>
                                        <span class="stok-badge stok-rendah">
                                            <?= formatAngka($item['stok']) ?>
                                            tersisa
                                        </span>
                                    <?php else: ?>
                                        <span class="stok-badge stok-aman">
                                            <?= formatAngka($item['stok']) ?>
                                            tersisa
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= formatAngka($item['jumlah_terjual']) ?>
                                </td>

                                <td class="produk-nominal">
                                    <?= formatRupiah($item['total_pendapatan']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="produk-card-footer">
            Data katalog mengikuti data produk saat ini. Penjualan
            dihitung dari pesanan berstatus selesai pada periode terpilih.
        </div>
    </section>

</div>

<?php layoutBawah(); ?>