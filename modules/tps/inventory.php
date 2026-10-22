<?php
session_start();

require __DIR__ . '/_layout.php';
require __DIR__ . '/koneksi.php';
require __DIR__ . '/auth.php';

if (!isset($_SESSION['id_akun'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['tipe_akun'] ?? '';

if (!in_array($role, ['admin', 'manager', 'staff'])) {
    header('Location: dashboard.php');
    exit;
}

if (!hasPrivilege('view_inventory')) {
    header('Location: dashboard.php');
    exit;
}


/* =========================
   PROSES TAMBAH
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_inventory'])) {

    if (!hasPrivilege('manage_inventory')) {
        die('Anda tidak memiliki akses untuk mengubah inventory.');
    }

    $namaItem = trim($_POST['nama_item']);
    $kategori = trim($_POST['kategori']);
    $jumlahStok = (int) $_POST['jumlah_stok'];
    $satuan = trim($_POST['satuan']);
    $stokMinimum = (int) $_POST['stok_minimum'];
    $hargaSatuan = (float) $_POST['harga_satuan'];

    if ($namaItem !== '' && $kategori !== '' && $satuan !== '') {

        $stmt = $pdo->prepare("
            INSERT INTO inventory
            (
                nama_item,
                kategori,
                jumlah_stok,
                satuan,
                stok_minimum,
                harga_satuan
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $namaItem,
            $kategori,
            $jumlahStok,
            $satuan,
            $stokMinimum,
            $hargaSatuan
        ]);
    }

    header('Location: inventory.php');
    exit;
}


/* =========================
   PROSES EDIT
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_inventory'])) {

    if (!hasPrivilege('manage_inventory')) {
        die('Anda tidak memiliki akses untuk mengubah inventory.');
    }

    $id = (int) $_POST['id_inventory'];
    $namaItem = trim($_POST['nama_item']);
    $kategori = trim($_POST['kategori']);
    $jumlahStok = (int) $_POST['jumlah_stok'];
    $satuan = trim($_POST['satuan']);
    $stokMinimum = (int) $_POST['stok_minimum'];
    $hargaSatuan = (float) $_POST['harga_satuan'];

    $stmt = $pdo->prepare("
        UPDATE inventory
        SET
            nama_item = ?,
            kategori = ?,
            jumlah_stok = ?,
            satuan = ?,
            stok_minimum = ?,
            harga_satuan = ?
        WHERE id_inventory = ?
    ");

    $stmt->execute([
        $namaItem,
        $kategori,
        $jumlahStok,
        $satuan,
        $stokMinimum,
        $hargaSatuan,
        $id
    ]);

    header('Location: inventory.php');
    exit;
}


/* =========================
   PROSES HAPUS
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_inventory'])) {

    if (!hasPrivilege('manage_inventory')) {
        die('Anda tidak memiliki akses untuk mengubah inventory.');
    }

    $id = (int) $_POST['id_inventory'];

    $stmt = $pdo->prepare("
        DELETE FROM inventory
        WHERE id_inventory = ?
    ");

    $stmt->execute([$id]);

    header('Location: inventory.php');
    exit;
}


/* =========================
   DATA INVENTORY
========================= */

$stmt = $pdo->query("
    SELECT *
    FROM inventory
    ORDER BY
        CASE
            WHEN jumlah_stok = 0 THEN 0
            WHEN jumlah_stok <= stok_minimum THEN 1
            ELSE 2
        END,
        nama_item ASC
");

$inventory = $stmt->fetchAll();


/* =========================
   STATISTIK
========================= */

$totalItem = count($inventory);
$totalStok = 0;
$stokMenipis = 0;
$totalNilai = 0;

foreach ($inventory as $item) {

    $stok = (int) $item['jumlah_stok'];
    $minimum = (int) $item['stok_minimum'];
    $harga = (float) $item['harga_satuan'];

    $totalStok += $stok;

    if ($stok <= $minimum) {
        $stokMenipis++;
    }

    $totalNilai += $stok * $harga;
}


layoutAtas(
    'Inventory',
    'Kelola persediaan bahan dan komponen RAY Bouquet',
    'inventory',
    ['inventory.css']
);

?>

<div class="inventory-page">

    <!-- HEADER -->

    <div class="inventory-head">

        <div>
            <h1>Inventory</h1>
            <p>
                Kelola stok bahan dan komponen yang digunakan
                dalam operasional toko.
            </p>
        </div>

        <?php if (hasPrivilege('manage_inventory')): ?>

            <button
                type="button"
                class="btn btn-primary"
                onclick="openModal('modalTambah')"
            >
                + Tambah Item
            </button>

        <?php endif; ?>

    </div>


    <!-- STATISTIK -->

    <div class="stat-row">

        <div class="stat-box">

            <small>Total Item</small>

            <strong>
                <?= number_format($totalItem) ?>
            </strong>

            <div class="stat-desc">
                Jenis komponen
            </div>

        </div>


        <div class="stat-box">

            <small>Total Stok</small>

            <strong>
                <?= number_format($totalStok) ?>
            </strong>

            <div class="stat-desc">
                Seluruh persediaan
            </div>

        </div>


        <div class="stat-box">

            <small>Stok Menipis</small>

            <strong class="warn">
                <?= number_format($stokMenipis) ?>
            </strong>

            <div class="stat-desc">
                Perlu diperhatikan
            </div>

        </div>


        <div class="stat-box">

            <small>Nilai Inventory</small>

            <strong>
                Rp <?= number_format($totalNilai, 0, ',', '.') ?>
            </strong>

            <div class="stat-desc">
                Estimasi nilai stok
            </div>

        </div>

    </div>


    <!-- TABLE -->

    <div class="inventory-card">

        <div class="card-heading">

            <div>
                <h2>Daftar Inventory</h2>

                <p>
                    Data persediaan bahan dan komponen toko.
                </p>
            </div>

        </div>


        <div class="table-wrapper">

            <table class="inventory-tbl">

                <thead>

                    <tr>
                        <th>No</th>
                        <th>Nama Item</th>
                        <th>Kategori</th>
                        <th>Stok</th>
                        <th>Satuan</th>
                        <th>Minimum</th>
                        <th>Harga Satuan</th>
                        <th>Status</th>

                        <?php if (hasPrivilege('manage_inventory')): ?>
                            <th>Aksi</th>
                        <?php endif; ?>

                    </tr>

                </thead>


                <tbody>

                <?php if (empty($inventory)): ?>

                    <tr>

                        <td
                            colspan="<?= hasPrivilege('manage_inventory') ? 9 : 8 ?>"
                            class="empty-state"
                        >
                            Belum ada data inventory.
                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach ($inventory as $index => $item): ?>

                        <?php

                        $stok = (int) $item['jumlah_stok'];
                        $minimum = (int) $item['stok_minimum'];

                        if ($stok <= 0) {

                            $status = 'Habis';
                            $statusClass = 'habis';

                        } elseif ($stok <= $minimum) {

                            $status = 'Menipis';
                            $statusClass = 'tipis';

                        } else {

                            $status = 'Aman';
                            $statusClass = 'aman';

                        }

                        ?>


                        <tr>

                            <!-- NO -->

                            <td>
                                <?= $index + 1 ?>
                            </td>


                            <!-- NAMA ITEM -->

                            <td>

                                <div class="inventory-name">

                                    <b>
                                        <?= htmlspecialchars($item['nama_item']) ?>
                                    </b>

                                </div>

                            </td>


                            <!-- KATEGORI -->

                            <td>

                                <span class="kat-chip">
                                    <?= htmlspecialchars($item['kategori']) ?>
                                </span>

                            </td>


                            <!-- STOK -->

                            <td>

                                <strong>
                                    <?= number_format($stok) ?>
                                </strong>

                            </td>


                            <!-- SATUAN -->

                            <td>
                                <?= htmlspecialchars($item['satuan']) ?>
                            </td>


                            <!-- MINIMUM -->

                            <td>
                                <?= number_format($minimum) ?>
                            </td>


                            <!-- HARGA -->

                            <td>

                                Rp <?= number_format(
                                    $item['harga_satuan'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span class="stok-pill <?= $statusClass ?>">
                                    <?= $status ?>
                                </span>

                            </td>


                            <!-- AKSI -->

                            <?php if (hasPrivilege('manage_inventory')): ?>

                                <td>

                                    <div class="row-aksi">

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-ghost"
                                            onclick='openEditModal(<?= json_encode($item) ?>)'
                                        >
                                            Edit
                                        </button>


                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus item ini?')"
                                        >

                                            <input
                                                type="hidden"
                                                name="id_inventory"
                                                value="<?= $item['id_inventory'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="hapus_inventory"
                                                class="btn btn-sm btn-danger"
                                            >
                                                Hapus
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            <?php endif; ?>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<!-- =========================
     MODAL TAMBAH
========================= -->

<?php if (hasPrivilege('manage_inventory')): ?>

<div class="modal" id="modalTambah">

    <div class="modal-box">

        <div class="modal-header">

            <div>

                <h2>Tambah Inventory</h2>

                <p>
                    Tambahkan bahan atau komponen baru.
                </p>

            </div>


            <button
                type="button"
                class="modal-close"
                onclick="closeModal('modalTambah')"
            >
                ×
            </button>

        </div>


        <form method="POST">

            <div class="form-grid">

                <div class="form-group full">

                    <label>Nama Item</label>

                    <input
                        type="text"
                        name="nama_item"
                        placeholder="Contoh: Mawar Merah"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Kategori</label>

                    <select name="kategori" required>

                        <option value="">
                            Pilih kategori
                        </option>

                        <option value="Bunga">
                            Bunga
                        </option>

                        <option value="Packaging">
                            Packaging
                        </option>

                        <option value="Bahan">
                            Bahan
                        </option>

                        <option value="Aksesoris">
                            Aksesoris
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>Satuan</label>

                    <input
                        type="text"
                        name="satuan"
                        placeholder="pcs / tangkai / roll"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Jumlah Stok</label>

                    <input
                        type="number"
                        name="jumlah_stok"
                        min="0"
                        value="0"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Stok Minimum</label>

                    <input
                        type="number"
                        name="stok_minimum"
                        min="0"
                        value="0"
                        required
                    >

                </div>


                <div class="form-group full">

                    <label>Harga Satuan</label>

                    <input
                        type="number"
                        name="harga_satuan"
                        min="0"
                        value="0"
                    >

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
                    name="tambah_inventory"
                    class="btn btn-primary"
                >
                    Simpan Item
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================
     MODAL EDIT
========================= -->

<div class="modal" id="modalEdit">

    <div class="modal-box">

        <div class="modal-header">

            <div>

                <h2>Edit Inventory</h2>

                <p>
                    Perbarui data persediaan.
                </p>

            </div>


            <button
                type="button"
                class="modal-close"
                onclick="closeModal('modalEdit')"
            >
                ×
            </button>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="id_inventory"
                id="edit_id"
            >


            <div class="form-grid">

                <div class="form-group full">

                    <label>Nama Item</label>

                    <input
                        type="text"
                        name="nama_item"
                        id="edit_nama"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Kategori</label>

                    <select
                        name="kategori"
                        id="edit_kategori"
                        required
                    >

                        <option value="Bunga">
                            Bunga
                        </option>

                        <option value="Packaging">
                            Packaging
                        </option>

                        <option value="Bahan">
                            Bahan
                        </option>

                        <option value="Aksesoris">
                            Aksesoris
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>Satuan</label>

                    <input
                        type="text"
                        name="satuan"
                        id="edit_satuan"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Jumlah Stok</label>

                    <input
                        type="number"
                        name="jumlah_stok"
                        id="edit_stok"
                        min="0"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Stok Minimum</label>

                    <input
                        type="number"
                        name="stok_minimum"
                        id="edit_minimum"
                        min="0"
                        required
                    >

                </div>


                <div class="form-group full">

                    <label>Harga Satuan</label>

                    <input
                        type="number"
                        name="harga_satuan"
                        id="edit_harga"
                        min="0"
                        required
                    >

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
                    name="edit_inventory"
                    class="btn btn-primary"
                >
                    Simpan Perubahan
                </button>

            </div>

        </form>

    </div>

</div>


<script>

function openModal(id) {

    document
        .getElementById(id)
        .classList.add('show');

}


function closeModal(id) {

    document
        .getElementById(id)
        .classList.remove('show');

}


function openEditModal(item) {

    document.getElementById('edit_id').value =
        item.id_inventory;

    document.getElementById('edit_nama').value =
        item.nama_item;

    document.getElementById('edit_kategori').value =
        item.kategori;

    document.getElementById('edit_satuan').value =
        item.satuan;

    document.getElementById('edit_stok').value =
        item.jumlah_stok;

    document.getElementById('edit_minimum').value =
        item.stok_minimum;

    document.getElementById('edit_harga').value =
        item.harga_satuan;

    openModal('modalEdit');

}


window.onclick = function(event) {

    if (event.target.classList.contains('modal')) {

        event.target.classList.remove('show');

    }

};

</script>

<?php endif; ?>


<?php layoutBawah(); ?>