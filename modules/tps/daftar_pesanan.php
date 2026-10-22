<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

require __DIR__ . '/koneksi.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/_layout.php';

if (!isset($_SESSION['id_akun'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['tipe_akun'] ?? '';

if (!in_array($role, ['admin', 'manager', 'staff'])) {
    http_response_code(403);
    exit('Kamu tidak punya akses ke halaman ini.');
}

if (!hasPrivilege('view_pesanan')) {
    http_response_code(403);
    exit('Kamu tidak punya akses ke halaman ini.');
}


/* ==============================
   HELPER
============================== */

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rp($value)
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function formatTanggal($tanggal)
{
    if (!$tanggal) {
        return '-';
    }

    return date('d M Y', strtotime($tanggal));
}


/* ==============================
   STATUS
============================== */

$statusList = [
    'menunggu' => [
        'label' => 'Menunggu',
        'class' => 'waiting'
    ],

    'diproses' => [
        'label' => 'Diproses',
        'class' => 'process'
    ],

    'dirangkai' => [
        'label' => 'Dirangkai',
        'class' => 'arrange'
    ],

    'dikirim' => [
        'label' => 'Dikirim',
        'class' => 'shipping'
    ],

    'selesai' => [
        'label' => 'Selesai',
        'class' => 'done'
    ]
];

$statusOrder = [
    'menunggu',
    'diproses',
    'dirangkai',
    'dikirim',
    'selesai'
];


/* ==============================
   UPDATE STATUS
============================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'ubah_status' && hasPrivilege('manage_pesanan')) {

        $idPesanan = (int) ($_POST['id_pesanan'] ?? 0);
        $statusBaru = $_POST['status_pesanan'] ?? '';

        if (
            $idPesanan > 0 &&
            array_key_exists($statusBaru, $statusList)
        ) {

            try {

                $stmt = $pdo->prepare("
                    UPDATE pesanan
                    SET status_pesanan = ?
                    WHERE id_pesanan = ?
                ");

                $stmt->execute([
                    $statusBaru,
                    $idPesanan
                ]);

                header(
                    'Location: daftar_pesanan.php?status=' .
                    urlencode($_GET['status'] ?? 'all') .
                    '&success=1'
                );
                exit;

            } catch (PDOException $e) {

                header(
                    'Location: daftar_pesanan.php?error=1'
                );
                exit;
            }
        }
    }
}


/* ==============================
   FILTER
============================== */

$filterStatus = $_GET['status'] ?? 'all';

if (
    $filterStatus !== 'all' &&
    !array_key_exists($filterStatus, $statusList)
) {
    $filterStatus = 'all';
}


/* ==============================
   AMBIL PESANAN
============================== */

$sql = "
    SELECT
        p.id_pesanan,
        p.nomor_pesanan,
        p.id_akun,
        p.total_bayar,
        p.status_pesanan,
        p.tanggal_kirim,

        a.nama AS nama_buyer,
        a.username AS username_buyer

    FROM pesanan p

    LEFT JOIN ray_bouquet_oas.akun a
        ON a.id_akun = p.id_akun
";

$params = [];

if ($filterStatus !== 'all') {

    $sql .= "
        WHERE p.status_pesanan = ?
    ";

    $params[] = $filterStatus;
}

$sql .= "
    ORDER BY p.id_pesanan DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$pesanan = $stmt->fetchAll();


/* ==============================
   JUMLAH PER STATUS
============================== */

$jumlahStatus = [
    'all' => 0,
    'menunggu' => 0,
    'diproses' => 0,
    'dirangkai' => 0,
    'dikirim' => 0,
    'selesai' => 0
];

$stmtCount = $pdo->query("
    SELECT status_pesanan, COUNT(*) AS jumlah
    FROM pesanan
    GROUP BY status_pesanan
");

foreach ($stmtCount->fetchAll() as $row) {

    $status = $row['status_pesanan'];

    if (isset($jumlahStatus[$status])) {
        $jumlahStatus[$status] = (int) $row['jumlah'];
    }

    $jumlahStatus['all'] += (int) $row['jumlah'];
}


/* ==============================
   TOTAL OMZET
============================== */

$totalOmzet = 0;

$stmtOmzet = $pdo->query("
    SELECT COALESCE(SUM(total_bayar), 0)
    FROM pesanan
    WHERE status_pesanan = 'selesai'
");

$totalOmzet = $stmtOmzet->fetchColumn();


layoutAtas(
    'Daftar Pesanan',
    'Kelola dan pantau seluruh pesanan pelanggan',
    'pesanan',
    ['daftar_pesanan.css']
);
?>


<div class="order-page">

    <?php if (isset($_GET['success'])): ?>
        <div class="flash flash-success">
            Status pesanan berhasil diperbarui.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="flash flash-error">
            Status pesanan gagal diperbarui.
        </div>
    <?php endif; ?>


    <!-- =========================
         RINGKASAN
    ========================== -->

    <div class="order-stats">

        <div class="order-stat">
            <div class="stat-icon">#</div>

            <div>
                <span>Total Pesanan</span>
                <strong><?= $jumlahStatus['all'] ?></strong>
            </div>
        </div>


        <div class="order-stat waiting-stat">
            <div class="stat-icon">!</div>

            <div>
                <span>Menunggu</span>
                <strong><?= $jumlahStatus['menunggu'] ?></strong>
            </div>
        </div>


        <div class="order-stat process-stat">
            <div class="stat-icon">↻</div>

            <div>
                <span>Diproses</span>
                <strong>
                    <?= $jumlahStatus['diproses'] + $jumlahStatus['dirangkai'] ?>
                </strong>
            </div>
        </div>


        <div class="order-stat done-stat">
            <div class="stat-icon">✓</div>

            <div>
                <span>Selesai</span>
                <strong><?= $jumlahStatus['selesai'] ?></strong>
            </div>
        </div>

    </div>


    <!-- =========================
         FILTER
    ========================== -->

    <div class="order-filter">

        <div class="filter-title">
            <strong>Status Pesanan</strong>
            <span>
                <?= $jumlahStatus['all'] ?> pesanan
            </span>
        </div>

        <div class="filter-buttons">

            <a
                href="daftar_pesanan.php?status=all"
                class="filter-btn <?= $filterStatus === 'all' ? 'active' : '' ?>"
            >
                Semua
                <b><?= $jumlahStatus['all'] ?></b>
            </a>


            <?php foreach ($statusList as $status => $data): ?>

                <a
                    href="daftar_pesanan.php?status=<?= urlencode($status) ?>"
                    class="filter-btn <?= $filterStatus === $status ? 'active' : '' ?>"
                >
                    <?= e($data['label']) ?>
                    <b><?= $jumlahStatus[$status] ?></b>
                </a>

            <?php endforeach; ?>

        </div>

    </div>


    <!-- =========================
         LIST PESANAN
    ========================== -->

    <div class="order-panel">

        <div class="panel-head">

            <div>
                <h2>Daftar Pesanan</h2>

                <p>
                    <?php if ($filterStatus === 'all'): ?>
                        Menampilkan seluruh pesanan pelanggan.
                    <?php else: ?>
                        Menampilkan pesanan dengan status
                        <strong>
                            <?= e($statusList[$filterStatus]['label']) ?>
                        </strong>.
                    <?php endif; ?>
                </p>
            </div>

        </div>


        <?php if (empty($pesanan)): ?>

            <div class="empty-order">

                <div class="empty-icon">
                    □
                </div>

                <h3>Belum ada pesanan</h3>

                <p>
                    Belum ada pesanan dengan status yang dipilih.
                </p>

            </div>

        <?php else: ?>

            <div class="order-table-wrap">

                <table class="order-table">

                    <thead>
                        <tr>
                            <th>Pesanan</th>
                            <th>Pelanggan</th>
                            <th>Tanggal Kirim</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($pesanan as $item): ?>

                        <?php
                        $status = $item['status_pesanan'];

                        $statusData = $statusList[$status] ?? [
                            'label' => ucfirst($status),
                            'class' => 'waiting'
                        ];
                        ?>

                        <tr>

                            <!-- NOMOR PESANAN -->
                            <td>

                                <div class="order-number">

                                    <strong>
                                        <?= e($item['nomor_pesanan']) ?>
                                    </strong>

                                    <span>
                                        ID #<?= (int) $item['id_pesanan'] ?>
                                    </span>

                                </div>

                            </td>


                            <!-- BUYER -->
                            <td>

                                <div class="customer">

                                    <div class="customer-avatar">
                                        <?= strtoupper(
                                            substr(
                                                $item['nama_buyer'] ?: 'B',
                                                0,
                                                1
                                            )
                                        ) ?>
                                    </div>

                                    <div>
                                        <strong>
                                            <?= e(
                                                $item['nama_buyer']
                                                ?: 'Buyer'
                                            ) ?>
                                        </strong>

                                        <span>
                                            @<?= e(
                                                $item['username_buyer']
                                                ?: '-'
                                            ) ?>
                                        </span>
                                    </div>

                                </div>

                            </td>


                            <!-- TANGGAL -->
                            <td>

                                <div class="date-info">

                                    <strong>
                                        <?= formatTanggal(
                                            $item['tanggal_kirim']
                                        ) ?>
                                    </strong>

                                </div>

                            </td>


                            <!-- TOTAL -->
                            <td>

                                <strong class="order-price">
                                    <?= rp($item['total_bayar']) ?>
                                </strong>

                            </td>


                            <!-- STATUS -->
                            <td>

                                <span class="status-badge <?= e($statusData['class']) ?>">
                                    <?= e($statusData['label']) ?>
                                </span>

                            </td>


                            <!-- AKSI -->
                            <td>

                                <div class="order-actions">

                                    <a
                                        href="detail_pesanan.php?id=<?= (int) $item['id_pesanan'] ?>"
                                        class="detail-btn"
                                    >
                                        Detail
                                    </a>


                                    <?php if (hasPrivilege('manage_pesanan')): ?>

                                        <button
                                            type="button"
                                            class="status-btn"
                                            onclick="openStatusModal(
                                                <?= (int) $item['id_pesanan'] ?>,
                                                '<?= e($item['nomor_pesanan']) ?>',
                                                '<?= e($status) ?>'
                                            )"
                                        >
                                            Ubah Status
                                        </button>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>


<!-- =========================
     MODAL STATUS
========================== -->

<div class="status-modal" id="statusModal">

    <div class="status-box">

        <button
            type="button"
            class="modal-close"
            onclick="closeStatusModal()"
        >
            ×
        </button>

        <span class="modal-label">
            UPDATE PESANAN
        </span>

        <h2>Ubah Status</h2>

        <p>
            Pesanan
            <strong id="modalOrderNumber"></strong>
        </p>


        <form method="POST">

            <input
                type="hidden"
                name="aksi"
                value="ubah_status"
            >

            <input
                type="hidden"
                name="id_pesanan"
                id="modalOrderId"
            >


            <label class="status-field">

                <span>Status Pesanan</span>

                <select
                    name="status_pesanan"
                    id="modalStatus"
                    required
                >

                    <?php foreach ($statusList as $status => $data): ?>

                        <option value="<?= e($status) ?>">
                            <?= e($data['label']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </label>


            <div class="modal-actions">

                <button
                    type="button"
                    class="cancel-btn"
                    onclick="closeStatusModal()"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    class="save-status-btn"
                >
                    Simpan Status
                </button>

            </div>

        </form>

    </div>

</div>


<script>

function openStatusModal(id, nomor, status)
{
    document.getElementById('modalOrderId').value = id;

    document.getElementById('modalOrderNumber').textContent = nomor;

    document.getElementById('modalStatus').value = status;

    document.getElementById('statusModal').classList.add('show');
}


function closeStatusModal()
{
    document
        .getElementById('statusModal')
        .classList.remove('show');
}


document
    .getElementById('statusModal')
    .addEventListener('click', function (event) {

        if (event.target === this) {
            closeStatusModal();
        }

    });


document.addEventListener('keydown', function (event) {

    if (event.key === 'Escape') {
        closeStatusModal();
    }

});

</script>


<?php
layoutBawah();
?>