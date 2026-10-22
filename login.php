<?php
session_start();
require __DIR__ . '/koneksi.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email dan password wajib diisi.';
    } else {

        $stmt = $pdo_oas->prepare("
            SELECT id_akun, nama, username, password, tipe_akun
            FROM akun
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);
        $akun = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($akun && $password === $akun['password']) {

            // Simpan data akun ke session
            $_SESSION['id_akun'] = $akun['id_akun'];
            $_SESSION['nama'] = $akun['nama'];
            $_SESSION['username'] = $akun['username'];
            $_SESSION['tipe_akun'] = $akun['tipe_akun'];

            // Redirect berdasarkan role
            switch ($akun['tipe_akun']) {

                case 'buyer':
                    header('Location: tps/katalog.php');
                    exit;

                case 'admin':
                    header('Location: dashboard.php');
                    exit;

                case 'manager':
                    header('Location: dashboard.php');
                    exit;

                case 'staff':
                    header('Location: dashboard.php');
                    exit;

                default:
                    header('Location: katalog.php');
                    exit;
            }

        } else {
            $error = 'Email atau password salah.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login | Ray Bouquet</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700;800&family=Poppins:wght@400;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="css/login.css">
</head>

<body>

<div class="page">

    <!-- HEADER -->
    <header class="topbar">

        <div class="brand">
            <img src="assets/logoo.png" alt="Ray Bouquet">
        </div>

        <div class="top-message" id="topMessage">
            Selamat datang kembali 🌷
        </div>

        <a class="contact" href="mailto:halo@raybouquet.com">
            halo@raybouquet.com
        </a>

    </header>


    <!-- MAIN -->
    <main class="main-content" id="main">

        <!-- INTRO -->
        <section class="intro" id="intro">

            <div class="flower-decoration">✿</div>

            <h1>
                Mau<br>
                Bunga<br>
                Apa Hari Ini?
            </h1>

            <span class="mini-tag">
                Rangkai rasa, antar bahagia ✿
            </span>


            <div class="flower-card">

                <img
                    id="flowerPhoto"
                    src="https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=500&q=80"
                    alt="Bunga segar"
                >

                <div class="flower-caption" id="flowerCaption">
                    Fresh Flowers
                </div>

            </div>

        </section>


        <!-- LOGIN -->
        <section class="login-wrapper">

            <div class="login-card">

                <div class="tape"></div>

                <h2>Login</h2>

                <p class="login-description">
                    Masukkan akunmu untuk melanjutkan
                </p>


                <!-- ERROR -->
                <?php if ($error): ?>

                    <div class="error-message">
                        <?= htmlspecialchars($error) ?>
                    </div>

                <?php endif; ?>


                <!-- FORM -->
                <form action="login.php" method="post">

                    <div class="form-group">

                        <label for="email">
                            Email
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="nama@email.com"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="••••••••"
                            required
                        >

                    </div>


                    <button type="submit">
                        Masuk
                    </button>

                </form>


                <div class="login-footer">
                    Belum punya akun? Daftar dulu ✿
                </div>

            </div>

        </section>


        <div class="heart">
            ♥
        </div>

    </main>


    <div class="scallop"></div>


    <!-- FOOTER -->
    <footer class="footer">

        <span>
            © 2026 Ray Bouquet
        </span>

        <span>
            Designed & Developed by Asya Tiara
        </span>

    </footer>

</div>


<script>

    // ==============================
    // PESAN TOPBAR
    // ==============================

    const msgs = [
        "Selamat datang kembali 🌷",
        "Bunga segar tiap hari 🌸",
        "Yuk masuk & belanja ✿"
    ];

    const el = document.getElementById("topMessage");

    let i = 0;

    setInterval(() => {

        el.classList.add("change");

        setTimeout(() => {

            i = (i + 1) % msgs.length;

            el.textContent = msgs[i];

            el.classList.remove("change");

        }, 300);

    }, 3500);



    // ==============================
    // EFEK PARALLAX
    // ==============================

    const intro = document.getElementById("intro");

    document
        .getElementById("main")
        .addEventListener("mousemove", e => {

            const x =
                (e.clientX / innerWidth - .5) * 12;

            const y =
                (e.clientY / innerHeight - .5) * 8;

            intro.style.transform =
                `translate(${x}px,${y}px)`;

        });



    // ==============================
    // FOTO BUNGA BERGANTI
    // ==============================

    const flowerPhoto =
        document.getElementById("flowerPhoto");

    const flowerCaption =
        document.getElementById("flowerCaption");


    const flowerPhotos = [

        {
            image: "https://i.pinimg.com/1200x/a8/05/1c/a8051c908c0f7b927299a690d354dd0b.jpg",
            text: "When yh..."
        },

        {
            image: "https://i.pinimg.com/1200x/17/aa/9e/17aa9e6acc98cad1a38aa471cf977d28.jpg",
            text: "Enjoy ur Grad!"
        },

        {
            image: "https://i.pinimg.com/736x/e6/e2/8d/e6e28d37ea7702c17a9a53442c90475e.jpg",
            text: "Just Pretty Things"
        }

    ];


    let flowerIndex = 0;


    setInterval(() => {

        flowerPhoto.style.opacity = "0";

        setTimeout(() => {

            flowerIndex =
                (flowerIndex + 1) % flowerPhotos.length;

            flowerPhoto.src =
                flowerPhotos[flowerIndex].image;

            flowerCaption.textContent =
                flowerPhotos[flowerIndex].text;

            flowerPhoto.style.opacity = "1";

        }, 500);

    }, 3500);

</script>

</body>
</html>