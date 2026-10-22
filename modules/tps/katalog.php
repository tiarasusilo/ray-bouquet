<?php
session_start();
require __DIR__ . '/../koneksi.php';

if (!isset($_SESSION['keranjang']) || !is_array($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

/* =====================================================
   PROSES KERANJANG (dipanggil lewat JavaScript)
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {

    header('Content-Type: application/json; charset=utf-8');

    $aksi  = $_POST['aksi'];
    $id    = (int)($_POST['id'] ?? 0);
    $qty   = (int)($_POST['qty'] ?? 0);
    $pesan = '';

    if ($id > 0) {
        $s = $pdo->prepare('SELECT stok FROM bouquet WHERE id_bouquet = ?');
        $s->execute([$id]);
        $stokDb = $s->fetchColumn();

        if ($stokDb === false) {
            unset($_SESSION['keranjang'][$id]);
            $pesan = 'Bouquet tidak ditemukan.';

        } elseif ($aksi === 'tambah' && $qty > 0) {
            $stokDb = (int)$stokDb;
            $baru   = ($_SESSION['keranjang'][$id] ?? 0) + $qty;
            if ($baru > $stokDb) {
                $baru  = $stokDb;
                $pesan = 'Jumlah disesuaikan dengan stok yang tersedia.';
            }
            if ($baru > 0) $_SESSION['keranjang'][$id] = $baru;

        } elseif ($aksi === 'ubah') {
            if ($qty <= 0) {
                unset($_SESSION['keranjang'][$id]);
            } else {
                $_SESSION['keranjang'][$id] = min($qty, (int)$stokDb);
            }

        } elseif ($aksi === 'hapus') {
            unset($_SESSION['keranjang'][$id]);
        }
    }

    // susun isi keranjang dari data terbaru di database
    $items = [];
    $total = 0;
    $count = 0;

    if (!empty($_SESSION['keranjang'])) {
        $ids = array_map('intval', array_keys($_SESSION['keranjang']));
        $in  = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $pdo->prepare(
            "SELECT id_bouquet, nama_bouquet, harga, stok, foto
             FROM bouquet WHERE id_bouquet IN ($in)"
        );
        $stmt->execute($ids);

        $ada = [];
        foreach ($stmt->fetchAll() as $r) {
            $bid  = (int)$r['id_bouquet'];
            $stok = (int)$r['stok'];
            $q    = min((int)$_SESSION['keranjang'][$bid], $stok);

            if ($q <= 0) {
                unset($_SESSION['keranjang'][$bid]);
                continue;
            }

            $_SESSION['keranjang'][$bid] = $q;
            $ada[$bid] = true;

            $sub = $q * (float)$r['harga'];
            $items[] = [
                'id'       => $bid,
                'nama'     => $r['nama_bouquet'],
                'harga'    => (float)$r['harga'],
                'stok'     => $stok,
                'foto'     => $r['foto'],
                'qty'      => $q,
                'subtotal' => $sub,
            ];
            $total += $sub;
            $count += $q;
        }

        // buang item yang sudah tidak ada di database
        foreach ($ids as $bid) {
            if (!isset($ada[$bid])) unset($_SESSION['keranjang'][$bid]);
        }
    }

    echo json_encode([
        'items' => $items,
        'total' => $total,
        'count' => $count,
        'pesan' => $pesan,
    ]);
    exit;
}

/* =====================================================
   DATA KATALOG
===================================================== */
$stmt = $pdo->query("
    SELECT b.id_bouquet, b.nama_bouquet, b.harga, b.stok,
           b.deskripsi, b.foto, k.nama_kategori
    FROM bouquet b
    JOIN kategori_bouquet k ON k.id_kategori = b.id_kategori
    ORDER BY b.id_bouquet ASC
");
$produk = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Katalog - RAY Bouquet</title>

    <link rel="stylesheet" href="katalog.css?v=2">
    <link rel="stylesheet" href="../logout.css">
</head>

<body>

    <!-- HEADER -->
    <header>

        <a href="katalog.php" class="brand">
            <img src="../assets/logoo.png" alt="RAY Bouquet">
        </a>

        <nav>
            <a href="katalog.php" class="active">Katalog</a>
            <a href="pesanan.php">Pesanan</a>
            <a href="../kms/faq.php">FAQ</a>
            <a href="#" class="logout" id="logoutBtn">Logout</a>
        </nav>

    </header>


    <!-- HERO -->
    <section class="hero">

        <span class="hero-small">RAY BOUQUET</span>

        <h1>
            Make Someone's Day Bloom
            <span>✦</span>
        </h1>

        <p>
            Beri sedikit bunga, buat harinya jadi lebih indah.
        </p>

    </section>


    <!-- PRODUCT -->
    <main class="product-grid">

        <?php if (empty($produk)): ?>

            <p class="empty-catalog">Belum ada bouquet yang tersedia 🌸</p>

        <?php else: ?>
            <?php foreach ($produk as $b): ?>

            <div class="product-card">

                <div class="flower-image">
                    <?php if (!empty($b['foto'])): ?>
                        <img src="<?= htmlspecialchars($b['foto']) ?>"
                             alt="<?= htmlspecialchars($b['nama_bouquet']) ?>">
                    <?php else: ?>
                        <div class="no-image">🌸</div>
                    <?php endif; ?>
                </div>

                <div class="product-info">

                    <div class="category">
                        <?= htmlspecialchars($b['nama_kategori']) ?>
                    </div>

                    <h3><?= htmlspecialchars($b['nama_bouquet']) ?></h3>

                    <p class="description">
                        <?= htmlspecialchars($b['deskripsi'] ?? '') ?>
                    </p>

                    <div class="price">
                        Rp <?= number_format($b['harga'], 0, ',', '.') ?>
                    </div>

                    <div class="stock">
                        <?php if ($b['stok'] > 0): ?>
                            Stok tersedia: <?= (int)$b['stok'] ?>
                        <?php else: ?>
                            Stok habis
                        <?php endif; ?>
                    </div>

                    <?php if ($b['stok'] > 0): ?>
                        <a href="#" class="order-btn"
                           data-id="<?= (int)$b['id_bouquet'] ?>"
                           data-nama="<?= htmlspecialchars($b['nama_bouquet'], ENT_QUOTES) ?>"
                           data-harga="<?= (float)$b['harga'] ?>"
                           data-stok="<?= (int)$b['stok'] ?>"
                           data-foto="<?= htmlspecialchars($b['foto'] ?? '', ENT_QUOTES) ?>">
                            Pesan Bouquet
                        </a>
                    <?php else: ?>
                        <span class="order-btn disabled">Stok Habis</span>
                    <?php endif; ?>

                </div>

            </div>

            <?php endforeach; ?>
        <?php endif; ?>

    </main>

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


    <!-- TOMBOL KERANJANG NGAMBANG -->
    <button type="button" class="cart-fab" id="cartFab" aria-label="Buka keranjang">
        🛒
        <span class="cart-badge" id="cartBadge">0</span>
    </button>

    <!-- PANEL KERANJANG -->
    <div class="cart-overlay" id="cartOverlay"></div>

    <aside class="cart-drawer" id="cartDrawer">
        <div class="cart-head">
            <h3>Keranjang kamu 🌸</h3>
            <button type="button" class="cart-close" id="cartClose" aria-label="Tutup">✕</button>
        </div>

        <div class="cart-list" id="cartList"></div>

        <div class="cart-foot">
            <div class="cart-total-row">
                <span>Total</span>
                <span id="cartTotal">Rp 0</span>
            </div>
            <a href="checkout.php" class="cart-checkout disabled" id="cartCheckout">Lanjut ke checkout</a>
        </div>
    </aside>

    <!-- KOTAK PILIH JUMLAH -->
    <div class="qty-modal" id="qtyModal">
        <div class="qty-card">
            <div class="qty-top">
                <div class="qty-img" id="qtyImg"></div>
                <div>
                    <h4 id="qtyName"></h4>
                    <p id="qtyPrice"></p>
                    <small id="qtyStock"></small>
                </div>
            </div>

            <div class="qty-pick">
                <button type="button" id="qtyMinus" aria-label="Kurangi">−</button>
                <span id="qtyNum">1</span>
                <button type="button" id="qtyPlus" aria-label="Tambah">+</button>
            </div>

            <div class="qty-subtotal">
                <span>Subtotal</span>
                <strong id="qtySub"></strong>
            </div>

            <div class="qty-actions">
                <button type="button" id="qtyCancel">Batal</button>
                <button type="button" id="qtyAdd">Masukkan keranjang</button>
            </div>
        </div>
    </div>

    <div class="cart-toast" id="cartToast"></div>

    <!-- LOGOUT MODAL -->

<div class="logout-modal" id="logoutModal">

    <div class="logout-box">

        <div class="logout-icon">
            ✿
        </div>

        <h2>
            Yakin mau logout?
        </h2>

        <p>
            Kamu akan kembali ke halaman login.
        </p>

        <div class="logout-actions">

            <button
                type="button"
                class="logout-cancel"
                id="logoutCancel"
            >
                Batal
            </button>

            <a
                href="../logout.php"
                class="logout-confirm"
            >
                Logout
            </a>

        </div>

    </div>

</div>

<script>
(function () {
  /* ===== LOGOUT ===== */

  var logoutBtn = document.getElementById('logoutBtn');
  var logoutModal = document.getElementById('logoutModal');
  var logoutCancel = document.getElementById('logoutCancel');

  if (logoutBtn && logoutModal && logoutCancel) {

    logoutBtn.addEventListener('click', function (e) {
      e.preventDefault();
      logoutModal.classList.add('show');
    });

    logoutCancel.addEventListener('click', function () {
      logoutModal.classList.remove('show');
    });

    logoutModal.addEventListener('click', function (e) {
      if (e.target === logoutModal) {
        logoutModal.classList.remove('show');
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        logoutModal.classList.remove('show');
      }
    });

  }
  var API = 'katalog.php';
  var $ = function (id) { return document.getElementById(id); };
  var fmt = function (n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); };
  var esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  var cur = null, qty = 1, toastTimer;

  function call(aksi, data) {
    var body = new URLSearchParams(Object.assign({ aksi: aksi }, data || {}));
    return fetch(API, { method: 'POST', body: body }).then(function (r) { return r.json(); });
  }

  function toast(msg) {
    var t = $('cartToast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { t.classList.remove('show'); }, 2200);
  }

  function render(res) {
    var badge = $('cartBadge');
    badge.textContent = res.count;
    badge.style.display = res.count > 0 ? 'flex' : 'none';

    var list = $('cartList');
    if (!res.items.length) {
      list.innerHTML = '<div class="cart-empty">🌸<p>Keranjangmu masih kosong.</p></div>';
    } else {
      list.innerHTML = res.items.map(function (it) {
        return '<div class="cart-item" data-id="' + it.id + '" data-qty="' + it.qty + '">' +
          (it.foto ? '<img src="' + esc(it.foto) + '" alt="">' : '<div class="cart-noimg">🌸</div>') +
          '<div class="cart-info">' +
            '<div class="cart-name">' + esc(it.nama) + '</div>' +
            '<div class="cart-price">' + fmt(it.harga) + '</div>' +
            '<div class="stepper">' +
              '<button type="button" data-act="kurang" aria-label="Kurangi">−</button>' +
              '<span>' + it.qty + '</span>' +
              '<button type="button" data-act="tambah" aria-label="Tambah"' + (it.qty >= it.stok ? ' disabled' : '') + '>+</button>' +
            '</div>' +
          '</div>' +
          '<div class="cart-side">' +
            '<div class="cart-sub">' + fmt(it.subtotal) + '</div>' +
            '<button type="button" class="cart-del" data-act="hapus">Hapus</button>' +
          '</div>' +
        '</div>';
      }).join('');
    }

    $('cartTotal').textContent = fmt(res.total);
    $('cartCheckout').classList.toggle('disabled', !res.items.length);
  }

  function refresh(res) { render(res); return res; }
  function gagal() { toast('Gagal terhubung ke server.'); }

  /* ===== panel keranjang ===== */
  function openDrawer()  { $('cartDrawer').classList.add('open'); $('cartOverlay').classList.add('show'); }
  function closeDrawer() { $('cartDrawer').classList.remove('open'); $('cartOverlay').classList.remove('show'); }

  $('cartFab').addEventListener('click', openDrawer);
  $('cartClose').addEventListener('click', closeDrawer);
  $('cartOverlay').addEventListener('click', closeDrawer);

  $('cartCheckout').addEventListener('click', function (e) {
    if (this.classList.contains('disabled')) e.preventDefault();
  });

  $('cartList').addEventListener('click', function (e) {
    var btn = e.target.closest('[data-act]');
    if (!btn || btn.disabled) return;
    var row = btn.closest('.cart-item');
    var id = row.dataset.id, q = parseInt(row.dataset.qty, 10);
    var act = btn.dataset.act;

    var req;
    if (act === 'hapus')       req = call('hapus', { id: id });
    else if (act === 'tambah') req = call('ubah', { id: id, qty: q + 1 });
    else                       req = call('ubah', { id: id, qty: q - 1 });

    req.then(refresh).catch(gagal);
  });

  /* ===== kotak pilih jumlah ===== */
  function updateModal() {
    $('qtyNum').textContent = qty;
    $('qtySub').textContent = fmt(qty * cur.harga);
    $('qtyMinus').disabled = qty <= 1;
    $('qtyPlus').disabled = qty >= cur.stok;
  }

  function openModal(d) {
    cur = { id: d.id, nama: d.nama, harga: parseFloat(d.harga), stok: parseInt(d.stok, 10), foto: d.foto };
    qty = 1;
    $('qtyName').textContent = cur.nama;
    $('qtyPrice').textContent = fmt(cur.harga);
    $('qtyStock').textContent = 'Stok tersedia: ' + cur.stok;
    $('qtyImg').innerHTML = cur.foto ? '<img src="' + esc(cur.foto) + '" alt="">' : '🌸';
    updateModal();
    $('qtyModal').classList.add('show');
  }

  function closeModal() { $('qtyModal').classList.remove('show'); }

  document.addEventListener('click', function (e) {
    var a = e.target.closest('.order-btn[data-id]');
    if (a) { e.preventDefault(); openModal(a.dataset); }
  });

  $('qtyMinus').addEventListener('click', function () { if (qty > 1) { qty--; updateModal(); } });
  $('qtyPlus').addEventListener('click', function () { if (qty < cur.stok) { qty++; updateModal(); } });
  $('qtyCancel').addEventListener('click', closeModal);
  $('qtyModal').addEventListener('click', function (e) { if (e.target === this) closeModal(); });

  $('qtyAdd').addEventListener('click', function () {
    call('tambah', { id: cur.id, qty: qty })
      .then(function (res) {
        render(res);
        closeModal();
        toast(res.pesan || 'Ditambahkan ke keranjang 🌸');
        var fab = $('cartFab');
        fab.classList.remove('bump');
        void fab.offsetWidth;
        fab.classList.add('bump');
      })
      .catch(gagal);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeModal(); closeDrawer(); }
  });

  /* ===== muat isi keranjang saat halaman dibuka ===== */
  call('lihat').then(refresh).catch(function () {});
})();
</script>

</body>
</html>