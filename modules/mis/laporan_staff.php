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
    die('Anda tidak memiliki akses ke halaman laporan staff.');
}

// Filter periode
$tanggal_awal = $_GET['tanggal_awal'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-d');

if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_awal) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_akhir) ||
    $tanggal_awal > $tanggal_akhir
) {
    $tanggal_awal = date('Y-m-01');
    $tanggal_akhir = date('Y-m-d');
}

// DATA DUMMY — ganti dengan data database saat sudah tersedia
$staff = [
    [
        'nama' => 'Alya Putri',
        'jabatan' => 'Staff Produk',
        'ditangani' => 42,
        'selesai' => 38,
        'proses' => 4,
    ],
    [
        'nama' => 'Nadia Safitri',
        'jabatan' => 'Staff Pesanan',
        'ditangani' => 36,
        'selesai' => 32,
        'proses' => 4,
    ],
    [
        'nama' => 'Dinda Maharani',
        'jabatan' => 'Staff Pesanan',
        'ditangani' => 31,
        'selesai' => 29,
        'proses' => 2,
    ],
    [
        'nama' => 'Rani Amelia',
        'jabatan' => 'Staff Produk',
        'ditangani' => 27,
        'selesai' => 24,
        'proses' => 3,
    ],
];

$aktivitas = [
    [
        'tanggal' => date('Y-m-d'),
        'nama' => 'Alya Putri',
        'aktivitas' => 'Memperbarui data produk bouquet',
        'kategori' => 'Produk',
        'status' => 'Selesai',
    ],
    [
        'tanggal' => date('Y-m-d', strtotime('-1 day')),
        'nama' => 'Nadia Safitri',
        'aktivitas' => 'Memproses pesanan pelanggan',
        'kategori' => 'Pesanan',
        'status' => 'Selesai',
    ],
    [
        'tanggal' => date('Y-m-d', strtotime('-2 days')),
        'nama' => 'Dinda Maharani',
        'aktivitas' => 'Memperbarui status pesanan',
        'kategori' => 'Pesanan',
        'status' => 'Selesai',
    ],
    [
        'tanggal' => date('Y-m-d', strtotime('-3 days')),
        'nama' => 'Rani Amelia',
        'aktivitas' => 'Memeriksa ketersediaan stok bouquet',
        'kategori' => 'Inventory',
        'status' => 'Dalam Proses',
    ],
    [
        'tanggal' => date('Y-m-d', strtotime('-4 days')),
        'nama' => 'Alya Putri',
        'aktivitas' => 'Memperbarui informasi katalog',
        'kategori' => 'Produk',
        'status' => 'Selesai',
    ],
];

// Filter aktivitas sesuai periode
$aktivitas = array_values(array_filter(
    $aktivitas,
    function ($item) use ($tanggal_awal, $tanggal_akhir) {
        return $item['tanggal'] >= $tanggal_awal
            && $item['tanggal'] <= $tanggal_akhir;
    }
));

// Hitung ringkasan
$totalStaff = count($staff);
$totalDitangani = array_sum(array_column($staff, 'ditangani'));
$totalSelesai = array_sum(array_column($staff, 'selesai'));
$totalProses = array_sum(array_column($staff, 'proses'));

$tingkatPenyelesaian = $totalDitangani > 0
    ? round(($totalSelesai / $totalDitangani) * 100)
    : 0;

// Urutkan performa berdasarkan pesanan selesai
usort($staff, function ($a, $b) {
    return $b['selesai'] <=> $a['selesai'];
});

function staffEsc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function staffTanggal($tanggal)
{
    return date('d/m/Y', strtotime($tanggal));
}

layoutAtas(
    'Laporan Staff',
    'Ringkasan performa dan aktivitas staff RAY Bouquet',
    'laporan_staff.php',
    ['laporan_staff.css']
);
?>

<div class="laporan-staff-page">

    <!-- HEADER -->
    <div class="staff-report-header">
        <div>
            <h2>Laporan Staff</h2>
            <p>
                Pantau performa kerja dan aktivitas staff RAY Bouquet.
            </p>
        </div>

        <span class="staff-demo-badge">DATA DEMO</span>
    </div>

    <!-- FILTER PERIODE -->
    <form method="GET" class="staff-filter">
        <div class="staff-filter-field">
            <label for="tanggal_awal">Dari tanggal</label>
            <input
                type="date"
                id="tanggal_awal"
                name="tanggal_awal"
                value="<?= staffEsc($tanggal_awal) ?>"
                required
            >
        </div>

        <div class="staff-filter-field">
            <label for="tanggal_akhir">Sampai tanggal</label>
            <input
                type="date"
                id="tanggal_akhir"
                name="tanggal_akhir"
                value="<?= staffEsc($tanggal_akhir) ?>"
                min="<?= staffEsc($tanggal_awal) ?>"
                required
            >
        </div>

        <button type="submit" class="staff-filter-btn">
            Tampilkan
        </button>
    </form>

    <!-- RINGKASAN -->
    <div class="staff-summary-grid">

        <div class="staff-summary-card">
            <span>Total Staff</span>
            <strong><?= number_format($totalStaff, 0, ',', '.') ?></strong>
            <small>Staff dalam data demo</small>
        </div>

        <div class="staff-summary-card">
            <span>Pesanan Ditangani</span>
            <strong><?= number_format($totalDitangani, 0, ',', '.') ?></strong>
            <small>Total tugas pesanan</small>
        </div>

        <div class="staff-summary-card">
            <span>Pesanan Selesai</span>
            <strong class="staff-green">
                <?= number_format($totalSelesai, 0, ',', '.') ?>
            </strong>
            <small>Tugas yang telah diselesaikan</small>
        </div>

        <div class="staff-summary-card">
            <span>Tingkat Penyelesaian</span>
            <strong class="staff-gold">
                <?= $tingkatPenyelesaian ?>%
            </strong>
            <small>
                <?= number_format($totalProses, 0, ',', '.') ?>
                tugas masih diproses
            </small>
        </div>

    </div>

    <!-- PERFORMA STAFF -->
    <section class="staff-report-card">
        <div class="staff-card-header">
            <div>
                <h3>Performa Staff</h3>
                <p>
                    Peringkat berdasarkan jumlah tugas yang selesai.
                </p>
            </div>

            <span class="staff-period-badge">
                <?= staffTanggal($tanggal_awal) ?>
                – <?= staffTanggal($tanggal_akhir) ?>
            </span>
        </div>

        <div class="staff-table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Peringkat</th>
                        <th>Nama Staff</th>
                        <th>Bagian</th>
                        <th>Ditangani</th>
                        <th>Selesai</th>
                        <th>Dalam Proses</th>
                        <th>Penyelesaian</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($staff as $index => $item): ?>
                        <?php
                        $persentase = $item['ditangani'] > 0
                            ? round(
                                ($item['selesai'] / $item['ditangani']) * 100
                            )
                            : 0;
                        ?>

                        <tr>
                            <td>
                                <span class="staff-rank">
                                    <?= $index + 1 ?>
                                </span>
                            </td>

                            <td class="staff-name">
                                <?= staffEsc($item['nama']) ?>
                            </td>

                            <td><?= staffEsc($item['jabatan']) ?></td>

                            <td><?= $item['ditangani'] ?></td>

                            <td>
                                <span class="staff-status staff-status-done">
                                    <?= $item['selesai'] ?>
                                </span>
                            </td>

                            <td>
                                <span class="staff-status staff-status-process">
                                    <?= $item['proses'] ?>
                                </span>
                            </td>

                            <td>
                                <div class="staff-progress">
                                    <div class="staff-progress-track">
                                        <div
                                            class="staff-progress-fill"
                                            style="width: <?= $persentase ?>%;"
                                        ></div>
                                    </div>
                                    <span><?= $persentase ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- AKTIVITAS STAFF -->
    <section class="staff-report-card">
        <div class="staff-card-header">
            <div>
                <h3>Riwayat Aktivitas Staff</h3>
                <p>
                    Contoh aktivitas staff dalam periode yang dipilih.
                </p>
            </div>
        </div>

        <div class="staff-table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Nama Staff</th>
                        <th>Aktivitas</th>
                        <th>Kategori</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($aktivitas)): ?>
                        <tr>
                            <td colspan="5" class="staff-empty">
                                Tidak ada aktivitas pada periode ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($aktivitas as $item): ?>
                            <tr>
                                <td><?= staffTanggal($item['tanggal']) ?></td>

                                <td class="staff-name">
                                    <?= staffEsc($item['nama']) ?>
                                </td>

                                <td><?= staffEsc($item['aktivitas']) ?></td>

                                <td>
                                    <span class="staff-category">
                                        <?= staffEsc($item['kategori']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($item['status'] === 'Selesai'): ?>
                                        <span class="staff-status staff-status-done">
                                            Selesai
                                        </span>
                                    <?php else: ?>
                                        <span class="staff-status staff-status-process">
                                            Dalam Proses
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="staff-card-footer">
            Seluruh nama, jumlah tugas, dan aktivitas pada halaman ini
            merupakan data dummy untuk kebutuhan tampilan.
        </div>
    </section>

</div>

<?php layoutBawah(); ?>