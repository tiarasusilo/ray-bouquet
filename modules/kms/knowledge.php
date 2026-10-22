<?php

session_start();

require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/koneksi_kms.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../_layout.php';

if (!isset($_SESSION['tipe_akun'])) {
    header("Location: ../login.php");
    exit;
}

if (!hasPrivilege('manage_knowledge')) {
    die("Anda tidak memiliki akses ke halaman Knowledge.");
}

/*
|--------------------------------------------------------------------------
| Ambil data artikel
|--------------------------------------------------------------------------
*/

$query = "
    SELECT
        ka.id,
        ka.judul,
        ka.isi,
        ka.penulis,
        ka.status,
        ka.created_at,
        kc.nama_kategori
    FROM knowledge_articles ka
    LEFT JOIN knowledge_categories kc
        ON ka.category_id = kc.id
    ORDER BY ka.created_at DESC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query error: " . mysqli_error($conn));
}


/*
|--------------------------------------------------------------------------
| Ambil kategori
|--------------------------------------------------------------------------
*/

$categoryQuery = mysqli_query(
    $conn,
    "
    SELECT id, nama_kategori
    FROM knowledge_categories
    ORDER BY nama_kategori ASC
    "
);


/*
|--------------------------------------------------------------------------
| Statistik
|--------------------------------------------------------------------------
*/

$totalArtikel = 0;
$totalPublished = 0;
$totalDraft = 0;

$articles = [];

while ($article = mysqli_fetch_assoc($result)) {

    $articles[] = $article;

    $totalArtikel++;

    if ($article['status'] === 'published') {
        $totalPublished++;
    }

    if ($article['status'] === 'draft') {
        $totalDraft++;
    }
}

?>


<?php

layoutAtas(
    'Knowledge',
    'Kelola panduan, SOP, dan pengetahuan RAY Bouquet',
    'knowledge.php',
    ['knowledge.css']
);

?>


<div class="knowledge-page">

    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="knowledge-header">

        <div class="knowledge-heading">

            <span class="page-label">
                KNOWLEDGE MANAGEMENT
            </span>

            <h1>
                Knowledge
            </h1>

            <p>
                Kelola pengetahuan, panduan, dan SOP
                untuk mendukung operasional RAY Bouquet.
            </p>

        </div>


        <a
            href="knowledge_article_add.php"
            class="btn-add"
        >
            <span>+</span>
            Tambah Artikel
        </a>

    </div>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <div class="knowledge-stats">

        <div class="knowledge-stat-card">

            <div class="stat-icon">
                K
            </div>

            <div>
                <span class="stat-label">
                    Total Artikel
                </span>

                <strong>
                    <?= $totalArtikel ?>
                </strong>
            </div>

        </div>


        <div class="knowledge-stat-card">

            <div class="stat-icon published">
                ✓
            </div>

            <div>
                <span class="stat-label">
                    Published
                </span>

                <strong>
                    <?= $totalPublished ?>
                </strong>
            </div>

        </div>


        <div class="knowledge-stat-card">

            <div class="stat-icon draft">
                D
            </div>

            <div>
                <span class="stat-label">
                    Draft
                </span>

                <strong>
                    <?= $totalDraft ?>
                </strong>
            </div>

        </div>

    </div>


    <!-- =====================================================
         TOOLBAR
    ====================================================== -->

    <div class="knowledge-toolbar">

        <div class="search-wrapper">

            <span class="search-icon">
                ⌕
            </span>

            <input
                type="text"
                id="searchInput"
                placeholder="Cari artikel knowledge..."
            >

        </div>


        <select id="categoryFilter">

            <option value="">
                Semua Kategori
            </option>

            <?php while ($category = mysqli_fetch_assoc($categoryQuery)): ?>

                <option
                    value="<?= htmlspecialchars($category['nama_kategori']) ?>"
                >
                    <?= htmlspecialchars($category['nama_kategori']) ?>
                </option>

            <?php endwhile; ?>

        </select>


        <select id="statusFilter">

            <option value="">
                Semua Status
            </option>

            <option value="published">
                Published
            </option>

            <option value="draft">
                Draft
            </option>

        </select>

    </div>


    <!-- =====================================================
         ARTICLE HEADER
    ====================================================== -->

    <div class="content-header">

        <div>

            <h2>
                Daftar Artikel
            </h2>

            <p>
                Panduan dan pengetahuan yang tersedia
                untuk tim RAY Bouquet.
            </p>

        </div>

        <span id="resultCount">
            <?= $totalArtikel ?> artikel
        </span>

    </div>


    <!-- =====================================================
         ARTICLE LIST
    ====================================================== -->

    <div
        class="article-grid"
        id="articleList"
    >

        <?php if (count($articles) > 0): ?>

            <?php foreach ($articles as $article): ?>

                <?php

                $preview = strip_tags($article['isi']);

                if (strlen($preview) > 180) {
                    $preview = substr($preview, 0, 180) . "...";
                }

                $category = $article['nama_kategori']
                    ?? 'Tanpa Kategori';

                $status = strtolower($article['status']);

                ?>

                <article
                    class="article-card"

                    data-title="<?= htmlspecialchars(
                        strtolower($article['judul'])
                    ) ?>"

                    data-category="<?= htmlspecialchars(
                        strtolower($category)
                    ) ?>"

                    data-status="<?= htmlspecialchars($status) ?>"
                >


                    <!-- CARD TOP -->

                    <div class="article-card-top">

                        <span class="article-category">
                            <?= htmlspecialchars($category) ?>
                        </span>

                        <span
                            class="article-status <?= $status ?>"
                        >
                            <?= ucfirst($status) ?>
                        </span>

                    </div>


                    <!-- TITLE -->

                    <h3 class="article-title">

                        <?= htmlspecialchars(
                            $article['judul']
                        ) ?>

                    </h3>


                    <!-- PREVIEW -->

                    <p class="article-preview">

                        <?= htmlspecialchars($preview) ?>

                    </p>


                    <!-- INFO -->

                    <div class="article-meta">

                        <span>
                            Oleh
                            <strong>
                                <?= htmlspecialchars(
                                    $article['penulis'] ?? '-'
                                ) ?>
                            </strong>
                        </span>

                        <span>
                            <?= date(
                                'd M Y',
                                strtotime($article['created_at'])
                            ) ?>
                        </span>

                    </div>


                    <!-- ACTION -->

                    <div class="article-actions">

                        <a
                            href="knowledge_article_view.php?id=<?= $article['id'] ?>"
                            class="btn-view"
                        >
                            Lihat
                        </a>


                        <a
                            href="knowledge_article_edit.php?id=<?= $article['id'] ?>"
                            class="btn-edit"
                        >
                            Edit
                        </a>


                        <a
                            href="knowledge_article_delete.php?id=<?= $article['id'] ?>"
                            class="btn-delete"

                            onclick="
                                return confirm(
                                    'Yakin ingin menghapus artikel ini?'
                                );
                            "
                        >
                            Hapus
                        </a>

                    </div>

                </article>

            <?php endforeach; ?>


            <!-- EMPTY SEARCH -->

            <div
                class="no-result"
                id="noResult"
                style="display:none;"
            >

                <div class="no-result-icon">
                    ?
                </div>

                <h3>
                    Artikel tidak ditemukan
                </h3>

                <p>
                    Coba gunakan kata kunci atau filter yang berbeda.
                </p>

            </div>


        <?php else: ?>


            <!-- EMPTY DATABASE -->

            <div class="empty-state">

                <div class="empty-icon">
                    K
                </div>

                <h3>
                    Belum Ada Knowledge Article
                </h3>

                <p>
                    Belum ada artikel knowledge yang dibuat.
                </p>

                <a
                    href="knowledge_article_add.php"
                    class="btn-add"
                >
                    <span>+</span>
                    Tambah Artikel
                </a>

            </div>

        <?php endif; ?>

    </div>

</div>


<script>

const searchInput =
    document.getElementById("searchInput");

const categoryFilter =
    document.getElementById("categoryFilter");

const statusFilter =
    document.getElementById("statusFilter");

const articles =
    document.querySelectorAll(".article-card");

const noResult =
    document.getElementById("noResult");

const resultCount =
    document.getElementById("resultCount");


function filterArticles() {

    const search =
        searchInput.value.toLowerCase().trim();

    const category =
        categoryFilter.value.toLowerCase();

    const status =
        statusFilter.value.toLowerCase();

    let visibleCount = 0;


    articles.forEach(article => {

        const title =
            article.dataset.title;

        const articleCategory =
            article.dataset.category;

        const articleStatus =
            article.dataset.status;


        const matchSearch =
            title.includes(search);


        const matchCategory =
            category === ""
            || articleCategory === category;


        const matchStatus =
            status === ""
            || articleStatus === status;


        if (
            matchSearch &&
            matchCategory &&
            matchStatus
        ) {

            article.style.display = "";

            visibleCount++;

        } else {

            article.style.display = "none";

        }

    });


    if (visibleCount === 0 && articles.length > 0) {

        noResult.style.display = "block";

    } else {

        noResult.style.display = "none";

    }


    resultCount.textContent =
        visibleCount + " artikel";

}


searchInput.addEventListener(
    "input",
    filterArticles
);


categoryFilter.addEventListener(
    "change",
    filterArticles
);


statusFilter.addEventListener(
    "change",
    filterArticles
);

</script>


<?php

layoutBawah();

?>