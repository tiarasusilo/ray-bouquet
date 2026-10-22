<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

?>

<!DOCTYPE html>

<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>FAQ - RAY Bouquet</title>

<link rel="stylesheet" href="faq.css">
<link rel="stylesheet" href="../logout.css">

</head>

<body>

<!-- HEADER -->
<header>

    <a href="katalog.php" class="brand">
        <img src="../assets/logoo.png" alt="RAY Bouquet">
    </a>

    <nav>
        <a href="../tps/katalog.php">Katalog</a>
        <a href="../tps/pesanan.php">Pesanan</a>
        <a href="faq.php" class="active">FAQ</a>
        <a href="#" class="logout" id="logoutBtn">Logout</a>
    </nav>

</header>


<!-- HERO -->
<section class="hero">

    <span class="hero-small">RAY BOUQUET</span>

    <h1>
        Frequently Asked Questions
        <span>✦</span>
    </h1>

    <p>
        Temukan jawaban untuk pertanyaan seputar bouquet dan pemesanan.
    </p>

</section>


<!-- FAQ CONTENT -->
<main class="faq-container">

    <!-- SEARCH -->
    <div class="search-box">

        <input
            type="text"
            id="searchFAQ"
            placeholder="Cari pertanyaan...">

    </div>


    <!-- CATEGORY -->
    <div class="category-filter">

        <button class="category-btn active" data-category="all">
            Semua
        </button>

        <button class="category-btn" data-category="pemesanan">
            Pemesanan
        </button>

        <button class="category-btn" data-category="produk">
            Produk
        </button>

        <button class="category-btn" data-category="pembayaran">
            Pembayaran
        </button>

        <button class="category-btn" data-category="pengiriman">
            Pengiriman
        </button>

        <button class="category-btn" data-category="perawatan">
            Perawatan Bunga
        </button>

    </div>


    <!-- FAQ LIST -->
    <section class="faq-list">

        <div class="section-title">
            <span>Pertanyaan yang Sering Ditanyakan</span>
        </div>


        <!-- FAQ 1 -->
        <div class="faq-item" data-category="pemesanan">

            <button class="faq-question">
                <span>Bagaimana cara memesan bouquet?</span>
                <span class="faq-icon">+</span>
            </button>

            <div class="faq-answer">

                <p>
                    Pilih bouquet yang kamu inginkan pada halaman
                    katalog, kemudian klik tombol "Pesan Bouquet"
                    dan lengkapi informasi pemesanan.
                </p>

            </div>

        </div>


        <!-- FAQ 2 -->
        <div class="faq-item" data-category="pemesanan">

            <button class="faq-question">
                <span>Apakah bisa melakukan custom bouquet?</span>
                <span class="faq-icon">+</span>
            </button>

            <div class="faq-answer">

                <p>
                    Bisa. Kamu dapat menyampaikan permintaan custom
                    seperti warna atau jenis bunga melalui catatan
                    pada saat melakukan pemesanan.
                </p>

            </div>

        </div>


        <!-- FAQ 3 -->
        <div class="faq-item" data-category="produk">

            <button class="faq-question">
                <span>Apakah bunga yang digunakan merupakan bunga asli?</span>
                <span class="faq-icon">+</span>
            </button>

            <div class="faq-answer">

                <p>
                    Ya, bouquet RAY Bouquet menggunakan bunga asli.
                    Jenis bunga yang digunakan dapat berbeda sesuai
                    dengan produk yang dipilih.
                </p>

            </div>

        </div>


        <!-- FAQ 4 -->
        <div class="faq-item" data-category="produk">

            <button class="faq-question">
                <span>Apakah stok bouquet selalu tersedia?</span>
                <span class="faq-icon">+</span>
            </button>

            <div class="faq-answer">

                <p>
                    Ketersediaan bouquet dapat berubah sesuai dengan
                    stok yang tersedia. Informasi stok dapat dilihat
                    langsung pada halaman katalog.
                </p>

            </div>

        </div>


        <!-- FAQ 5 -->
        <div class="faq-item" data-category="pembayaran">

            <button class="faq-question">
                <span>Metode pembayaran apa saja yang tersedia?</span>
                <span class="faq-icon">+</span>
            </button>

            <div class="faq-answer">

                <p>
                    Informasi mengenai metode pembayaran akan
                    ditampilkan pada proses pemesanan.
                </p>

            </div>

        </div>


        <!-- FAQ 6 -->
        <div class="faq-item" data-category="pengiriman">

            <button class="faq-question">
                <span>Apakah RAY Bouquet menyediakan pengiriman?</span>
                <span class="faq-icon">+</span>
            </button>

            <div class="faq-answer">

                <p>
                    Ya, RAY Bouquet menyediakan layanan pengiriman
                    sesuai dengan area yang tersedia.
                </p>

            </div>

        </div>


        <!-- FAQ 7 -->
        <div class="faq-item" data-category="pengiriman">

            <button class="faq-question">
                <span>Berapa lama proses pembuatan bouquet?</span>
                <span class="faq-icon">+</span>
            </button>

            <div class="faq-answer">

                <p>
                    Waktu pembuatan bouquet dapat berbeda tergantung
                    jenis dan jumlah pesanan.
                </p>

            </div>

        </div>


        <!-- FAQ 8 -->
        <div class="faq-item" data-category="perawatan">

            <button class="faq-question">
                <span>Bagaimana cara merawat bouquet agar tetap segar?</span>
                <span class="faq-icon">+</span>
            </button>

            <div class="faq-answer">

                <p>
                    Simpan bouquet di tempat yang sejuk dan hindari
                    paparan sinar matahari langsung. Perawatan juga
                    dapat disesuaikan dengan jenis bunga.
                </p>

            </div>

        </div>

    </section>


    <!-- ASK QUESTION -->
    <section class="ask-question">

        <div class="ask-content">

            <span class="ask-small">
                TIDAK MENEMUKAN JAWABAN?
            </span>

            <h2>
                Masih punya pertanyaan?
            </h2>

            <p>
                Ajukan pertanyaan kepada kami dan staff RAY Bouquet
                akan membantu menjawabnya.
            </p>

            <button
                type="button"
                class="ask-btn"
                onclick="openQuestionModal()">

                + Ajukan Pertanyaan

            </button>

        </div>

    </section>

</main>

<!-- LOGOUT MODAL -->
<div class="logout-modal" id="logoutModal">

    <div class="logout-box">

        <div class="logout-icon">✿</div>

        <h2>Yakin mau logout?</h2>

        <p>Kamu akan kembali ke halaman login.</p>

        <div class="logout-actions">

            <button
                type="button"
                class="logout-cancel"
                id="logoutCancel">
                Batal
            </button>

            <a
                href="/msi/logout.php"
                class="logout-confirm">
                Logout
            </a>

        </div>

    </div>

</div>
<!-- QUESTION MODAL -->
<div class="modal-overlay" id="questionModal">

    <div class="modal">

        <button
            type="button"
            class="close-modal"
            onclick="closeQuestionModal()">

            ×

        </button>

        <div class="modal-header">

            <span>RAY BOUQUET</span>

            <h2>Ajukan Pertanyaan</h2>

            <p>
                Tulis pertanyaan yang ingin kamu tanyakan
                kepada staff kami.
            </p>

        </div>


        <form action="#" method="POST">

            <label for="question">
                Pertanyaan
            </label>

            <textarea
                id="question"
                name="question"
                rows="5"
                placeholder="Tulis pertanyaan kamu..."
                required></textarea>


            <div class="modal-actions">

                <button
                    type="button"
                    class="cancel-btn"
                    onclick="closeQuestionModal()">

                    Batal

                </button>

                <button
                    type="submit"
                    class="submit-btn">

                    Kirim Pertanyaan

                </button>

            </div>

        </form>

    </div>

</div>


<!-- JAVASCRIPT -->
<script>

    // FAQ ACCORDION
    const faqQuestions = document.querySelectorAll(".faq-question");

    faqQuestions.forEach(function (question) {

        question.addEventListener("click", function () {

            const faqItem = this.parentElement;

            faqItem.classList.toggle("active");

        });

    });


    // CATEGORY FILTER
    const categoryButtons =
        document.querySelectorAll(".category-btn");

    const faqItems =
        document.querySelectorAll(".faq-item");


    categoryButtons.forEach(function (button) {

        button.addEventListener("click", function () {

            categoryButtons.forEach(function (btn) {
                btn.classList.remove("active");
            });

            this.classList.add("active");

            const category =
                this.getAttribute("data-category");


            faqItems.forEach(function (item) {

                if (
                    category === "all" ||
                    item.getAttribute("data-category") === category
                ) {

                    item.style.display = "block";

                } else {

                    item.style.display = "none";

                }

            });

        });

    });


    // SEARCH FAQ
    const searchInput =
        document.getElementById("searchFAQ");


    searchInput.addEventListener("input", function () {

        const keyword =
            this.value.toLowerCase();


        faqItems.forEach(function (item) {

            const question =
                item.querySelector(".faq-question")
                    .innerText
                    .toLowerCase();

            const answer =
                item.querySelector(".faq-answer")
                    .innerText
                    .toLowerCase();


            if (
                question.includes(keyword) ||
                answer.includes(keyword)
            ) {

                item.style.display = "block";

            } else {

                item.style.display = "none";

            }

        });

    });


    // OPEN MODAL
    function openQuestionModal() {

        document
            .getElementById("questionModal")
            .classList.add("show");

    }


    // CLOSE MODAL
    function closeQuestionModal() {

        document
            .getElementById("questionModal")
            .classList.remove("show");

    }


    // CLOSE MODAL WHEN CLICK OUTSIDE
    document
        .getElementById("questionModal")
        .addEventListener("click", function (event) {

            if (event.target === this) {

                closeQuestionModal();

            }

        });
// LOGOUT MODAL
const logoutBtn = document.getElementById("logoutBtn");
const logoutModal = document.getElementById("logoutModal");
const logoutCancel = document.getElementById("logoutCancel");

logoutBtn.addEventListener("click", function (event) {
    event.preventDefault();
    logoutModal.classList.add("show");
});

logoutCancel.addEventListener("click", function () {
    logoutModal.classList.remove("show");
});

logoutModal.addEventListener("click", function (event) {
    if (event.target === logoutModal) {
        logoutModal.classList.remove("show");
    }
});
</script>

</body>

</html>
