<?php 
session_start(); 
date_default_timezone_set('Asia/Jakarta'); 
 
require __DIR__ . '/../koneksi.php'; 
 
if (empty($_SESSION['id_akun'])) { 
    header('Location: ../login.php'); 
    exit; 
} 
 
$idAkun = (int) $_SESSION['id_akun']; 
 
function rp($n) { 
    return 'Rp ' . number_format((float)$n, 0, ',', '.'); 
} 
 
function e($s) { 
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); 
} 
 
function formatTanggal($tanggal) { 
    if (!$tanggal) { 
        return '-'; 
    } 
 
    return date('d M Y', strtotime($tanggal)); 
} 
 
/* ========================================================= 
   AMBIL PESANAN BUYER 
========================================================= */ 
 
$stmt = $pdo->prepare(" 
    SELECT 
        p.id_pesanan, 
        p.nomor_pesanan, 
        p.total_bayar, 
        p.status_pesanan, 
        p.tanggal_kirim, 
        i.id_bouquet, 
        i.jumlah, 
        b.nama_bouquet, 
        b.foto 
    FROM pesanan p 
    LEFT JOIN item_pesanan i 
        ON i.id_pesanan = p.id_pesanan 
    LEFT JOIN bouquet b 
        ON b.id_bouquet = i.id_bouquet 
    WHERE p.id_akun = ? 
    ORDER BY p.id_pesanan DESC 
"); 
 
$stmt->execute([$idAkun]); 
$rows = $stmt->fetchAll(); 
 
/* ========================================================= 
   GABUNG ITEM 
========================================================= */ 
 
$pesanan = []; 
 
foreach ($rows as $row) { 
 
    $idPesanan = $row['id_pesanan']; 
 
    if (!isset($pesanan[$idPesanan])) { 
 
        $pesanan[$idPesanan] = [ 
            'id_pesanan'     => $row['id_pesanan'], 
            'nomor_pesanan'  => $row['nomor_pesanan'], 
            'total_bayar'    => $row['total_bayar'], 
            'status_pesanan' => $row['status_pesanan'], 
            'tanggal_kirim'  => $row['tanggal_kirim'], 
            'items'          => [] 
        ]; 
    } 
 
    if (!empty($row['id_bouquet'])) { 
 
        $pesanan[$idPesanan]['items'][] = [ 
            'nama_bouquet' => $row['nama_bouquet'], 
            'foto'         => $row['foto'], 
            'jumlah'       => $row['jumlah'] 
        ]; 
    } 
} 
 
/* ========================================================= 
   STATUS 
========================================================= */ 
 
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
 
function statusLabel($status) { 
    global $statusList; 
    return $statusList[$status]['label'] ?? ucfirst($status); 
} 
 
function statusClass($status) { 
    global $statusList; 
    return $statusList[$status]['class'] ?? 'waiting'; 
} 
 
function statusIndex($status) { 
    global $statusOrder; 
 
    $index = array_search($status, $statusOrder, true); 
 
    return $index === false ? 0 : $index; 
} 
 
?> 
 
<!DOCTYPE html> 
<html lang="id"> 
 
<head> 
 
    <meta charset="UTF-8"> 
 
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
 
    <title>Pesanan - RAY Bouquet</title> 
 
    <link rel="stylesheet" href="katalog.css"> 
    <link rel="stylesheet" href="pesanan.css?v=10"> 
    <link rel="stylesheet" href="../logout.css"> 
 
</head> 
 
<body> 
 
<!-- ========================================================= 
     HEADER 
========================================================= --> 
 
<header>

    <a href="katalog.php" class="brand">
        <img src="../assets/logoo.png" alt="RAY Bouquet">
    </a>

    <nav>
        <a href="katalog.php">Katalog</a>
        <a href="pesanan.php" class="active">Pesanan</a>
        <a href="../kms/faq.php">FAQ</a>
        <a href="#" class="logout" id="logoutBtn">Logout</a>
    </nav>

</header>
 
<!-- ========================================================= 
     HERO 
========================================================= --> 
 
<section class="hero order-hero"> 
 
    <span class="hero-small"> 
        PESANANKU 
    </span> 
 
    <h1> 
        Pantau pesananmu <span>✦</span> 
    </h1> 
 
    <p> 
        Lihat perjalanan bouquet kamu sampai selesai. 
    </p> 
 
</section> 
 
<!-- ========================================================= 
     CONTENT 
========================================================= --> 
 
<main class="order-wrap"> 
 
<?php if (empty($pesanan)): ?> 
 
    <div class="empty-order"> 
 
        <div class="empty-flower"> 
            ✿ 
        </div> 
 
        <h2> 
            Belum ada pesanan 
        </h2> 
 
        <p> 
            Yuk pilih bouquet favoritmu dan mulai pesan. 
        </p> 
 
        <a href="katalog.php" class="order-btn"> 
            Lihat katalog 
        </a> 
 
    </div> 
 
<?php else: ?> 
 
    <!-- FILTER --> 
 
    <div class="order-filter"> 
 
        <button type="button" class="filter-btn active" data-filter="all"> 
            Semua 
        </button> 
 
        <button type="button" class="filter-btn" data-filter="menunggu"> 
            Menunggu 
        </button> 
 
        <button type="button" class="filter-btn" data-filter="diproses"> 
            Diproses 
        </button> 
 
        <button type="button" class="filter-btn" data-filter="dirangkai"> 
            Dirangkai 
        </button> 
 
        <button type="button" class="filter-btn" data-filter="dikirim"> 
            Dikirim 
        </button> 
 
        <button type="button" class="filter-btn" data-filter="selesai"> 
            Selesai 
        </button> 
 
    </div> 
 
    <!-- LIST PESANAN --> 
 
    <div class="order-list"> 
 
    <?php foreach ($pesanan as $pesananItem): ?> 
 
        <?php 
        $status = $pesananItem['status_pesanan']; 
        $currentIndex = statusIndex($status); 
        ?> 
 
        <article 
            class="order-card" 
            data-status="<?= e($status) ?>" 
        > 
 
            <!-- HEADER CARD --> 
 
            <div class="order-card-head"> 
 
                <div> 
 
                    <span class="order-label"> 
                        NOMOR PESANAN 
                    </span> 
 
                    <h2> 
                        <?= e($pesananItem['nomor_pesanan']) ?> 
                    </h2> 
 
                </div> 
 
                <div class="order-date"> 
 
                    <span> 
                        Tanggal kirim 
                    </span> 
 
                    <strong> 
                        <?= formatTanggal($pesananItem['tanggal_kirim']) ?> 
                    </strong> 
 
                </div> 
 
            </div> 
 
            <!-- BOUQUET --> 
 
            <div class="order-products"> 
 
            <?php foreach ($pesananItem['items'] as $item): ?> 
 
                <div class="order-product"> 
 
                    <?php if (!empty($item['foto'])): ?> 
 
                        <img 
                            src="<?= e($item['foto']) ?>" 
                            alt="<?= e($item['nama_bouquet']) ?>" 
                        > 
 
                    <?php else: ?> 
 
                        <div class="product-noimg"> 
                            ✿ 
                        </div> 
 
                    <?php endif; ?> 
 
                    <div class="product-info"> 
 
                        <strong> 
                            <?= e($item['nama_bouquet']) ?> 
                        </strong> 
 
                        <span> 
                            <?= (int)$item['jumlah'] ?> bouquet 
                        </span> 
 
                    </div> 
 
                </div> 
 
            <?php endforeach; ?> 
 
            </div> 
 
            <!-- STATUS --> 
 
            <div class="order-status"> 
 
                <div class="status-heading"> 
 
                    <span> 
                        Status pesanan 
                    </span> 
 
                    <strong class="<?= e(statusClass($status)) ?>"> 
                        <?= e(statusLabel($status)) ?> 
                    </strong> 
 
                </div> 
 
                <div class="tracking"> 
 
                <?php foreach ($statusOrder as $index => $statusKey): ?> 
 
                    <?php 
                    $isDone = $index < $currentIndex; 
                    $isCurrent = $index === $currentIndex; 
                    ?> 
 
                    <div class="track-step <?= $isDone ? 'done' : '' ?> <?= $isCurrent ? 'current' : '' ?>"> 
 
                        <div class="track-dot"> 
 
                            <?php if ($isDone): ?> 
                                ✓ 
                            <?php elseif ($isCurrent): ?> 
                                ● 
                            <?php endif; ?> 
 
                        </div> 
 
                        <span> 
                            <?= e(statusLabel($statusKey)) ?> 
                        </span> 
 
                    </div> 
 
                    <?php if ($index < count($statusOrder) - 1): ?> 
 
                        <div class="track-line <?= $index < $currentIndex ? 'done' : '' ?>"></div> 
 
                    <?php endif; ?> 
 
                <?php endforeach; ?> 
 
                </div> 
 
            </div> 
 
            <!-- CARD BOTTOM --> 
 
            <div class="order-card-bottom"> 
 
                <div class="order-total"> 
 
                    <span> 
                        Total 
                    </span> 
 
                    <strong> 
                        <?= rp($pesananItem['total_bayar']) ?> 
                    </strong> 
 
                </div> 
 
                <a 
                    href="detail_pesanan.php?id=<?= (int)$pesananItem['id_pesanan'] ?>" 
                    class="order-detail-btn" 
                > 
                    Lihat detail 
                    <span>→</span> 
                </a> 
 
            </div> 
 
        </article> 
 
    <?php endforeach; ?> 
 
    </div> 
 
<?php endif; ?> 
 
</main> 
 
<!-- ========================================================= 
     FOOTER 
========================================================= --> 
 
<footer class="site-footer"> 
 
    <div class="footer-main"> 
 
        <div class="footer-intro"> 
 
            <img src="../assets/logoo.png" alt="RAY Bouquet"> 
 
            <h2> 
                Rangkai rasa,<br> 
                antar bahagia. 
            </h2> 
 
            <div class="footer-social"> 
                <a href="#">Instagram</a> 
                <a href="#">TikTok</a> 
                <a href="#">WhatsApp</a> 
            </div> 
 
        </div> 
 
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
 
<!-- ========================================================= 
     LOGOUT MODAL 
========================================================= --> 
 
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
 
<!-- ========================================================= 
     SCRIPT 
========================================================= --> 
 
<script> 
 
document.addEventListener('DOMContentLoaded', function () { 
 
    /* =========================
       LOGOUT
    ========================= */ 
 
    const logoutBtn = document.getElementById('logoutBtn'); 
    const logoutModal = document.getElementById('logoutModal'); 
    const logoutCancel = document.getElementById('logoutCancel'); 
 
    if (logoutBtn && logoutModal && logoutCancel) { 
 
        logoutBtn.addEventListener('click', function () { 
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
 
 
    /* =========================
       FILTER PESANAN
    ========================= */ 
 
    const buttons = document.querySelectorAll('.filter-btn'); 
    const cards = document.querySelectorAll('.order-card'); 
 
    buttons.forEach(function (button) { 
 
        button.addEventListener('click', function () { 
 
            const filter = this.dataset.filter; 
 
            buttons.forEach(function (btn) { 
                btn.classList.remove('active'); 
            }); 
 
            this.classList.add('active'); 
 
            cards.forEach(function (card) { 
 
                const status = card.dataset.status; 
 
                if (filter === 'all' || status === filter) { 
                    card.style.display = ''; 
                } else { 
                    card.style.display = 'none'; 
                } 
 
            }); 
 
        }); 
 
    }); 
 
}); 
 
</script> 
 
</body> 
</html>