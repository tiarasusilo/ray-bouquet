<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require __DIR__ . '/../koneksi.php';

if (empty($_SESSION['id_akun'])) {
    header('Location: ../login.php');
    exit;
}
$idAkun = (int)$_SESSION['id_akun'];

function rp($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function e($s)  { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$metodeList = [
    'transfer' => ['Transfer Bank',   'Bayar lewat transfer rekening'],
    'qris'     => ['QRIS',            'Scan dari m-banking atau e-wallet'],
    'e_wallet' => ['E-Wallet',        'DANA, OVO, atau GoPay'],
    'cash'     => ['Bayar di tempat', 'Tunai saat bouquet diterima'],
];

// GANTI dengan data pembayaran toko kamu yang asli
$instruksi = [
    'transfer' => 'Transfer ke BCA 1234567890 a.n. Ray Bouquet, lalu kirim bukti transfer lewat WhatsApp.',
    'qris'     => 'Scan QRIS toko kami, lalu kirim bukti pembayaran lewat WhatsApp.',
    'e_wallet' => 'Kirim ke DANA / OVO / GoPay 0812-3456-7890 a.n. Ray Bouquet, lalu kirim bukti lewat WhatsApp.',
    'cash'     => 'Siapkan uang pas dan bayar langsung saat bouquet diterima.',
];

/* =====================================================
   HALAMAN SUKSES
===================================================== */
$sukses = null;
$itemSukses = [];

if (isset($_GET['sukses'])) {
    $q = $pdo->prepare("
        SELECT p.id_pesanan, p.nomor_pesanan, p.total_bayar, p.nama_penerima,
               p.alamat_kirim, p.tanggal_kirim, pb.metode
        FROM pesanan p
        LEFT JOIN pembayaran pb ON pb.id_pesanan = p.id_pesanan
        WHERE p.nomor_pesanan = ? AND p.id_akun = ?
    ");
    $q->execute([$_GET['sukses'], $idAkun]);
    $sukses = $q->fetch();

    if (!$sukses) {
        header('Location: katalog.php');
        exit;
    }

    $q = $pdo->prepare("
        SELECT b.nama_bouquet, i.jumlah, i.subtotal
        FROM item_pesanan i
        JOIN bouquet b ON b.id_bouquet = i.id_bouquet
        WHERE i.id_pesanan = ?
    ");
    $q->execute([$sukses['id_pesanan']]);
    $itemSukses = $q->fetchAll();
}

/* =====================================================
   FORM CHECKOUT
===================================================== */
$items = [];
$total = 0;
$err   = [];
$old   = [
    'nama_penerima' => '',
    'no_hp'         => '',
    'alamat'        => '',
    'tanggal'       => '',
    'kartu'         => '',
    'metode'        => 'transfer',
];

if (!$sukses) {

    $cart = $_SESSION['keranjang'] ?? [];
    if (empty($cart)) {
        header('Location: katalog.php');
        exit;
    }

    if (empty($_SESSION['csrf_co'])) {
        $_SESSION['csrf_co'] = bin2hex(random_bytes(32));
    }

    /* ----- proses saat tombol Buat Pesanan diklik ----- */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $old['nama_penerima'] = trim($_POST['nama_penerima'] ?? '');
        $old['no_hp']         = trim($_POST['no_hp'] ?? '');
        $old['alamat']        = trim($_POST['alamat'] ?? '');
        $old['tanggal']       = trim($_POST['tanggal'] ?? '');
        $old['kartu']         = trim($_POST['kartu'] ?? '');
        $old['metode']        = $_POST['metode'] ?? '';

        if (!hash_equals($_SESSION['csrf_co'], $_POST['csrf'] ?? '')) {
            $err['umum'] = 'Sesi tidak valid, muat ulang halaman lalu coba lagi.';
        }

        $len = function ($s) { return mb_strlen($s, 'UTF-8'); };

        if ($len($old['nama_penerima']) < 2 || $len($old['nama_penerima']) > 100) {
            $err['nama_penerima'] = 'Isi nama penerima (2 sampai 100 huruf).';
        }
        if (!preg_match('/^[0-9+\-\s]{8,20}$/', $old['no_hp'])) {
            $err['no_hp'] = 'Nomor HP tidak valid.';
        }
        if ($len($old['alamat']) < 10) {
            $err['alamat'] = 'Tulis alamat lengkap (minimal 10 karakter).';
        }

        $d = DateTime::createFromFormat('Y-m-d', $old['tanggal']);
        if (!$d || $d->format('Y-m-d') !== $old['tanggal']) {
            $err['tanggal'] = 'Pilih tanggal pengiriman.';
        } elseif ($old['tanggal'] < date('Y-m-d')) {
            $err['tanggal'] = 'Tanggal pengiriman tidak boleh sudah lewat.';
        }

        if ($len($old['kartu']) > 255) {
            $err['kartu'] = 'Kartu ucapan maksimal 255 karakter.';
        }
        if (!isset($metodeList[$old['metode']])) {
            $err['metode'] = 'Pilih metode pembayaran.';
        }

        /* ----- simpan ke database ----- */
        if (empty($err)) {
            try {
                $pdo->beginTransaction();

                $baris = [];
                $totalBayar = 0;

                foreach ($cart as $bid => $qty) {
                    $s = $pdo->prepare(
                        'SELECT id_bouquet, nama_bouquet, harga, stok
                         FROM bouquet WHERE id_bouquet = ? FOR UPDATE'
                    );
                    $s->execute([(int)$bid]);
                    $r = $s->fetch();

                    if (!$r) {
                        throw new RuntimeException('Ada bouquet di keranjang yang sudah tidak tersedia.');
                    }
                    if ((int)$r['stok'] < (int)$qty) {
                        throw new RuntimeException(
                            'Stok ' . $r['nama_bouquet'] . ' tinggal ' . (int)$r['stok'] . '. Ubah jumlah di keranjang.'
                        );
                    }

                    $sub = (int)$qty * (float)$r['harga'];
                    $totalBayar += $sub;
                    $baris[] = [
                        'id'    => (int)$r['id_bouquet'],
                        'qty'   => (int)$qty,
                        'harga' => (float)$r['harga'],
                        'sub'   => $sub,
                    ];
                }

                $nomor = 'RB' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

                $pdo->prepare("
                    INSERT INTO pesanan
                      (nomor_pesanan, id_akun, total_bayar, status_pesanan,
                       nama_penerima, no_hp_penerima, alamat_kirim, tanggal_kirim, kartu_ucapan)
                    VALUES (?, ?, ?, 'menunggu', ?, ?, ?, ?, ?)
                ")->execute([
                    $nomor, $idAkun, $totalBayar,
                    $old['nama_penerima'], $old['no_hp'], $old['alamat'],
                    $old['tanggal'], $old['kartu'] !== '' ? $old['kartu'] : null,
                ]);
                $idPesanan = (int)$pdo->lastInsertId();

                $insItem = $pdo->prepare("
                    INSERT INTO item_pesanan (id_pesanan, id_bouquet, jumlah, harga_satuan, subtotal)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $kurangStok = $pdo->prepare(
                    'UPDATE bouquet SET stok = stok - ? WHERE id_bouquet = ? AND stok >= ?'
                );

                foreach ($baris as $b) {
                    $insItem->execute([$idPesanan, $b['id'], $b['qty'], $b['harga'], $b['sub']]);
                    $kurangStok->execute([$b['qty'], $b['id'], $b['qty']]);
                    if ($kurangStok->rowCount() === 0) {
                        throw new RuntimeException('Stok berubah saat diproses. Coba lagi.');
                    }
                }

                $pdo->prepare("
                    INSERT INTO pembayaran (id_pesanan, metode, jumlah, status)
                    VALUES (?, ?, ?, 'menunggu')
                ")->execute([$idPesanan, $old['metode'], $totalBayar]);

                $pdo->commit();

                $_SESSION['keranjang'] = [];
                unset($_SESSION['csrf_co']);
                header('Location: checkout.php?sukses=' . urlencode($nomor));
                exit;

            } catch (RuntimeException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $err['umum'] = $ex->getMessage();
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $err['umum'] = 'Pesanan gagal disimpan. Coba lagi.';
                // untuk mencari penyebab, buka baris di bawah ini sementara:
                // $err['umum'] = $ex->getMessage();
            }
        }
    }

    /* ----- data ringkasan pesanan ----- */
    $ids = array_map('intval', array_keys($cart));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $pdo->prepare(
        "SELECT id_bouquet, nama_bouquet, harga, foto
         FROM bouquet WHERE id_bouquet IN ($in)"
    );
    $st->execute($ids);

    foreach ($st->fetchAll() as $r) {
        $q   = (int)$cart[$r['id_bouquet']];
        $sub = $q * (float)$r['harga'];
        $items[] = [
            'nama'  => $r['nama_bouquet'],
            'harga' => (float)$r['harga'],
            'foto'  => $r['foto'],
            'qty'   => $q,
            'sub'   => $sub,
        ];
        $total += $sub;
    }

    if (empty($items)) {
        header('Location: katalog.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Checkout - RAY Bouquet</title>

    <link rel="stylesheet" href="katalog.css">
    <link rel="stylesheet" href="checkout.css">
</head>

<body>

    <!-- HEADER -->
    <header>

        <a href="katalog.php" class="brand">
            <img src="../assets/logoo.png" alt="RAY Bouquet">
        </a>

        <nav>
            <a href="katalog.php">Katalog</a>
            <a href="pesanan.php">Pesanan</a>
            <a href="../logout.php" class="logout">Logout</a>
        </nav>

    </header>

<a href="katalog.php" class="back-fab" aria-label="Kembali ke katalog">
    <img src="../assets/arrow.png" alt="">
</a>

<?php if ($sukses): ?>

    <!-- ================= SUKSES ================= -->
    <section class="hero co-hero">
        <span class="hero-small">PESANAN DITERIMA</span>
        <h1>Terima kasih! <span>✦</span></h1>
        <p>Pesananmu sudah tercatat dan siap kami rangkai.</p>
    </section>

    <main class="ok-wrap">
        <div class="co-card ok-card">

            <div class="ok-no"><?= e($sukses['nomor_pesanan']) ?></div>

            <div class="ok-total">
                <span>Total pembayaran</span>
                <strong><?= rp($sukses['total_bayar']) ?></strong>
            </div>

            <div class="ok-pay">
                <b>Cara bayar: <?= e($metodeList[$sukses['metode']][0] ?? '-') ?></b>
                <p><?= e($instruksi[$sukses['metode']] ?? '') ?></p>
            </div>

            <div class="ok-list">
                <?php foreach ($itemSukses as $it): ?>
                    <div class="sum-row">
                        <span><?= e($it['nama_bouquet']) ?> × <?= (int)$it['jumlah'] ?></span>
                        <span><?= rp($it['subtotal']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="ok-ship">
                <b>Dikirim ke</b>
                <p>
                    <?= e($sukses['nama_penerima']) ?><br>
                    <?= nl2br(e($sukses['alamat_kirim'])) ?><br>
                    Tanggal kirim: <?= e(date('d-m-Y', strtotime($sukses['tanggal_kirim']))) ?>
                </p>
            </div>
            <div class="ok-actions"> 
                <a href="pesanan.php" class="co-btn">Lihat pesanan</a> 
            </div>
        </div>
    </main>

<?php else: ?>

    <!-- ================= FORM ================= -->
    <section class="hero co-hero">
        <span class="hero-small">CHECKOUT</span>
        <h1>Hampir selesai <span>✦</span></h1>
        <p>Lengkapi data pengiriman, lalu pesananmu langsung kami proses.</p>
    </section>

    <main class="co-wrap">

        <form method="post" action="checkout.php" id="coForm" novalidate>
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_co']) ?>">

            <?php if (!empty($err['umum'])): ?>
                <div class="co-error"><?= e($err['umum']) ?></div>
            <?php endif; ?>

            <!-- DATA PENERIMA -->
            <div class="co-card">
                <h2>Data penerima</h2>

                <div class="field">
                    <label for="nama_penerima">Nama penerima</label>
                    <input type="text" id="nama_penerima" name="nama_penerima"
                           value="<?= e($old['nama_penerima']) ?>" placeholder="Nama yang akan menerima bouquet">
                    <?php if (isset($err['nama_penerima'])): ?><small class="bad"><?= e($err['nama_penerima']) ?></small><?php endif; ?>
                </div>

                <div class="row2">
                    <div class="field">
                        <label for="no_hp">Nomor HP penerima</label>
                        <input type="tel" id="no_hp" name="no_hp"
                               value="<?= e($old['no_hp']) ?>" placeholder="0812xxxxxxxx">
                        <?php if (isset($err['no_hp'])): ?><small class="bad"><?= e($err['no_hp']) ?></small><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="tanggal">Tanggal kirim</label>
                        <input type="date" id="tanggal" name="tanggal"
                               min="<?= date('Y-m-d') ?>" value="<?= e($old['tanggal']) ?>">
                        <?php if (isset($err['tanggal'])): ?><small class="bad"><?= e($err['tanggal']) ?></small><?php endif; ?>
                    </div>
                </div>

                <div class="field">
                    <label for="alamat">Alamat lengkap</label>
                    <textarea id="alamat" name="alamat"
                              placeholder="Jalan, nomor rumah, kelurahan, patokan"><?= e($old['alamat']) ?></textarea>
                    <?php if (isset($err['alamat'])): ?><small class="bad"><?= e($err['alamat']) ?></small><?php endif; ?>
                </div>

                <div class="field">
                    <label for="kartu">Kartu ucapan <em>(opsional)</em></label>
                    <textarea id="kartu" name="kartu" maxlength="255"
                              placeholder="Tulis pesan manis untuk penerima"><?= e($old['kartu']) ?></textarea>
                    <small><span id="kartuCount"><?= mb_strlen($old['kartu'], 'UTF-8') ?></span>/255</small>
                    <?php if (isset($err['kartu'])): ?><small class="bad"><?= e($err['kartu']) ?></small><?php endif; ?>
                </div>
            </div>

            <!-- METODE BAYAR -->
            <div class="co-card">
                <h2>Metode pembayaran</h2>

                <div class="pay-grid">
                    <?php foreach ($metodeList as $kode => $m): ?>
                        <label class="pay-opt">
                            <input type="radio" name="metode" value="<?= e($kode) ?>"
                                   <?= $old['metode'] === $kode ? 'checked' : '' ?>>
                            <span class="pay-box">
                                <b><?= e($m[0]) ?></b>
                                <small><?= e($m[1]) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($err['metode'])): ?><small class="bad"><?= e($err['metode']) ?></small><?php endif; ?>
            </div>
        </form>


        <!-- RINGKASAN -->
        <aside class="co-card sum-card">
            <h2>Ringkasan pesanan</h2>

            <?php foreach ($items as $it): ?>
                <div class="sum-item">
                    <?php if (!empty($it['foto'])): ?>
                        <img src="<?= e($it['foto']) ?>" alt="">
                    <?php else: ?>
                        <div class="sum-noimg">🌸</div>
                    <?php endif; ?>

                    <div>
                        <div class="sum-name"><?= e($it['nama']) ?></div>
                        <div class="sum-meta"><?= rp($it['harga']) ?> × <?= (int)$it['qty'] ?></div>
                    </div>

                    <div class="sum-sub"><?= rp($it['sub']) ?></div>
                </div>
            <?php endforeach; ?>

            <div class="sum-row"><span>Subtotal</span><span><?= rp($total) ?></span></div>
            <div class="sum-row"><span>Ongkir</span><span>Gratis</span></div>

            <div class="sum-total">
                <span>Total</span>
                <strong><?= rp($total) ?></strong>
            </div>

            <button type="submit" form="coForm" class="co-btn" id="coSubmit">
                Buat Pesanan
            </button>
        </aside>

    </main>

<?php endif; ?>

<footer class="site-footer">

    <div class="footer-main">

        <!-- KIRI -->
        <div class="footer-intro">
            <img src="../assets/logoo.png" alt="RAY Bouquet">

            <h2>Rangkai rasa,<br>antar bahagia.</h2>

            <div class="footer-social">
                <a href="#">Instagram</a>
                <a href="#">TikTok</a>
                <a href="#">WhatsApp</a>
            </div>
        </div>


        <!-- KANAN -->
        <div class="footer-contact">

            <div class="contact-box">
                <h3>Hubungi Kami</h3>

                <a href="#">Instagram</a>
                <a href="#">WhatsApp</a>
                <a href="#">Email</a>
            </div>

            <div class="contact-box">
                <h3>Temukan</h3>

                <a href="katalog.php">Katalog</a>
                <a href="pesanan.php">Pesanan</a>
                <a href="../kms/faq.html">FAQ</a>
            </div>

        </div>

    </div>


    <div class="footer-bottom">
        <span>RAY Bouquet ✿</span>
        <span>© 2026 RAY Bouquet</span>
    </div>

</footer>

<script>
(function () {
  var kartu = document.getElementById('kartu');
  var hitung = document.getElementById('kartuCount');
  if (kartu && hitung) {
    kartu.addEventListener('input', function () { hitung.textContent = kartu.value.length; });
  }

  var form = document.getElementById('coForm');
  var btn  = document.getElementById('coSubmit');
  if (form && btn) {
    form.addEventListener('submit', function () {
      btn.disabled = true;
      btn.textContent = 'Memproses…';
    });
  }
})();
</script>

</body>
</html>