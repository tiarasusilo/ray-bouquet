<?php
session_start();

require __DIR__ . '/koneksi.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/_layout.php';

if (!isset($_SESSION['id_akun'])) {
    header('Location: login.php');
    exit;
}

if (!hasPrivilege('manage_role')) {
    http_response_code(403);
    exit('Kamu tidak punya akses ke halaman ini.');
}

$roles = [
    'admin'   => 'Admin',
    'manager' => 'Manager',
    'staff'   => 'Staff',
    'buyer'   => 'Buyer'
];

$roleAktif = $_GET['role'] ?? 'admin';

if (!array_key_exists($roleAktif, $roles)) {
    $roleAktif = 'admin';
}

$pesan = '';
$error = '';

/* ==============================
   SIMPAN PRIVILEGE
============================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $role = $_POST['tipe_akun'] ?? '';
    $privileges = $_POST['privileges'] ?? [];

    if (!array_key_exists($role, $roles)) {
        $error = 'Role tidak valid.';
    } else {
        try {
            $pdo_oas->beginTransaction();

            // Hapus privilege lama
            $stmtDelete = $pdo_oas->prepare("
                DELETE FROM role_privilege
                WHERE tipe_akun = ?
            ");

            $stmtDelete->execute([$role]);

            // Tambahkan privilege baru
            if (!empty($privileges)) {

                $stmtInsert = $pdo_oas->prepare("
                    INSERT INTO role_privilege
                    (tipe_akun, id_privilege)
                    VALUES (?, ?)
                ");

                foreach ($privileges as $idPrivilege) {
                    $stmtInsert->execute([
                        $role,
                        (int) $idPrivilege
                    ]);
                }
            }

            $pdo_oas->commit();

            $pesan = 'Privilege untuk role ' . $roles[$role] . ' berhasil diperbarui.';
            $roleAktif = $role;

        } catch (PDOException $e) {

            if ($pdo_oas->inTransaction()) {
                $pdo_oas->rollBack();
            }

            $error = 'Gagal menyimpan privilege.';
        }
    }
}


/* ==============================
   AMBIL SEMUA PRIVILEGE
============================== */

$stmt = $pdo_oas->query("
    SELECT
        id_privilege,
        nama_privilege
    FROM privilege
    ORDER BY id_privilege ASC
");

$privileges = $stmt->fetchAll();


/* ==============================
   AMBIL PRIVILEGE ROLE AKTIF
============================== */

$stmt = $pdo_oas->prepare("
    SELECT id_privilege
    FROM role_privilege
    WHERE tipe_akun = ?
");

$stmt->execute([$roleAktif]);

$privilegeAktif = array_map(
    'intval',
    $stmt->fetchAll(PDO::FETCH_COLUMN)
);


/* ==============================
   KELOMPOKKAN PRIVILEGE
============================== */

$kelompokPrivilege = [
    'Pengguna & Role' => [
        'manage_user',
        'manage_role'
    ],

    'Produk' => [
        'view_produk',
        'manage_produk'
    ],

    'Pesanan' => [
        'view_pesanan',
        'manage_pesanan'
    ],

    'Inventory' => [
        'view_inventory',
        'manage_inventory'
    ],

    'Keuangan' => [
        'view_keuangan',
        'manage_keuangan'
    ],

    'Laporan' => [
        'view_laporan'
    ],

    'Knowledge & FAQ' => [
        'manage_knowledge',
        'manage_faq'
    ],

    'Dashboard' => [
        'dashboard'
    ]
];

function namaPrivilege($nama)
{
    return ucwords(str_replace('_', ' ', $nama));
}

function cariPrivilege($privileges, $nama)
{
    foreach ($privileges as $privilege) {
        if ($privilege['nama_privilege'] === $nama) {
            return $privilege;
        }
    }

    return null;
}

layoutAtas(
    'Role & Privilege',
    'Atur hak akses berdasarkan role pengguna',
    'role',
    ['role.css']
);
?>

<div class="role-page">

    <?php if ($pesan): ?>
        <div class="flash flash-ok">
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="flash flash-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>


    <!-- ROLE SELECTOR -->
    <div class="role-selector">

        <div class="section-heading">
            <div>
                <h2>Pilih Role</h2>
                <p>Pilih role untuk mengatur privilege yang dimilikinya.</p>
            </div>
        </div>

        <div class="role-cards">

            <?php foreach ($roles as $role => $label): ?>

                <a
                    href="role.php?role=<?= urlencode($role) ?>"
                    class="role-card <?= $roleAktif === $role ? 'active' : '' ?>"
                >

                    <div class="role-card-icon">
                        <?= strtoupper(substr($label, 0, 1)) ?>
                    </div>

                    <div class="role-card-info">
                        <strong><?= htmlspecialchars($label) ?></strong>

                        <span>
                            <?php
                            $stmtCount = $pdo_oas->prepare("
                                SELECT COUNT(*)
                                FROM role_privilege
                                WHERE tipe_akun = ?
                            ");
                            $stmtCount->execute([$role]);
                            echo $stmtCount->fetchColumn();
                            ?>
                            privilege
                        </span>
                    </div>

                </a>

            <?php endforeach; ?>

        </div>
    </div>


    <!-- PRIVILEGE -->
    <form method="POST" class="privilege-form">

        <input
            type="hidden"
            name="tipe_akun"
            value="<?= htmlspecialchars($roleAktif) ?>"
        >

        <div class="privilege-header">

            <div>
                <span class="current-role">
                    ROLE TERPILIH
                </span>

                <h2>
                    <?= htmlspecialchars($roles[$roleAktif]) ?>
                </h2>

                <p>
                    Centang privilege yang boleh digunakan oleh role ini.
                </p>
            </div>

            <div class="privilege-total">
                <strong id="selectedCount">
                    <?= count($privilegeAktif) ?>
                </strong>
                <span>Privilege aktif</span>
            </div>

        </div>


        <div class="privilege-groups">

            <?php foreach ($kelompokPrivilege as $namaKelompok => $items): ?>

                <section class="privilege-group">

                    <div class="group-title">
                        <h3><?= htmlspecialchars($namaKelompok) ?></h3>
                    </div>

                    <div class="privilege-list">

                        <?php foreach ($items as $nama): ?>

                            <?php
                            $privilege = cariPrivilege($privileges, $nama);

                            if (!$privilege) {
                                continue;
                            }

                            $id = (int) $privilege['id_privilege'];
                            $checked = in_array($id, $privilegeAktif, true);
                            ?>

                            <label class="privilege-item">

                                <input
                                    type="checkbox"
                                    name="privileges[]"
                                    value="<?= $id ?>"
                                    <?= $checked ? 'checked' : '' ?>
                                >

                                <span class="custom-check"></span>

                                <span class="privilege-text">
                                    <strong>
                                        <?= htmlspecialchars(namaPrivilege($nama)) ?>
                                    </strong>

                                    <small>
                                        <?= htmlspecialchars($nama) ?>
                                    </small>
                                </span>

                            </label>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endforeach; ?>

        </div>


        <div class="form-footer">

            <div class="permission-info">
                <strong><?= htmlspecialchars($roles[$roleAktif]) ?></strong>
                <span>
                    Perubahan privilege akan langsung diterapkan pada role ini.
                </span>
            </div>

            <button type="submit" class="btn-save">
                Simpan Privilege
            </button>

        </div>

    </form>

</div>


<script>
const checkboxes = document.querySelectorAll(
    '.privilege-item input[type="checkbox"]'
);

const selectedCount = document.getElementById('selectedCount');

function updateCount() {
    let total = 0;

    checkboxes.forEach(function (checkbox) {
        if (checkbox.checked) {
            total++;
        }
    });

    selectedCount.textContent = total;
}

checkboxes.forEach(function (checkbox) {
    checkbox.addEventListener('change', updateCount);
});

updateCount();
</script>

<?php
layoutBawah();
?>