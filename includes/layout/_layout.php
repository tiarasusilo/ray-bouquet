
<?php
/*
|--------------------------------------------------------------------------
| Layout bersama halaman admin (sidebar + topbar)
|--------------------------------------------------------------------------
*/

function layoutAtas(string $judul, string $sub, string $aktif, array $css = []): void
{
    $role  = $_SESSION['tipe_akun'] ?? '';
    $nama  = $_SESSION['nama'] ?? 'User';
    $email = $_SESSION['email'] ?? '';

    $roleLabel = [
        'admin' => 'Admin',
        'manager' => 'Manager',
        'staff' => 'Staff'
    ];

    $roleTitle = $roleLabel[$role] ?? 'User';
    $inisial = strtoupper(mb_substr($nama, 0, 1));

    $menus = [
        ['manage_user', 'user.php', '♙', 'Pengguna'],
        ['manage_role', 'role.php', '◉', 'Role & Privilege'],
        ['view_produk', 'produk.php', '◇', 'Produk'],
        ['view_pesanan', 'daftar_pesanan.php', '□', 'Pesanan'],
        ['view_inventory', 'inventory.php', '▣', 'Inventory'],
        [
            'view_laporan',
            '#',
            '▥',
            'Laporan',
            [
                ['laporan_penjualan.php', 'Laporan Penjualan'],
                ['laporan_produk.php', 'Laporan Produk'],
                ['laporan_staff.php', 'Laporan Staff'],
                ['keuangan.php', 'Laporan Keuangan'],
            ]
        ],

        ['manage_knowledge', 'kms/knowledge.php', '▤', 'Knowledge'],
        ['manage_faq', 'faq.php', '?', 'FAQ'],
    ];

    // Cek apakah halaman yang sedang dibuka merupakan submenu laporan.
    $laporanAktif = false;

    foreach ($menus as $menu) {
        if (isset($menu[4])) {
            foreach ($menu[4] as $submenu) {
                if ($aktif === $submenu[0]) {
                    $laporanAktif = true;
                    break 2;
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($judul) ?> - RAY Bouquet</title>

    <link rel="stylesheet" href="dashboard.css">

    <?php foreach ($css as $file): ?>
        <link rel="stylesheet"
              href="<?= htmlspecialchars($file) ?>">
    <?php endforeach; ?>

    <style>
        .sidebar-dropdown {
            width: 100%;
        }

        .sidebar-dropdown-toggle {
            width: 100%;
            border: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            text-align: left;
            cursor: pointer;
        }

        .sidebar-dropdown-toggle .dropdown-arrow {
            margin-left: auto;
            font-size: 12px;
            transition: transform .2s ease;
        }

        .sidebar-dropdown-toggle[aria-expanded="true"]
        .dropdown-arrow {
            transform: rotate(180deg);
        }

        .sidebar-submenu {
            display: none;
            padding: 4px 0 4px 42px;
        }

        .sidebar-dropdown.open .sidebar-submenu {
            display: block;
        }


        .sidebar-submenu a {
            display: block;
            padding: 10px 12px;
            margin: 3px 0;
            border-radius: 8px;
            color: #F3E6BD;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            opacity: 1;
            transition: background .2s ease, color .2s ease;
        }

        .sidebar-submenu a:hover {
            background: rgba(243, 230, 189, .15);
            color: #FFFFFF;
        }

        .sidebar-submenu a.active {
            background: #F3E6BD;
            color: #640D14;
            font-weight: 700;
        }

        .sidebar-dropdown.open > .sidebar-dropdown-toggle {
            color: #AD2831;
        }

        .sidebar-submenu a.active {
            font-weight: 600;
        }

        .sidebar-dropdown.open > .sidebar-dropdown-toggle {
            color: #AD2831;
        }
    </style>
</head>

<body>

<aside class="sidebar" id="sidebar">

    <div class="sidebar-head">
        <a href="dashboard.php" class="brand">
            <img src="assets/logoo.png" alt="RAY Bouquet">
            <small><?= htmlspecialchars($roleTitle) ?> Panel</small>
        </a>

        <button class="sidebar-close" id="sidebarToggle"
                type="button" aria-label="Tutup / buka sidebar">
            <span class="icon-close">✕</span>
            <span class="icon-open">☰</span>
        </button>
    </div>

    <nav class="sidebar-menu">

        <a href="dashboard.php"
           class="sidebar-link <?= $aktif === 'dashboard.php' ? 'active' : '' ?>"
           title="Dashboard">
            <span class="sidebar-icon">⌂</span>
            <span class="sidebar-text">Dashboard</span>
        </a>

        <?php foreach ($menus as $menu): ?>
            <?php
                $priv = $menu[0];
                $href = $menu[1];
                $ikon = $menu[2];
                $label = $menu[3];
                $submenuItems = $menu[4] ?? [];

                if (!hasPrivilege($priv)) {
                    continue;
                }

                // Menu tanpa submenu.
                if (empty($submenuItems)):
            ?>
                <a href="<?= htmlspecialchars($href) ?>"
                   class="sidebar-link <?= $aktif === $href ? 'active' : '' ?>"
                   title="<?= htmlspecialchars($label) ?>">
                    <span class="sidebar-icon"><?= $ikon ?></span>
                    <span class="sidebar-text">
                        <?= htmlspecialchars($label) ?>
                    </span>
                </a>

            <?php else: ?>

                <div class="sidebar-dropdown <?= $laporanAktif ? 'open' : '' ?>">

                    <button type="button"
                            class="sidebar-link sidebar-dropdown-toggle <?= $laporanAktif ? 'active' : '' ?>"
                            aria-expanded="<?= $laporanAktif ? 'true' : 'false' ?>"
                            aria-controls="laporanSubmenu">

                        <span class="sidebar-icon"><?= $ikon ?></span>
                        <span class="sidebar-text">
                            <?= htmlspecialchars($label) ?>
                        </span>
                        <span class="dropdown-arrow">⌄</span>
                    </button>

                    <div class="sidebar-submenu" id="laporanSubmenu">

                        <?php foreach ($submenuItems as $submenu): ?>
                            <?php
                                $subHref = $submenu[0];
                                $subLabel = $submenu[1];
                            ?>

                            <a href="<?= htmlspecialchars($subHref) ?>"
                               class="<?= $aktif === $subHref ? 'active' : '' ?>">
                                <?= htmlspecialchars($subLabel) ?>
                            </a>

                        <?php endforeach; ?>

                    </div>
                </div>

            <?php endif; ?>
        <?php endforeach; ?>

    </nav>

    <div class="sidebar-bottom">
        <a href="logout.php"
           class="sidebar-link logout-link"
           title="Logout"
           onclick="return confirm('Yakin ingin logout?')">
            <span class="sidebar-icon">⎋</span>
            <span class="sidebar-text">Logout</span>
        </a>

        <div class="sidebar-user">
            <div class="avatar">
                <?= htmlspecialchars($inisial) ?>
            </div>
            <div class="user-info">
                <strong><?= htmlspecialchars($nama) ?></strong>
                <small>
                    <?= htmlspecialchars($email ?: $roleTitle) ?>
                </small>
            </div>
        </div>
    </div>

</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="main">

    <header class="topbar">
        <div class="topbar-left">

            <button class="mobile-toggle" id="mobileToggle"
                    type="button" aria-label="Buka menu">
                ☰
            </button>

            <div class="page-title">
                <h1><?= htmlspecialchars($judul) ?></h1>
                <small><?= htmlspecialchars($sub) ?></small>
            </div>

        </div>

        <div class="topbar-right">
            <label class="search-box">
                <span>⌕</span>
                <input type="search" id="pageSearch"
                       placeholder="Cari di halaman ini..."
                       autocomplete="off">
            </label>
        </div>
    </header>

    <main class="dashboard-container">

<?php
}

function layoutBawah(): void
{
?>
    </main>
</div>

<script>
const body = document.body;
const isMobile = () => window.innerWidth <= 900;

function toggleSidebar() {
    body.classList.toggle(
        isMobile() ? 'sidebar-open' : 'sidebar-collapsed'
    );
}

document.getElementById('sidebarToggle')
    .addEventListener('click', toggleSidebar);

document.getElementById('mobileToggle')
    .addEventListener('click', toggleSidebar);

document.getElementById('sidebarOverlay')
    .addEventListener('click', () => {
        body.classList.remove('sidebar-open');
    });

// Dropdown submenu.
document.querySelectorAll('.sidebar-dropdown-toggle')
    .forEach(button => {
        button.addEventListener('click', () => {
            const dropdown = button.closest('.sidebar-dropdown');
            const isOpen = dropdown.classList.toggle('open');

            button.setAttribute(
                'aria-expanded',
                isOpen ? 'true' : 'false'
            );
        });
    });

// Pencarian halaman.
document.getElementById('pageSearch')
    .addEventListener('input', e => {
        const q = e.target.value.trim().toLowerCase();
        let tampil = 0;

        document.querySelectorAll('.searchable').forEach(el => {
            const teks = (el.dataset.text || el.textContent).toLowerCase();
            const cocok = teks.includes(q);

            el.style.display = cocok ? '' : 'none';

            if (cocok) tampil++;
        });

        const kosong = document.getElementById('searchEmpty');

        if (kosong) {
            kosong.hidden = tampil > 0;
        }
    });
</script>

</body>
</html>
<?php
}
