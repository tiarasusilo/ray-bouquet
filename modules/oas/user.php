<?php
session_start();

require __DIR__ . '/koneksi.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/_layout.php';

if (!isset($_SESSION['id_akun'])) {
    header('Location: login.php');
    exit;
}

if (!hasPrivilege('manage_user')) {
    http_response_code(403);
    exit('Kamu tidak punya akses ke halaman ini.');
}

/*
|--------------------------------------------------------------------------
| KONFIGURASI
|--------------------------------------------------------------------------
*/
$db = $pdo_oas;   // koneksi ke database yang berisi tabel akun

// false  = password disimpan apa adanya (sama seperti data akun sekarang, cocok dengan login.php yang ada)
// true   = password di-hash dengan password_hash(). Aktifkan HANYA setelah login.php memakai password_verify().
$HASH_PASSWORD = false;

$ROLE_ADMIN = ['admin', 'super_admin'];

$meId   = (int) $_SESSION['id_akun'];
$meRole = $_SESSION['tipe_akun'] ?? '';

$_SESSION['csrf'] = $_SESSION['csrf'] ?? bin2hex(random_bytes(16));

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/
function flash($tipe, $pesan)
{
    $_SESSION['flash'] = [$tipe, $pesan];
}

function labelRole($r)
{
    return ucwords(str_replace('_', ' ', (string) $r));
}

function kelasRole($r)
{
    if (in_array($r, ['admin', 'super_admin'])) return 'rb-admin';
    if ($r === 'manager') return 'rb-manager';
    if ($r === 'buyer') return 'rb-buyer';
    return 'rb-staff';
}

function ambilAkun($db, $id)
{
    $q = $db->prepare("SELECT id_akun, tipe_akun FROM akun WHERE id_akun = ?");
    $q->execute([$id]);
    return $q->fetch(PDO::FETCH_ASSOC);
}

function jumlahAdmin($db, $roleAdmin)
{
    $in = implode(',', array_fill(0, count($roleAdmin), '?'));
    $q = $db->prepare("SELECT COUNT(*) FROM akun WHERE tipe_akun IN ($in)");
    $q->execute($roleAdmin);
    return (int) $q->fetchColumn();
}

// daftar role dibaca langsung dari kolom enum tipe_akun
$roles = [];
try {
    $kol = $db->query("SHOW COLUMNS FROM akun LIKE 'tipe_akun'")->fetch(PDO::FETCH_ASSOC);
    preg_match_all("/'([^']+)'/", $kol['Type'] ?? '', $m);
    $roles = $m[1];
} catch (PDOException $e) {
}
if (!$roles) {
    $roles = ['super_admin', 'admin', 'manager', 'staff', 'buyer'];
}

/*
|--------------------------------------------------------------------------
| Proses form
|--------------------------------------------------------------------------
*/
function bacaForm($roles, $meRole, $wajibPassword)
{
    $nama     = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $tipe     = $_POST['tipe_akun'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($nama === '') throw new RuntimeException('Nama wajib diisi.');
    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) {
        throw new RuntimeException('Username 3-50 karakter, hanya huruf, angka, titik, dan garis bawah.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Format email tidak valid.');
    }
    if (!in_array($tipe, $roles, true)) throw new RuntimeException('Role tidak valid.');
    if ($tipe === 'super_admin' && $meRole !== 'super_admin') {
        throw new RuntimeException('Hanya super admin yang boleh memberi role super admin.');
    }
    if (($wajibPassword || $password !== '') && strlen($password) < 6) {
        throw new RuntimeException('Password minimal 6 karakter.');
    }

    return [
        'nama' => $nama, 'username' => $username,
        'email' => $email === '' ? null : $email,
        'no_hp' => trim($_POST['no_hp'] ?? '') ?: null,
        'alamat' => trim($_POST['alamat'] ?? '') ?: null,
        'tipe' => $tipe, 'password' => $password,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        flash('error', 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.');
        header('Location: user.php');
        exit;
    }

    $aksi = $_POST['aksi'] ?? '';
    $id   = (int) ($_POST['id_akun'] ?? 0);

    try {
        if ($aksi === 'tambah') {

            $d  = bacaForm($roles, $meRole, true);
            $pw = $HASH_PASSWORD ? password_hash($d['password'], PASSWORD_DEFAULT) : $d['password'];

            $db->prepare("INSERT INTO akun (nama, username, password, email, no_hp, alamat, tipe_akun) VALUES (?, ?, ?, ?, ?, ?, ?)")
               ->execute([$d['nama'], $d['username'], $pw, $d['email'], $d['no_hp'], $d['alamat'], $d['tipe']]);

            flash('ok', 'Akun "' . $d['username'] . '" berhasil ditambahkan.');

        } elseif ($aksi === 'ubah') {

            $target = ambilAkun($db, $id);
            if (!$target) throw new RuntimeException('Akun tidak ditemukan.');
            if ($target['tipe_akun'] === 'super_admin' && $meRole !== 'super_admin') {
                throw new RuntimeException('Akun super admin hanya bisa diubah oleh super admin.');
            }

            $d = bacaForm($roles, $meRole, false);

            // role akun sendiri tidak boleh diubah
            if ($id === $meId) {
                $d['tipe'] = $target['tipe_akun'];
            }

            // jangan sampai admin terakhir diturunkan
            if (in_array($target['tipe_akun'], $ROLE_ADMIN) && !in_array($d['tipe'], $ROLE_ADMIN)
                && jumlahAdmin($db, $ROLE_ADMIN) <= 1) {
                throw new RuntimeException('Tidak bisa menurunkan admin terakhir.');
            }

            $sql = "UPDATE akun SET nama = ?, username = ?, email = ?, no_hp = ?, alamat = ?, tipe_akun = ?";
            $par = [$d['nama'], $d['username'], $d['email'], $d['no_hp'], $d['alamat'], $d['tipe']];

            if ($d['password'] !== '') {
                $sql .= ", password = ?";
                $par[] = $HASH_PASSWORD ? password_hash($d['password'], PASSWORD_DEFAULT) : $d['password'];
            }

            $db->prepare($sql . " WHERE id_akun = ?")->execute(array_merge($par, [$id]));

            if ($id === $meId) $_SESSION['nama'] = $d['nama'];

            flash('ok', 'Akun "' . $d['username'] . '" berhasil diperbarui.');

        } elseif ($aksi === 'hapus') {

            $target = ambilAkun($db, $id);
            if (!$target) throw new RuntimeException('Akun tidak ditemukan.');
            if ($id === $meId) throw new RuntimeException('Kamu tidak bisa menghapus akunmu sendiri.');
            if ($target['tipe_akun'] === 'super_admin' && $meRole !== 'super_admin') {
                throw new RuntimeException('Akun super admin hanya bisa dihapus oleh super admin.');
            }
            if (in_array($target['tipe_akun'], $ROLE_ADMIN) && jumlahAdmin($db, $ROLE_ADMIN) <= 1) {
                throw new RuntimeException('Tidak bisa menghapus admin terakhir.');
            }

            $db->prepare("DELETE FROM akun WHERE id_akun = ?")->execute([$id]);
            flash('ok', 'Akun berhasil dihapus.');

        } else {
            flash('error', 'Aksi tidak dikenali.');
        }

    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            flash('error', $aksi === 'hapus'
                ? 'Akun tidak bisa dihapus karena masih dipakai di data lain (misalnya pesanan).'
                : 'Username atau email sudah dipakai akun lain.');
        } else {
            flash('error', 'Data gagal disimpan: ' . $e->getMessage());
        }
    }

    header('Location: user.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil data
|--------------------------------------------------------------------------
*/
$semua = [];
$galat = '';

try {
    $semua = $db->query(
        "SELECT id_akun, nama, username, email, no_hp, alamat, tipe_akun, dibuat_pada
         FROM akun ORDER BY id_akun ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $galat = $e->getMessage();
}

$perRole = [];
foreach ($semua as $u) {
    $perRole[$u['tipe_akun']] = ($perRole[$u['tipe_akun']] ?? 0) + 1;
}

$filter = $_GET['role'] ?? '';
if (!in_array($filter, $roles, true)) $filter = '';

$tampil = $filter ? array_values(array_filter($semua, fn($u) => $u['tipe_akun'] === $filter)) : $semua;

$total   = count($semua);
$pembeli = $perRole['buyer'] ?? 0;
$internal = $total - $pembeli;
$baru    = count(array_filter($semua, fn($u) => substr((string) $u['dibuat_pada'], 0, 7) === date('Y-m')));

$bln3 = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

layoutAtas('Pengguna', 'Kelola akun pengguna sistem', 'user.php', ['produk.css', 'user.css']);
?>

<?php if ($flash): ?>
    <div class="flash <?= $flash[0] === 'ok' ? 'flash-ok' : 'flash-error' ?>">
        <?= htmlspecialchars($flash[1]) ?>
    </div>
<?php endif; ?>

<?php if ($galat): ?>
    <div class="flash flash-error">
        Data pengguna gagal dimuat: <?= htmlspecialchars($galat) ?>
    </div>
<?php endif; ?>

<!-- Ringkasan -->
<section class="stat-row">
    <div class="stat-box"><small>Total pengguna</small><strong><?= $total ?></strong></div>
    <div class="stat-box"><small>Tim internal</small><strong><?= $internal ?></strong></div>
    <div class="stat-box"><small>Pembeli</small><strong><?= $pembeli ?></strong></div>
    <div class="stat-box"><small>Baru bulan ini</small><strong><?= $baru ?></strong></div>
</section>

<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Daftar Pengguna</h2>
            <p class="panel-sub"><?= count($tampil) ?> akun<?= $filter ? ' dengan role ' . labelRole($filter) : '' ?></p>
        </div>

        <button type="button" class="btn btn-primary" onclick="bukaTambah()">+ Tambah pengguna</button>
    </div>

    <!-- Filter role -->
    <div class="role-tabs">
        <a href="user.php" class="role-tab <?= $filter === '' ? 'on' : '' ?>">Semua <b><?= $total ?></b></a>
        <?php foreach ($roles as $r): ?>
            <?php if (!empty($perRole[$r])): ?>
                <a href="user.php?role=<?= urlencode($r) ?>" class="role-tab <?= $filter === $r ? 'on' : '' ?>">
                    <?= labelRole($r) ?> <b><?= $perRole[$r] ?></b>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <?php if ($tampil): ?>
        <div class="table-wrap">
            <table class="tbl produk-tbl">
                <thead>
                    <tr>
                        <th>Pengguna</th>
                        <th>Kontak</th>
                        <th>Role</th>
                        <th>Dibuat</th>
                        <th class="right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tampil as $u): ?>
                        <?php
                        $diriSendiri = (int) $u['id_akun'] === $meId;
                        $ts = strtotime((string) $u['dibuat_pada']);
                        $json = htmlspecialchars(json_encode([
                            'id' => (int) $u['id_akun'], 'nama' => $u['nama'], 'username' => $u['username'],
                            'email' => $u['email'], 'no_hp' => $u['no_hp'], 'alamat' => $u['alamat'],
                            'tipe' => $u['tipe_akun'],
                        ]), ENT_QUOTES);
                        ?>
                        <tr class="searchable"
                            data-text="<?= htmlspecialchars(strtolower($u['nama'] . ' ' . $u['username'] . ' ' . $u['email'] . ' ' . $u['no_hp'] . ' ' . $u['tipe_akun'])) ?>">

                            <td>
                                <div class="produk-cell">
                                    <div class="user-avatar"><?= htmlspecialchars(strtoupper(mb_substr($u['nama'], 0, 1))) ?></div>
                                    <div class="produk-info">
                                        <b>
                                            <?= htmlspecialchars($u['nama']) ?>
                                            <?php if ($diriSendiri): ?><span class="me-tag">Kamu</span><?php endif; ?>
                                        </b>
                                        <small>@<?= htmlspecialchars($u['username']) ?></small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="kontak">
                                    <span><?= htmlspecialchars((string) $u['email'] ?: '-') ?></span>
                                    <small><?= htmlspecialchars((string) $u['no_hp'] ?: '') ?></small>
                                </div>
                            </td>

                            <td><span class="role-pill <?= kelasRole($u['tipe_akun']) ?>"><?= labelRole($u['tipe_akun']) ?></span></td>

                            <td><?= $ts ? date('j', $ts) . ' ' . $bln3[(int) date('n', $ts)] . ' ' . date('Y', $ts) : '-' ?></td>

                            <td class="right">
                                <div class="row-aksi">
                                    <button type="button" class="btn btn-ghost btn-sm" data-akun="<?= $json ?>" onclick="bukaEdit(this)">✎ Ubah</button>

                                    <?php if (!$diriSendiri): ?>
                                        <form method="post" onsubmit="return confirm('Hapus akun ini? Tindakan ini tidak bisa dibatalkan.')">
                                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                                            <input type="hidden" name="aksi" value="hapus">
                                            <input type="hidden" name="id_akun" value="<?= (int) $u['id_akun'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">✕ Hapus</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="empty" id="searchEmpty" hidden>Pengguna tidak ditemukan.</p>

    <?php elseif (!$galat): ?>
        <div class="empty">Belum ada pengguna.</div>
    <?php endif; ?>
</section>


<dialog id="formDialog" class="dialog">
    <form method="post" id="formAkun">
        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
        <input type="hidden" name="aksi" value="tambah">
        <input type="hidden" name="id_akun" value="">

        <div class="dialog-head">
            <h2 id="dialogJudul">Tambah pengguna</h2>
            <button type="button" class="dialog-x" onclick="this.closest('dialog').close()" aria-label="Tutup">✕</button>
        </div>

        <label class="field">
            <span>Nama lengkap</span>
            <input type="text" name="nama" required maxlength="100">
        </label>

        <div class="field-row">
            <label class="field">
                <span>Username</span>
                <input type="text" name="username" required maxlength="50" autocomplete="off">
            </label>
            <label class="field">
                <span>Role</span>
                <select name="tipe_akun" required>
                    <?php foreach ($roles as $r): ?>
                        <?php if ($r === 'super_admin' && $meRole !== 'super_admin') continue; ?>
                        <option value="<?= htmlspecialchars($r) ?>" <?= $r === 'staff' ? 'selected' : '' ?>><?= labelRole($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="field-row">
            <label class="field">
                <span>Email <em>(opsional)</em></span>
                <input type="email" name="email" maxlength="100">
            </label>
            <label class="field">
                <span>No. HP <em>(opsional)</em></span>
                <input type="text" name="no_hp" maxlength="20">
            </label>
        </div>

        <label class="field">
            <span>Alamat <em>(opsional)</em></span>
            <textarea name="alamat" rows="2"></textarea>
        </label>

        <label class="field">
            <span>Password <em id="pwHint">(minimal 6 karakter)</em></span>
            <input type="password" name="password" minlength="6" autocomplete="new-password">
        </label>

        <div class="dialog-foot">
            <button type="button" class="btn btn-ghost" onclick="this.closest('dialog').close()">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>
</dialog>

<script>
const dlg   = document.getElementById('formDialog');
const form  = document.getElementById('formAkun');
const judul = document.getElementById('dialogJudul');
const pwHint = document.getElementById('pwHint');
const meId  = <?= $meId ?>;

function bukaTambah() {
    form.reset();
    form.aksi.value = 'tambah';
    form.id_akun.value = '';
    form.tipe_akun.disabled = false;
    form.password.required = true;
    judul.textContent = 'Tambah pengguna';
    pwHint.textContent = '(minimal 6 karakter)';
    dlg.showModal();
}

function bukaEdit(btn) {
    const d = JSON.parse(btn.dataset.akun);

    form.reset();
    form.aksi.value = 'ubah';
    form.id_akun.value = d.id;
    form.nama.value = d.nama;
    form.username.value = d.username;
    form.email.value = d.email || '';
    form.no_hp.value = d.no_hp || '';
    form.alamat.value = d.alamat || '';

    // role dipilih sesuai data; role akun sendiri tidak bisa diubah
    if (![...form.tipe_akun.options].some(o => o.value === d.tipe)) {
        form.tipe_akun.add(new Option(d.tipe.replace('_', ' '), d.tipe));
    }
    form.tipe_akun.value = d.tipe;
    form.tipe_akun.disabled = (d.id === meId);

    form.password.required = false;
    judul.textContent = 'Ubah pengguna';
    pwHint.textContent = '(kosongkan jika tidak diganti)';
    dlg.showModal();
}

// select yang disabled tidak ikut terkirim, itu sengaja: server memakai role lama untuk akun sendiri
</script>

<?php layoutBawah(); ?>