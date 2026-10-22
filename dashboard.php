<?php
session_start();

require __DIR__ . '/koneksi.php';
require __DIR__ . '/auth.php';

if (!isset($_SESSION['id_akun'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['tipe_akun'] ?? '';

if ($role === 'buyer') {
    header('Location: tps/katalog.php');
    exit;
}

if (!in_array($role, ['admin', 'manager', 'staff'])) {
    header('Location: login.php');
    exit;
}

$nama  = $_SESSION['nama'] ?? 'User';
$email = $_SESSION['email'] ?? '';

$roleLabel = ['admin' => 'Admin', 'manager' => 'Manager', 'staff' => 'Staff'];
$roleTitle = $roleLabel[$role] ?? 'User';
$inisial   = strtoupper(mb_substr($nama, 0, 1));

/*
|--------------------------------------------------------------------------
| KONFIGURASI - sesuaikan nama tabel & kolom dengan database kamu
|--------------------------------------------------------------------------
*/
$cfg = [
    'pesanan' => [
        'tabel'    => 'pesanan',
        'tanggal'  => 'tanggal_pesanan',          // kolom tanggal/waktu pesanan
        'nama'     => 'nama_pembeli',             // nama pembeli
        'total'    => 'total_harga',              // total harga pesanan
        'status'   => 'status',                   // status pesanan
        'menunggu' => ['menunggu', 'pending', 'baru'], // nilai status = belum diproses (huruf kecil)
    ],
    'keuangan' => [
        'tabel'   => 'keuangan',
        'tanggal' => 'tanggal',
        'jenis'   => 'jenis',                     // kolom pembeda pemasukan / pengeluaran
        'jumlah'  => 'jumlah',                    // nominal
        'masuk'   => 'pemasukan',                 // nilai kolom jenis untuk pemasukan (huruf kecil)
    ],
    'inventory' => [
        'tabel' => 'bouquet',
        'nama'  => 'nama_bouquet',
        'stok'  => 'stok',
        'batas' => 5,                             // stok <= angka ini dianggap menipis
    ],
];

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/
$queryGagal = [];

function ambil($db, $sql, $params = [], $label = '')
{
    global $queryGagal;
    try {
        $s = $db->prepare($sql);
        $s->execute($params);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $queryGagal[] = $label ?: $sql;
        return [];
    }
}

function skalar($db, $sql, $params = [], $label = '')
{
    $r = ambil($db, $sql, $params, $label);
    return $r ? (float) array_values($r[0])[0] : 0;
}

function rp($n)
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function rpSingkat($n)
{
    if ($n >= 1e9) return round($n / 1e9, 1) . ' M';
    if ($n >= 1e6) return round($n / 1e6, 1) . ' jt';
    if ($n >= 1e3) return round($n / 1e3) . ' rb';
    return (string) round($n);
}

function banding($now, $prev, $pembanding)
{
    if ($prev <= 0) return ['Belum ada pembanding', null];
    $d = round(($now - $prev) / $prev * 100);
    return [($d >= 0 ? '↑ ' : '↓ ') . abs($d) . '% ' . $pembanding, $d >= 0];
}

function kelasStatus($s)
{
    $s = strtolower((string) $s);
    if (preg_match('/selesai|lunas|kirim|terima/', $s)) return 'st-selesai';
    if (preg_match('/proses|packing|siap/', $s))        return 'st-proses';
    if (preg_match('/batal|tolak|gagal/', $s))          return 'st-batal';
    return 'st-baru';
}

$hari  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
          'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$bln3  = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

$tanggal = $hari[date('w')] . ', ' . date('j') . ' ' . $bulan[(int) date('n')] . ' ' . date('Y');

/* 6 bulan terakhir: ['2026-05' => 'Mei', ...] */
$bulanList = [];
for ($i = 5; $i >= 0; $i--) {
    $d = new DateTime('first day of this month');
    $d->modify("-$i month");
    $bulanList[$d->format('Y-m')] = $bln3[(int) $d->format('n')];
}
$awalRentang = array_key_first($bulanList) . '-01';
$bulanIni    = date('Y-m');
$bulanLalu   = (new DateTime('first day of last month'))->format('Y-m');

/*
|--------------------------------------------------------------------------
| Data
|--------------------------------------------------------------------------
*/
$canPesanan   = hasPrivilege('view_pesanan');
$canKeuangan  = hasPrivilege('view_keuangan');
$canInventory = hasPrivilege('view_inventory');

$pesananHariIni = $pesananKemarin = $menunggu = 0;
$pesananBaru = [];
$pesananBulan = [];

if ($canPesanan) {
    $P = $cfg['pesanan'];
    $t = $P['tabel']; $tg = $P['tanggal']; $st = $P['status'];

    $pesananHariIni = skalar($pdo, "SELECT COUNT(*) FROM $t WHERE DATE($tg) = CURDATE()", [], 'pesanan hari ini');
    $pesananKemarin = skalar($pdo, "SELECT COUNT(*) FROM $t WHERE DATE($tg) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)", [], 'pesanan kemarin');

    $in = implode(',', array_fill(0, count($P['menunggu']), '?'));
    $menunggu = skalar($pdo, "SELECT COUNT(*) FROM $t WHERE LOWER($st) IN ($in)", $P['menunggu'], 'pesanan menunggu');

    $pesananBaru = ambil(
        $pdo,
        "SELECT {$P['nama']} AS nama, {$P['total']} AS total, $st AS status, $tg AS tgl
         FROM $t ORDER BY $tg DESC LIMIT 6",
        [], 'pesanan terbaru'
    );

    foreach (ambil($pdo, "SELECT DATE_FORMAT($tg, '%Y-%m') AS ym, COUNT(*) AS n FROM $t WHERE $tg >= ? GROUP BY ym", [$awalRentang], 'pesanan per bulan') as $r) {
        $pesananBulan[$r['ym']] = (int) $r['n'];
    }
}

$uangBulan = [];
$masukIni = $masukLalu = 0;

if ($canKeuangan) {
    $K = $cfg['keuangan'];
    $t = $K['tabel']; $tg = $K['tanggal']; $jm = $K['jumlah']; $jn = $K['jenis'];

    foreach (ambil($pdo, "SELECT DATE_FORMAT($tg, '%Y-%m') AS ym, SUM($jm) AS s FROM $t WHERE LOWER($jn) = ? AND $tg >= ? GROUP BY ym", [$K['masuk'], $awalRentang], 'pemasukan per bulan') as $r) {
        $uangBulan[$r['ym']] = (float) $r['s'];
    }
    $masukIni   = $uangBulan[$bulanIni]  ?? 0;
    $masukLalu  = $uangBulan[$bulanLalu] ?? 0;
}

$stokMenipis = [];
$jumlahMenipis = 0;

if ($canInventory) {
    $I = $cfg['inventory'];
    $t = $I['tabel']; $sk = $I['stok'];

    $jumlahMenipis = skalar($pdo, "SELECT COUNT(*) FROM $t WHERE $sk <= ?", [$I['batas']], 'stok menipis');
    $stokMenipis   = ambil($pdo, "SELECT {$I['nama']} AS nama, $sk AS stok FROM $t WHERE $sk <= ? ORDER BY $sk ASC LIMIT 6", [$I['batas']], 'daftar stok menipis');
}

/* KPI hero */
$kpi = [];
if ($canPesanan) {
    [$txt, $naik] = banding($pesananHariIni, $pesananKemarin, 'dari kemarin');
    $kpi[] = ['Pesanan hari ini', number_format($pesananHariIni, 0, ',', '.'), $txt, '□', $naik];
    $kpi[] = ['Menunggu diproses', number_format($menunggu, 0, ',', '.'), $menunggu > 0 ? 'Perlu segera ditindak' : 'Semua sudah ditangani', '◔', null];
}
if ($canKeuangan) {
    [$txt, $naik] = banding($masukIni, $masukLalu, 'dari bulan lalu');
    $kpi[] = ['Pemasukan bulan ini', rp($masukIni), $txt, 'Rp', $naik];
}
if ($canInventory) {
    $kpi[] = ['Stok menipis', number_format($jumlahMenipis, 0, ',', '.'), 'Item ≤ ' . $cfg['inventory']['batas'] . ' unit', '▣', null];
}

/* Notifikasi (nyata, dari data di atas) */
$notif = [];
if ($menunggu > 0)      $notif[] = [$menunggu . ' pesanan menunggu diproses', 'daftar_pesanan.php'];
if ($jumlahMenipis > 0) $notif[] = [$jumlahMenipis . ' item stok menipis', 'inventory.php'];
$jumlahNotif = (int) $menunggu + (int) $jumlahMenipis;

/* Grafik 6 bulan */
$modeUang = $canKeuangan;
$seri = [];
foreach ($bulanList as $ym => $lab) {
    $v = $modeUang ? ($uangBulan[$ym] ?? 0) : ($pesananBulan[$ym] ?? 0);
    $seri[] = [
        'lab'  => $lab,
        'v'    => $v,
        'sub'  => ($modeUang && $canPesanan) ? ($pesananBulan[$ym] ?? 0) . ' pesanan' : '',
        'ini'  => $ym === $bulanIni,
    ];
}
$maxSeri   = max(1, max(array_column($seri, 'v')));
$totalSeri = array_sum(array_column($seri, 'v'));

/* Menu sidebar */
$menus = [
    ['manage_user',      'user.php',      '♙',  'Pengguna'],
    ['manage_role',      'role.php',      '◉',  'Role & Privilege'],
    ['view_produk',      'produk.php',    '◇',  'Produk'],
    ['view_pesanan',     'daftar_pesanan.php',   '□',  'Pesanan'],
    ['view_inventory',   'inventory.php', '▣',  'Inventory'],
    ['view_keuangan',    'keuangan.php',  'Rp', 'Keuangan'],
    ['view_laporan',     'laporan.php',   '▥',  'Laporan'],
    ['manage_knowledge', 'knowledge.php', '▤',  'Knowledge'],
    ['manage_faq',       'faq.php',       '?',  'FAQ'],
];

$roleDesc = $role === 'admin'
    ? 'Ringkasan aktivitas RAY Bouquet hari ini.'
    : ($role === 'manager'
        ? 'Pantau penjualan dan operasional RAY Bouquet.'
        : 'Hal-hal yang perlu kamu kerjakan hari ini.');

$adaChart = $canPesanan || $canKeuangan;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard <?= htmlspecialchars($roleTitle) ?> - RAY Bouquet</title>
    <link rel="stylesheet" href="dashboard.css">
</head>

<body>

<!-- ==============================
     SIDEBAR
============================== -->
<aside class="sidebar" id="sidebar">

    <div class="sidebar-head">
        <a href="dashboard.php" class="brand">
            <img src="assets/logoo.png" alt="RAY Bouquet">
            <small><?= htmlspecialchars($roleTitle) ?> Panel</small>
        </a>

        <button class="sidebar-close" id="sidebarToggle" type="button" aria-label="Tutup / buka sidebar">
            <span class="icon-close">✕</span>
            <span class="icon-open">☰</span>
        </button>
    </div>

    <nav class="sidebar-menu">
        <a href="dashboard.php" class="sidebar-link active" title="Dashboard">
            <span class="sidebar-icon">⌂</span>
            <span class="sidebar-text">Dashboard</span>
        </a>

        <?php foreach ($menus as [$priv, $href, $ikon, $judul]): ?>
            <?php if (hasPrivilege($priv)): ?>
                <a href="<?= $href ?>" class="sidebar-link" title="<?= htmlspecialchars($judul) ?>">
                    <span class="sidebar-icon"><?= $ikon ?></span>
                    <span class="sidebar-text"><?= htmlspecialchars($judul) ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-bottom">
        <a href="logout.php" class="sidebar-link logout-link" title="Logout"
           onclick="return confirm('Yakin ingin logout?')">
            <span class="sidebar-icon">⎋</span>
            <span class="sidebar-text">Logout</span>
        </a>

        <div class="sidebar-user">
            <div class="avatar"><?= htmlspecialchars($inisial) ?></div>
            <div class="user-info">
                <strong><?= htmlspecialchars($nama) ?></strong>
                <small><?= htmlspecialchars($email ?: $roleTitle) ?></small>
            </div>
        </div>
    </div>

</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>


<!-- ==============================
     MAIN
============================== -->
<div class="main">

    <header class="topbar">
        <div class="topbar-left">
            <button class="mobile-toggle" id="mobileToggle" type="button" aria-label="Buka menu">☰</button>

            <div class="page-title">
                <h1>Dashboard</h1>
                <small>Kelola dashboard RAY Bouquet</small>
            </div>
        </div>

        <div class="topbar-right">
            <label class="search-box">
                <span>⌕</span>
                <input type="search" id="pageSearch" placeholder="Cari pesanan atau stok..." autocomplete="off">
            </label>

            <div class="notif-wrap">
                <button class="icon-btn" id="notifBtn" type="button" aria-label="Notifikasi">
                    🔔
                    <?php if ($jumlahNotif > 0): ?>
                        <span class="notif-badge"><?= $jumlahNotif > 99 ? '99+' : $jumlahNotif ?></span>
                    <?php endif; ?>
                </button>

                <div class="notif-pop" id="notifPop">
                    <strong>Notifikasi</strong>
                    <?php if ($notif): ?>
                        <?php foreach ($notif as [$teks, $link]): ?>
                            <a href="<?= $link ?>"><?= htmlspecialchars($teks) ?></a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Tidak ada yang perlu ditindak.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>


    <main class="dashboard-container">

        <!-- HERO -->
        <section class="hero">
            <div class="hero-top">
                <div>
                    <div class="hero-title">
                        <h2>Halo, <?= htmlspecialchars($nama) ?></h2>
                        <span class="date-chip"><?= $tanggal ?></span>
                    </div>
                    <p><?= $roleDesc ?></p>
                </div>

                <div class="hero-actions">
                    <button type="button" class="hero-btn" onclick="window.print()">⇩ Cetak</button>
                    <button type="button" class="hero-btn" onclick="location.reload()" aria-label="Muat ulang">⟳</button>
                </div>
            </div>

            <div class="hero-stats">
                <?php foreach ($kpi as [$label, $nilai, $ket, $ikon, $naik]): ?>
                    <div class="hero-tile">
                        <div class="tile-head">
                            <span class="tile-icon"><?= $ikon ?></span>
                            <span><?= $label ?></span>
                        </div>
                        <strong><?= $nilai ?></strong>
                        <small class="<?= $naik === true ? 'up' : ($naik === false ? 'down' : '') ?>"><?= $ket ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>


        <?php if ($queryGagal && $role === 'admin'): ?>
            <div class="notice">
                <b>Sebagian data belum tampil.</b>
                Nama tabel atau kolom belum cocok untuk:
                <?= htmlspecialchars(implode(', ', array_unique($queryGagal))) ?>.
                Sesuaikan blok KONFIGURASI di bagian atas <code>dashboard.php</code>.
            </div>
        <?php endif; ?>


        <?php if ($adaChart || $canInventory): ?>
        <section class="grid-2 <?= ($adaChart && $canInventory) ? '' : 'single' ?>">

            <?php if ($adaChart): ?>
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2><?= $modeUang ? 'Pemasukan 6 Bulan Terakhir' : 'Pesanan 6 Bulan Terakhir' ?></h2>
                        <p class="panel-sub">
                            <?= $modeUang ? 'Tinggi batang = pemasukan, angka di bawah = jumlah pesanan' : 'Jumlah pesanan per bulan' ?>
                        </p>
                    </div>
                    <span class="chip"><?= $modeUang ? rp($totalSeri) : $totalSeri . ' pesanan' ?></span>
                </div>

                <div class="chart">
                    <?php foreach ($seri as $s): ?>
                        <div class="col <?= $s['ini'] ? 'now' : '' ?>">
                            <div class="barwrap">
                                <span class="val"><?= $modeUang ? rpSingkat($s['v']) : $s['v'] ?></span>
                                <div class="bar" style="height: <?= max(3, round($s['v'] / $maxSeri * 85)) ?>%"></div>
                            </div>
                            <b class="lbl"><?= $s['lab'] ?></b>
                            <?php if ($s['sub']): ?><small><?= $s['sub'] ?></small><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($canInventory): ?>
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Stok Menipis</h2>
                        <p class="panel-sub">Segera restock</p>
                    </div>
                    <a href="inventory.php" class="link-more">Lihat semua</a>
                </div>

                <?php if ($stokMenipis): ?>
                    <ul class="stock-list">
                        <?php foreach ($stokMenipis as $b): ?>
                            <li class="searchable" data-text="<?= htmlspecialchars(strtolower($b['nama'])) ?>">
                                <span><?= htmlspecialchars($b['nama']) ?></span>
                                <b class="<?= (int) $b['stok'] <= 0 ? 'habis' : '' ?>">
                                    <?= (int) $b['stok'] <= 0 ? 'Habis' : (int) $b['stok'] . ' sisa' ?>
                                </b>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty">Semua stok aman.</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </section>
        <?php endif; ?>


        <?php if ($canPesanan): ?>
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Pesanan Terbaru</h2>
                    <p class="panel-sub">6 pesanan terakhir yang masuk</p>
                </div>
                <a href="daftar_pesanan.php" class="link-more">Lihat semua</a>
            </div>

            <?php if ($pesananBaru): ?>
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Pembeli</th>
                                <th>Tanggal</th>
                                <th class="right">Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pesananBaru as $p): ?>
                                <?php $ts = strtotime($p['tgl']); ?>
                                <tr class="searchable" data-text="<?= htmlspecialchars(strtolower($p['nama'] . ' ' . $p['status'])) ?>">
                                    <td><b><?= htmlspecialchars((string) $p['nama']) ?></b></td>
                                    <td><?= $ts ? date('j', $ts) . ' ' . $bln3[(int) date('n', $ts)] . ', ' . date('H:i', $ts) : '-' ?></td>
                                    <td class="right"><?= rp($p['total']) ?></td>
                                    <td><span class="badge <?= kelasStatus($p['status']) ?>"><?= htmlspecialchars((string) $p['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty">Belum ada pesanan.</div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

    </main>

</div>

<script>
const body = document.body;
const isMobile = () => window.innerWidth <= 900;

function toggleSidebar() {
    body.classList.toggle(isMobile() ? 'sidebar-open' : 'sidebar-collapsed');
}

document.getElementById('sidebarToggle').addEventListener('click', toggleSidebar);
document.getElementById('mobileToggle').addEventListener('click', toggleSidebar);
document.getElementById('sidebarOverlay').addEventListener('click', () => body.classList.remove('sidebar-open'));

// Notifikasi
const notifPop = document.getElementById('notifPop');
document.getElementById('notifBtn').addEventListener('click', e => {
    e.stopPropagation();
    notifPop.classList.toggle('show');
});
document.addEventListener('click', e => {
    if (!notifPop.contains(e.target)) notifPop.classList.remove('show');
});

// Cari pesanan / stok di halaman
document.getElementById('pageSearch').addEventListener('input', e => {
    const q = e.target.value.trim().toLowerCase();
    document.querySelectorAll('.searchable').forEach(el => {
        el.style.display = el.dataset.text.includes(q) ? '' : 'none';
    });
});
</script>
</body>
</html>