<?php
session_start();

require __DIR__ . '/koneksi.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/_layout.php';

if (!isset($_SESSION['id_akun'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['tipe_akun'] ?? '', ['admin', 'manager', 'staff'])) {
    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| KONFIGURASI
|--------------------------------------------------------------------------
*/
$C = [
    'tabel'     => 'bouquet',
    'id'        => 'id_bouquet',
    'kategori'  => 'id_kategori',
    'nama'      => 'nama_bouquet',
    'harga'     => 'harga',
    'stok'      => 'stok',
    'ukuran'    => 'ukuran',
    'warna'     => 'warna',
    'deskripsi' => 'deskripsi',
    'foto'      => 'foto',                  // berisi URL gambar atau nama file upload
    'folder'    => 'assets/produk/',        // folder untuk foto yang diupload

    'kat_tabel' => 'kategori_bouquet',      // kolom id & nama kategori dideteksi otomatis
    'batas_stok' => 5,                      // stok <= angka ini dianggap menipis

    // nama privilege untuk tiap aksi
    'priv' => [
        'lihat'  => 'view_produk',
        'tambah' => 'manage_produk',
        'ubah'   => 'manage_produk',
        'hapus'  => 'manage_produk',
    ],
];

$P = $C['priv'];

if (!hasPrivilege($P['lihat'])) {
    http_response_code(403);
    exit('Kamu tidak punya akses ke halaman ini.');
}

$bisaTambah = hasPrivilege($P['tambah']);
$bisaUbah   = hasPrivilege($P['ubah']);
$bisaHapus  = hasPrivilege($P['hapus']);

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

function rp($n)
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}

function adalahUrl($s)
{
    return (bool) preg_match('#^https?://#i', (string) $s);
}

function simpanFoto($f, $dir)
{
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload foto gagal.');
    if ($f['size'] > 2 * 1024 * 1024) throw new RuntimeException('Ukuran foto maksimal 2 MB.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) throw new RuntimeException('Format foto harus JPG, PNG, atau WEBP.');

    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $nama = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . $nama)) {
        throw new RuntimeException('Gagal menyimpan foto.');
    }
    return $nama;
}

// hapus file lokal saja (URL luar tidak disentuh)
function hapusFoto($dir, $foto)
{
    if ($foto && !adalahUrl($foto) && is_file($dir . basename($foto))) {
        @unlink($dir . basename($foto));
    }
}

// tentukan nilai foto: upload > URL > nilai lama
function tentukanFoto($dir, $lama)
{
    $upload = simpanFoto($_FILES['foto'] ?? null, $dir);
    if ($upload) return [$upload, true];

    $url = trim($_POST['foto_url'] ?? '');
    if ($url !== '') {
        if (!adalahUrl($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Link foto harus diawali http:// atau https://');
        }
        return [$url, $url !== $lama];
    }

    return [$lama, false];
}

$dir = __DIR__ . '/' . $C['folder'];

/*
|--------------------------------------------------------------------------
| Kategori (kolom id & nama dideteksi dari tabelnya)
|--------------------------------------------------------------------------
*/
$kategori = [];       // id => nama
$kid = $knama = null;

try {
    $kc = $pdo->query("SHOW COLUMNS FROM {$C['kat_tabel']}")->fetchAll(PDO::FETCH_COLUMN);
    if ($kc) {
        $kid   = $kc[0];
        $knama = $kc[1] ?? $kc[0];
        foreach ($pdo->query("SELECT $kid AS id, $knama AS nama FROM {$C['kat_tabel']} ORDER BY $kid") as $r) {
            $kategori[$r['id']] = $r['nama'];
        }
    }
} catch (PDOException $e) {
    $kid = $knama = null;
}

/*
|--------------------------------------------------------------------------
| Proses form (tambah / ubah / hapus) - privilege dicek di server
|--------------------------------------------------------------------------
*/
function bacaForm()
{
    $nama  = trim($_POST['nama'] ?? '');
    $harga = $_POST['harga'] ?? '';
    $stok  = $_POST['stok'] ?? '';
    $kat   = (int) ($_POST['kategori'] ?? 0);

    if ($nama === '' || !is_numeric($harga) || $harga < 0 || !ctype_digit((string) $stok) || $kat <= 0) {
        throw new RuntimeException('Kategori, nama, harga, dan stok wajib diisi dengan benar.');
    }

    $ukuran = trim($_POST['ukuran'] ?? '');

    return [
        'kat' => $kat, 'nama' => $nama, 'harga' => $harga, 'stok' => (int) $stok,
        'ukuran' => $ukuran === '' ? null : $ukuran,
        'warna' => trim($_POST['warna'] ?? ''),
        'deskripsi' => trim($_POST['deskripsi'] ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        flash('error', 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.');
        header('Location: produk.php');
        exit;
    }

    $aksi = $_POST['aksi'] ?? '';
    $id   = (int) ($_POST['id_produk'] ?? 0);
    $t = $C['tabel']; $cid = $C['id'];

    try {
        if ($aksi === 'tambah' && $bisaTambah) {

            $d = bacaForm();
            [$foto] = tentukanFoto($dir, null);

            $pdo->prepare(
                "INSERT INTO $t ({$C['kategori']}, {$C['nama']}, {$C['harga']}, {$C['stok']}, {$C['ukuran']}, {$C['warna']}, {$C['deskripsi']}, {$C['foto']})
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([$d['kat'], $d['nama'], $d['harga'], $d['stok'], $d['ukuran'], $d['warna'], $d['deskripsi'], $foto]);

            flash('ok', 'Produk "' . $d['nama'] . '" berhasil ditambahkan.');

        } elseif ($aksi === 'ubah' && $bisaUbah) {

            if ($id <= 0) throw new RuntimeException('Produk tidak ditemukan.');
            $d = bacaForm();

            $q = $pdo->prepare("SELECT {$C['foto']} FROM $t WHERE $cid = ?");
            $q->execute([$id]);
            $fotoLama = $q->fetchColumn();

            [$fotoBaru, $berubah] = tentukanFoto($dir, $fotoLama);

            $pdo->prepare(
                "UPDATE $t SET {$C['kategori']} = ?, {$C['nama']} = ?, {$C['harga']} = ?, {$C['stok']} = ?,
                        {$C['ukuran']} = ?, {$C['warna']} = ?, {$C['deskripsi']} = ?, {$C['foto']} = ?
                 WHERE $cid = ?"
            )->execute([$d['kat'], $d['nama'], $d['harga'], $d['stok'], $d['ukuran'], $d['warna'], $d['deskripsi'], $fotoBaru, $id]);

            if ($berubah) hapusFoto($dir, $fotoLama);

            flash('ok', 'Produk "' . $d['nama'] . '" berhasil diperbarui.');

        } elseif ($aksi === 'hapus' && $bisaHapus) {

            $q = $pdo->prepare("SELECT {$C['foto']} FROM $t WHERE $cid = ?");
            $q->execute([$id]);
            $fotoLama = $q->fetchColumn();

            $pdo->prepare("DELETE FROM $t WHERE $cid = ?")->execute([$id]);
            hapusFoto($dir, $fotoLama);

            flash('ok', 'Produk berhasil dihapus.');

        } else {
            flash('error', 'Kamu tidak punya izin untuk aksi ini.');
        }

    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    } catch (PDOException $e) {
        flash('error', $aksi === 'hapus'
            ? 'Produk tidak bisa dihapus karena sudah dipakai di data pesanan.'
            : 'Data gagal disimpan: ' . $e->getMessage());
    }

    header('Location: produk.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil data produk
|--------------------------------------------------------------------------
*/
$produk = [];
$galat  = false;
$pesanGalat = '';

$join  = $kid ? "LEFT JOIN {$C['kat_tabel']} k ON k.$kid = b.{$C['kategori']}" : '';
$kolomKat = $kid ? "k.$knama" : "NULL";

try {
    $produk = $pdo->query(
        "SELECT b.{$C['id']} AS id, b.{$C['kategori']} AS kat_id, $kolomKat AS kat_nama,
                b.{$C['nama']} AS nama, b.{$C['harga']} AS harga, b.{$C['stok']} AS stok,
                b.{$C['ukuran']} AS ukuran, b.{$C['warna']} AS warna,
                b.{$C['deskripsi']} AS deskripsi, b.{$C['foto']} AS foto
         FROM {$C['tabel']} b $join
         ORDER BY b.{$C['id']} DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $galat = true;
    $pesanGalat = $e->getMessage();
}

$jumlah    = count($produk);
$totalStok = array_sum(array_map(fn($p) => (int) $p['stok'], $produk));
$menipis   = count(array_filter($produk, fn($p) => (int) $p['stok'] <= $C['batas_stok']));

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);


layoutAtas('Produk', 'Kelola katalog bouquet', 'produk.php', ['produk.css']);
?>

<?php if ($flash): ?>
    <div class="flash <?= $flash[0] === 'ok' ? 'flash-ok' : 'flash-error' ?>">
        <?= htmlspecialchars($flash[1]) ?>
    </div>
<?php endif; ?>

<?php if ($galat): ?>
    <div class="flash flash-error">
        Data produk gagal dimuat.
        <?php if (($_SESSION['tipe_akun'] ?? '') === 'admin'): ?>
            <br><b>Pesan dari MySQL:</b> <?= htmlspecialchars($pesanGalat) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php $adaAksi = $bisaUbah || $bisaHapus; ?>

<!-- Ringkasan -->
<section class="stat-row">
    <div class="stat-box">
        <small>Total produk</small>
        <strong><?= $jumlah ?></strong>
    </div>
    <div class="stat-box">
        <small>Total stok</small>
        <strong><?= number_format($totalStok, 0, ',', '.') ?></strong>
    </div>
    <div class="stat-box">
        <small>Stok menipis (≤ <?= $C['batas_stok'] ?>)</small>
        <strong class="<?= $menipis ? 'warn' : '' ?>"><?= $menipis ?></strong>
    </div>
</section>

<!-- Tabel produk -->
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Daftar Produk</h2>
            <p class="panel-sub"><?= $jumlah ?> produk terdaftar</p>
        </div>

        <?php if ($bisaTambah): ?>
            <button type="button" class="btn btn-primary" onclick="bukaTambah()">+ Tambah produk</button>
        <?php endif; ?>
    </div>

    <?php if ($produk): ?>
        <div class="table-wrap">
            <table class="tbl produk-tbl">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Kategori</th>
                        <th>Warna / Ukuran</th>
                        <th class="right">Harga</th>
                        <th>Stok</th>
                        <?php if ($adaAksi): ?><th class="right">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produk as $p): ?>
                        <?php
                        $foto = (string) $p['foto'];
                        $src  = null;
                        if ($foto !== '') {
                            if (adalahUrl($foto)) $src = $foto;
                            elseif (is_file($dir . basename($foto))) $src = $C['folder'] . basename($foto);
                        }

                        $stok      = (int) $p['stok'];
                        $kelasStok = $stok <= 0 ? 'habis' : ($stok <= $C['batas_stok'] ? 'tipis' : 'aman');
                        $labelStok = $stok <= 0 ? 'Habis' : $stok . ' tersisa';

                        $json = htmlspecialchars(json_encode([
                            'id' => $p['id'], 'kat_id' => $p['kat_id'], 'nama' => $p['nama'],
                            'harga' => (float) $p['harga'], 'stok' => $stok, 'ukuran' => $p['ukuran'],
                            'warna' => $p['warna'], 'deskripsi' => $p['deskripsi'],
                            'foto_url' => adalahUrl($foto) ? $foto : '',
                        ]), ENT_QUOTES);

                        $warnaUkuran = trim((string) $p['warna'] . ($p['warna'] && $p['ukuran'] ? ' • ' : '') . (string) $p['ukuran']);
                        ?>
                        <tr class="searchable"
                            data-text="<?= htmlspecialchars(strtolower($p['nama'] . ' ' . $p['warna'] . ' ' . $p['kat_nama'])) ?>">

                            <td>
                                <div class="produk-cell">
                                    <div class="thumb">
                                        <?php if ($src): ?>
                                            <img src="<?= htmlspecialchars($src) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                                        <?php else: ?>
                                            <span><?= htmlspecialchars(strtoupper(mb_substr($p['nama'], 0, 1))) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="produk-info">
                                        <b><?= htmlspecialchars($p['nama']) ?></b>
                                        <small><?= htmlspecialchars((string) $p['deskripsi'] ?: 'Belum ada deskripsi.') ?></small>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?php if ($p['kat_nama']): ?>
                                    <span class="kat-chip"><?= htmlspecialchars($p['kat_nama']) ?></span>
                                <?php else: ?>-<?php endif; ?>
                            </td>

                            <td><?= $warnaUkuran !== '' ? htmlspecialchars($warnaUkuran) : '-' ?></td>

                            <td class="right"><b><?= rp($p['harga']) ?></b></td>

                            <td><span class="stok-pill <?= $kelasStok ?>"><?= $labelStok ?></span></td>

                            <?php if ($adaAksi): ?>
                                <td class="right">
                                    <div class="row-aksi">
                                        <?php if ($bisaUbah): ?>
                                            <button type="button" class="btn btn-ghost btn-sm" data-produk="<?= $json ?>" onclick="bukaEdit(this)">✎ Ubah</button>
                                        <?php endif; ?>

                                        <?php if ($bisaHapus): ?>
                                            <form method="post" onsubmit="return confirm('Hapus produk ini? Tindakan ini tidak bisa dibatalkan.')">
                                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                                                <input type="hidden" name="aksi" value="hapus">
                                                <input type="hidden" name="id_produk" value="<?= (int) $p['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">✕ Hapus</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="empty" id="searchEmpty" hidden>Produk tidak ditemukan.</p>

    <?php elseif (!$galat): ?>
        <div class="empty">
            Belum ada produk.
            <?= $bisaTambah ? 'Klik "Tambah produk" untuk mulai.' : '' ?>
        </div>
    <?php endif; ?>
</section>


<?php if ($bisaTambah || $bisaUbah): ?>
<dialog id="formDialog" class="dialog">
    <form method="post" enctype="multipart/form-data" id="formProduk">
        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
        <input type="hidden" name="aksi" value="tambah">
        <input type="hidden" name="id_produk" value="">

        <div class="dialog-head">
            <h2 id="dialogJudul">Tambah produk</h2>
            <button type="button" class="dialog-x" onclick="this.closest('dialog').close()" aria-label="Tutup">✕</button>
        </div>

        <label class="field">
            <span>Nama produk</span>
            <input type="text" name="nama" required maxlength="150">
        </label>

        <label class="field">
            <span>Kategori</span>
            <?php if ($kategori): ?>
                <select name="kategori" required>
                    <option value="">Pilih kategori</option>
                    <?php foreach ($kategori as $kidOpt => $knamaOpt): ?>
                        <option value="<?= htmlspecialchars($kidOpt) ?>"><?= htmlspecialchars($knamaOpt) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="number" name="kategori" required min="1" placeholder="ID kategori">
            <?php endif; ?>
        </label>

        <div class="field-row">
            <label class="field">
                <span>Harga (Rp)</span>
                <input type="number" name="harga" required min="0" step="1000">
            </label>
            <label class="field">
                <span>Stok</span>
                <input type="number" name="stok" required min="0" step="1">
            </label>
        </div>

        <div class="field-row">
            <label class="field">
                <span>Warna</span>
                <input type="text" name="warna" maxlength="50">
            </label>
            <label class="field">
                <span>Ukuran <em>(opsional)</em></span>
                <input type="text" name="ukuran" maxlength="50">
            </label>
        </div>

        <label class="field">
            <span>Deskripsi</span>
            <textarea name="deskripsi" rows="3"></textarea>
        </label>

        <label class="field">
            <span>Link foto</span>
            <input type="url" name="foto_url" placeholder="https://...">
        </label>

        <label class="field">
            <span>atau upload foto <em id="fotoHint">(JPG, PNG, WEBP, maks 2 MB)</em></span>
            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
        </label>

        <div class="dialog-foot">
            <button type="button" class="btn btn-ghost" onclick="this.closest('dialog').close()">Batal</button>
            <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
    </form>
</dialog>

<script>
const dlg   = document.getElementById('formDialog');
const form  = document.getElementById('formProduk');
const judul = document.getElementById('dialogJudul');
const hint  = document.getElementById('fotoHint');

function bukaTambah() {
    form.reset();
    form.aksi.value = 'tambah';
    form.id_produk.value = '';
    judul.textContent = 'Tambah produk';
    hint.textContent = '(JPG, PNG, WEBP, maks 2 MB)';
    dlg.showModal();
}

function bukaEdit(btn) {
    const d = JSON.parse(btn.dataset.produk);

    form.reset();
    form.aksi.value = 'ubah';
    form.id_produk.value = d.id;
    form.nama.value = d.nama;
    form.kategori.value = d.kat_id;
    form.harga.value = d.harga;
    form.stok.value = d.stok;
    form.warna.value = d.warna || '';
    form.ukuran.value = d.ukuran || '';
    form.deskripsi.value = d.deskripsi || '';
    form.foto_url.value = d.foto_url || '';
    judul.textContent = 'Ubah produk';
    hint.textContent = '(kosongkan jika tidak ingin mengganti)';
    dlg.showModal();
}
</script>
<?php endif; ?>

<?php layoutBawah(); ?>