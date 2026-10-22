<?php
session_start();

require __DIR__ . '/koneksi.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/_layout.php';

if (!isset($_SESSION['tipe_akun'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['tipe_akun'];

if (!hasPrivilege('view_keuangan')) {
    die('Anda tidak memiliki akses ke halaman keuangan.');
}

$canManage = hasPrivilege('manage_keuangan');

/* =========================================================
   PROSES TAMBAH
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {

    $aksi = $_POST['aksi'];

    /* ================= TAMBAH ================= */
    if ($aksi === 'tambah' && $canManage) {

        $tanggal    = $_POST['tanggal'];
        $jenis      = $_POST['jenis'];
        $kategori   = trim($_POST['kategori']);
        $keterangan = trim($_POST['keterangan']);
        $nominal    = (float) $_POST['nominal'];

        $idAkun = $_SESSION['id_akun'] ?? null;

        if (!$idAkun) {
            die('Session akun tidak ditemukan.');
        }

        $stmt = $pdo->prepare("
            INSERT INTO keuangan
            (tanggal, jenis, kategori, keterangan, nominal, status, id_akun)
            VALUES (?, ?, ?, ?, ?, 'Menunggu', ?)
        ");

        $stmt->execute([
            $tanggal,
            $jenis,
            $kategori,
            $keterangan,
            $nominal,
            $idAkun
        ]);

        header('Location: keuangan.php?success=tambah');
        exit;
    }

    /* ================= EDIT ================= */
    if ($aksi === 'edit' && $canManage) {

        $id         = (int) $_POST['id_keuangan'];
        $tanggal    = $_POST['tanggal'];
        $jenis      = $_POST['jenis'];
        $kategori   = trim($_POST['kategori']);
        $keterangan = trim($_POST['keterangan']);
        $nominal    = (float) $_POST['nominal'];

        $stmt = $pdo->prepare("
            UPDATE keuangan
            SET tanggal = ?,
                jenis = ?,
                kategori = ?,
                keterangan = ?,
                nominal = ?
            WHERE id_keuangan = ?
        ");

        $stmt->execute([
            $tanggal,
            $jenis,
            $kategori,
            $keterangan,
            $nominal,
            $id
        ]);

        header('Location: keuangan.php?success=edit');
        exit;
    }

    /* ================= HAPUS ================= */
    if ($aksi === 'hapus' && $canManage) {

        $id = (int) $_POST['id_keuangan'];

        $stmt = $pdo->prepare("
            DELETE FROM keuangan
            WHERE id_keuangan = ?
        ");

        $stmt->execute([$id]);

        header('Location: keuangan.php?success=hapus');
        exit;
    }

    /* ================= APPROVE ================= */
    if ($aksi === 'approve' && ($role === 'manager' || $role === 'admin')) {

        $id = (int) $_POST['id_keuangan'];
        $catatan = trim($_POST['catatan_manager'] ?? '');

        $idManager = $_SESSION['id_akun'] ?? null;

        $stmt = $pdo->prepare("
            UPDATE keuangan
            SET status = 'Disetujui',
                catatan_manager = ?,
                tanggal_approval = NOW(),
                id_manager = ?
            WHERE id_keuangan = ?
        ");

        $stmt->execute([
            $catatan,
            $idManager,
            $id
        ]);

        header('Location: keuangan.php?success=approve');
        exit;
    }

    /* ================= TOLAK ================= */
    if ($aksi === 'tolak' && ($role === 'manager' || $role === 'admin')) {

        $id = (int) $_POST['id_keuangan'];
        $catatan = trim($_POST['catatan_manager'] ?? '');

        $idManager = $_SESSION['id_akun'] ?? null;

        $stmt = $pdo->prepare("
            UPDATE keuangan
            SET status = 'Ditolak',
                catatan_manager = ?,
                tanggal_approval = NOW(),
                id_manager = ?
            WHERE id_keuangan = ?
        ");

        $stmt->execute([
            $catatan,
            $idManager,
            $id
        ]);

        header('Location: keuangan.php?success=tolak');
        exit;
    }
}


/* =========================================================
   STATISTIK
========================================================= */

$stmt = $pdo->query("
    SELECT
        COALESCE(SUM(CASE
            WHEN jenis = 'Pemasukan'
            AND status = 'Disetujui'
            THEN nominal ELSE 0 END), 0) AS pemasukan,

        COALESCE(SUM(CASE
            WHEN jenis = 'Pengeluaran'
            AND status = 'Disetujui'
            THEN nominal ELSE 0 END), 0) AS pengeluaran,

        COALESCE(SUM(CASE
            WHEN jenis = 'Pemasukan'
            AND status = 'Disetujui'
            THEN nominal
            WHEN jenis = 'Pengeluaran'
            AND status = 'Disetujui'
            THEN -nominal
            ELSE 0
        END), 0) AS saldo,

        SUM(CASE
            WHEN status = 'Menunggu'
            THEN 1 ELSE 0 END) AS menunggu
    FROM keuangan
");

$stats = $stmt->fetch();

$totalPemasukan  = $stats['pemasukan'];
$totalPengeluaran = $stats['pengeluaran'];
$saldo = $stats['saldo'];
$totalMenunggu = $stats['menunggu'];


/* =========================================================
   DATA GRAFIK
========================================================= */

$stmt = $pdo->query("
    SELECT
        DATE_FORMAT(tanggal, '%Y-%m') AS bulan,

        SUM(CASE
            WHEN jenis = 'Pemasukan'
            THEN nominal ELSE 0 END) AS pemasukan,

        SUM(CASE
            WHEN jenis = 'Pengeluaran'
            THEN nominal ELSE 0 END) AS pengeluaran

    FROM keuangan
    WHERE status = 'Disetujui'

    GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
    ORDER BY bulan
");

$chartData = $stmt->fetchAll();

$chartLabels = [];
$chartIncome = [];
$chartExpense = [];

foreach ($chartData as $row) {
    $chartLabels[] = date('M Y', strtotime($row['bulan'] . '-01'));
    $chartIncome[] = (float) $row['pemasukan'];
    $chartExpense[] = (float) $row['pengeluaran'];
}


/* =========================================================
   DATA TRANSAKSI
========================================================= */

$stmt = $pdo->query("
    SELECT
        k.*,
        a.nama AS nama_akun
    FROM keuangan k
    LEFT JOIN ray_bouquet_oas.akun a
        ON k.id_akun = a.id_akun
    ORDER BY k.tanggal DESC, k.id_keuangan DESC
");

$transaksi = $stmt->fetchAll();


function rupiah($nominal)
{
    return 'Rp ' . number_format(
        $nominal,
        0,
        ',',
        '.'
    );
}

function statusClass($status)
{
    switch ($status) {
        case 'Disetujui':
            return 'disetujui';

        case 'Ditolak':
            return 'ditolak';

        default:
            return 'menunggu';
    }
}

function jenisClass($jenis)
{
    return $jenis === 'Pemasukan'
        ? 'masuk'
        : 'keluar';
}

layoutAtas(
    'Keuangan',
    'Kelola pemasukan dan pengeluaran RAY Bouquet',
    'keuangan.php',
    ['keuangan.css']
);
?>

<div class="keuangan-page">

    <!-- HEADER -->
    <div class="keuangan-head">

        <div>
            <h1>Keuangan</h1>
            <p>
                Kelola pemasukan, pengeluaran, dan persetujuan transaksi.
            </p>
        </div>

        <?php if ($canManage): ?>
            <button
                class="btn btn-primary"
                onclick="openModal('modalTambah')"
            >
                + Tambah Transaksi
            </button>
        <?php endif; ?>

    </div>


    <!-- STATISTIK -->
    <div class="stat-row">

        <div class="stat-box">
            <div class="stat-label">Total Pemasukan</div>

            <div class="stat-value income">
                <?= rupiah($totalPemasukan) ?>
            </div>

            <div class="stat-desc">
                Transaksi disetujui
            </div>
        </div>


        <div class="stat-box">
            <div class="stat-label">Total Pengeluaran</div>

            <div class="stat-value expense">
                <?= rupiah($totalPengeluaran) ?>
            </div>

            <div class="stat-desc">
                Transaksi disetujui
            </div>
        </div>


        <div class="stat-box">
            <div class="stat-label">Saldo</div>

            <div class="stat-value balance">
                <?= rupiah($saldo) ?>
            </div>

            <div class="stat-desc">
                Pemasukan - pengeluaran
            </div>
        </div>


        <div class="stat-box">
            <div class="stat-label">Menunggu Approval</div>

            <div class="stat-value pending">
                <?= $totalMenunggu ?>
            </div>

            <div class="stat-desc">
                Transaksi perlu diperiksa
            </div>
        </div>

    </div>


    <!-- GRAFIK -->
    <div class="finance-card chart-card">

        <div class="card-heading">

            <div>
                <h2>Arus Keuangan</h2>
                <p>Pemasukan dan pengeluaran yang telah disetujui.</p>
            </div>

        </div>

        <?php if (count($chartData) > 0): ?>

            <div class="chart-wrapper">
                <canvas id="financeChart"></canvas>
            </div>

        <?php else: ?>

            <div class="empty-chart">
                Belum ada data keuangan yang disetujui.
            </div>

        <?php endif; ?>

    </div>


    <!-- TRANSAKSI -->
    <div class="finance-card">

        <div class="card-heading">

            <div>
                <h2>Daftar Transaksi</h2>
                <p>
                    Riwayat pemasukan dan pengeluaran RAY Bouquet.
                </p>
            </div>

        </div>


        <div class="table-wrapper">

            <table class="finance-tbl">

                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Kategori</th>
                        <th>Keterangan</th>
                        <th>Nominal</th>
                        <th>Dibuat Oleh</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($transaksi) > 0): ?>

                    <?php foreach ($transaksi as $item): ?>

                        <tr>

                            <td>
                                <?= date(
                                    'd M Y',
                                    strtotime($item['tanggal'])
                                ) ?>
                            </td>


                            <td>
                                <span class="jenis-pill <?= jenisClass($item['jenis']) ?>">
                                    <?= htmlspecialchars($item['jenis']) ?>
                                </span>
                            </td>


                            <td>
                                <span class="kat-chip">
                                    <?= htmlspecialchars($item['kategori']) ?>
                                </span>
                            </td>


                            <td class="keterangan">
                                <?= htmlspecialchars($item['keterangan']) ?>
                            </td>


                            <td class="nominal <?= jenisClass($item['jenis']) ?>">

                                <?= rupiah($item['nominal']) ?>

                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $item['nama_akun'] ?? '-'
                                ) ?>
                            </td>


                            <td>

                                <span class="status-pill <?= statusClass($item['status']) ?>">
                                    <?= htmlspecialchars($item['status']) ?>
                                </span>

                            </td>


                            <td>

                                <div class="row-aksi">

                                    <?php if (
                                        $canManage &&
                                        $item['status'] === 'Menunggu'
                                    ): ?>

                                        <button
                                            class="btn btn-sm btn-ghost"
                                            onclick='openEditModal(<?= json_encode($item) ?>)'
                                        >
                                            Edit
                                        </button>

                                        <form method="POST"
                                              onsubmit="return confirm('Hapus transaksi ini?')">

                                            <input
                                                type="hidden"
                                                name="aksi"
                                                value="hapus"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_keuangan"
                                                value="<?= $item['id_keuangan'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-danger"
                                            >
                                                Hapus
                                            </button>

                                        </form>

                                    <?php endif; ?>


                                    <?php if (
                                        ($role === 'manager' || $role === 'admin') &&
                                        $item['status'] === 'Menunggu'
                                    ): ?>

                                        <button
                                            class="btn btn-sm btn-approve"
                                            onclick="openApprovalModal(
                                                <?= $item['id_keuangan'] ?>,
                                                'approve'
                                            )"
                                        >
                                            Setujui
                                        </button>

                                        <button
                                            class="btn btn-sm btn-reject"
                                            onclick="openApprovalModal(
                                                <?= $item['id_keuangan'] ?>,
                                                'tolak'
                                            )"
                                        >
                                            Tolak
                                        </button>

                                    <?php endif; ?>


                                    <?php if (
                                        $item['status'] !== 'Menunggu' &&
                                        $item['catatan_manager']
                                    ): ?>

                                        <button
                                            class="btn btn-sm btn-ghost"
                                            onclick="showNote(
                                                <?= json_encode($item['catatan_manager']) ?>
                                            )"
                                        >
                                            Catatan
                                        </button>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8">

                            <div class="empty-state">
                                Belum ada transaksi keuangan.
                            </div>

                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<!-- =====================================================
     MODAL TAMBAH
===================================================== -->

<?php if ($canManage): ?>

<div class="modal" id="modalTambah">

    <div class="modal-box">

        <div class="modal-header">

            <div>
                <h2>Tambah Transaksi</h2>
                <p>Masukkan data pemasukan atau pengeluaran.</p>
            </div>

            <button
                class="modal-close"
                onclick="closeModal('modalTambah')"
            >
                ×
            </button>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="aksi"
                value="tambah"
            >

            <div class="form-grid">

                <div class="form-group">

                    <label>Tanggal</label>

                    <input
                        type="date"
                        name="tanggal"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Jenis</label>

                    <select name="jenis" required>

                        <option value="Pemasukan">
                            Pemasukan
                        </option>

                        <option value="Pengeluaran">
                            Pengeluaran
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>Kategori</label>

                    <input
                        type="text"
                        name="kategori"
                        placeholder="Contoh: Penjualan"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Nominal</label>

                    <input
                        type="number"
                        name="nominal"
                        min="0"
                        step="1000"
                        placeholder="500000"
                        required
                    >

                </div>


                <div class="form-group full">

                    <label>Keterangan</label>

                    <textarea
                        name="keterangan"
                        rows="3"
                        placeholder="Keterangan transaksi..."
                        required
                    ></textarea>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-ghost"
                    onclick="closeModal('modalTambah')"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Simpan
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =====================================================
     MODAL EDIT
===================================================== -->

<div class="modal" id="modalEdit">

    <div class="modal-box">

        <div class="modal-header">

            <div>
                <h2>Edit Transaksi</h2>
                <p>Ubah data transaksi.</p>
            </div>

            <button
                class="modal-close"
                onclick="closeModal('modalEdit')"
            >
                ×
            </button>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="aksi"
                value="edit"
            >

            <input
                type="hidden"
                name="id_keuangan"
                id="edit_id"
            >

            <div class="form-grid">

                <div class="form-group">

                    <label>Tanggal</label>

                    <input
                        type="date"
                        name="tanggal"
                        id="edit_tanggal"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Jenis</label>

                    <select
                        name="jenis"
                        id="edit_jenis"
                        required
                    >

                        <option value="Pemasukan">
                            Pemasukan
                        </option>

                        <option value="Pengeluaran">
                            Pengeluaran
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>Kategori</label>

                    <input
                        type="text"
                        name="kategori"
                        id="edit_kategori"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Nominal</label>

                    <input
                        type="number"
                        name="nominal"
                        id="edit_nominal"
                        min="0"
                        step="1000"
                        required
                    >

                </div>


                <div class="form-group full">

                    <label>Keterangan</label>

                    <textarea
                        name="keterangan"
                        id="edit_keterangan"
                        rows="3"
                        required
                    ></textarea>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-ghost"
                    onclick="closeModal('modalEdit')"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Simpan Perubahan
                </button>

            </div>

        </form>

    </div>

</div>

<?php endif; ?>


<!-- =====================================================
     MODAL APPROVAL
===================================================== -->

<?php if ($role === 'manager' || $role === 'admin'): ?>

<div class="modal" id="modalApproval">

    <div class="modal-box">

        <div class="modal-header">

            <div>
                <h2 id="approvalTitle">
                    Approval Transaksi
                </h2>

                <p>
                    Berikan catatan jika diperlukan.
                </p>
            </div>

            <button
                class="modal-close"
                onclick="closeModal('modalApproval')"
            >
                ×
            </button>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="aksi"
                id="approvalAksi"
            >

            <input
                type="hidden"
                name="id_keuangan"
                id="approvalId"
            >

            <div class="form-group">

                <label>Catatan Manager</label>

                <textarea
                    name="catatan_manager"
                    rows="4"
                    placeholder="Tambahkan catatan..."
                ></textarea>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-ghost"
                    onclick="closeModal('modalApproval')"
                >
                    Batal
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                    id="approvalButton"
                >
                    Simpan
                </button>

            </div>

        </form>

    </div>

</div>

<?php endif; ?>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}


/* EDIT */

function openEditModal(item) {

    document.getElementById('edit_id').value =
        item.id_keuangan;

    document.getElementById('edit_tanggal').value =
        item.tanggal;

    document.getElementById('edit_jenis').value =
        item.jenis;

    document.getElementById('edit_kategori').value =
        item.kategori;

    document.getElementById('edit_nominal').value =
        item.nominal;

    document.getElementById('edit_keterangan').value =
        item.keterangan;

    openModal('modalEdit');
}


/* APPROVAL */

function openApprovalModal(id, aksi) {

    document.getElementById('approvalId').value = id;

    document.getElementById('approvalAksi').value = aksi;

    if (aksi === 'approve') {

        document.getElementById('approvalTitle').innerText =
            'Setujui Transaksi';

        document.getElementById('approvalButton').innerText =
            'Setujui';

    } else {

        document.getElementById('approvalTitle').innerText =
            'Tolak Transaksi';

        document.getElementById('approvalButton').innerText =
            'Tolak';

    }

    openModal('modalApproval');
}


/* CATATAN */

function showNote(note) {

    alert(
        'Catatan Manager:\n\n' + note
    );

}


/* CLOSE MODAL */

window.onclick = function(event) {

    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }

};


/* CHART */

<?php if (count($chartData) > 0): ?>

const ctx = document
    .getElementById('financeChart')
    .getContext('2d');

new Chart(ctx, {

    type: 'bar',

    data: {

        labels: <?= json_encode($chartLabels) ?>,

        datasets: [

            {
                label: 'Pemasukan',
                data: <?= json_encode($chartIncome) ?>,

                backgroundColor: '#D8973C',
                borderColor: '#D8973C',

                borderWidth: 2,
                borderRadius: 8
            },

            {
                label: 'Pengeluaran',
                data: <?= json_encode($chartExpense) ?>,

                backgroundColor: '#AD2831',
                borderColor: '#AD2831',

                borderWidth: 2,
                borderRadius: 8
            }

        ]

    },

    options: {

        responsive: true,

        maintainAspectRatio: false,

        plugins: {

            legend: {
                position: 'top'
            }

        },

        scales: {

            y: {

                beginAtZero: true,

                ticks: {

                    callback: function(value) {

                        return 'Rp ' +
                            value.toLocaleString('id-ID');

                    }

                }

            }

        }

    }

});

<?php endif; ?>

</script>

<?php
layoutBawah();
?>