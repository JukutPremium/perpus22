<?php
// includes/header.php
$flash = getFlash();
$user = $GLOBALS['user'] ?? getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? APP_NAME ?> — <?= APP_SCHOOL ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Cormorant+Garamond:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --sidebar: #16213e;
            --sidebar-deeper: #0f172a;
            --accent: #c9a96e;
            --accent-soft: rgba(201, 169, 110, 0.12);
            --accent-glow: rgba(201, 169, 110, 0.25);

            --bg: #f4f6f9;
            --surface: #ffffff;
            --border: #e5e9f0;
            --shadow: 0 1px 3px rgba(22, 33, 62, 0.06), 0 8px 28px rgba(22, 33, 62, 0.07);

            --ink-1: #0f172a;
            --ink-2: #374151;
            --ink-3: #6b7280;
            --ink-4: #9ca3af;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--ink-2);
            font-size: 14px;
        }

        h1,
        h2,
        h3 {
            font-family: 'Cormorant Garamond', serif;
            font-weight: 600;
            color: var(--ink-1);
        }

        /* ── SIDEBAR ── */
        .sidebar {
            background: var(--sidebar);
            background-image:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(201, 169, 110, 0.06) 0%, transparent 70%),
                linear-gradient(180deg, #16213e 0%, #0f172a 100%);
        }

        .sidebar-divider {
            border-color: rgba(255, 255, 255, 0.06);
        }

        .nav-section-label {
            font-family: 'DM Sans', sans-serif;
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.22);
            padding: 0 12px 6px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.45);
            transition: all 0.18s ease;
            border: 1px solid transparent;
            margin-bottom: 1px;
        }

        .nav-link i {
            width: 16px;
            text-align: center;
            font-size: 13px;
            opacity: 0.65;
            transition: opacity 0.18s;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.85);
        }

        .nav-link:hover i {
            opacity: 1;
        }

        .nav-link.active {
            background: var(--accent-soft);
            border-color: rgba(201, 169, 110, 0.2);
            color: var(--accent);
            font-weight: 500;
        }

        .nav-link.active i {
            opacity: 1;
            color: var(--accent);
        }

        /* ── LOGO BADGE ── */
        .logo-badge {
            background: linear-gradient(135deg, var(--accent) 0%, #b8935a 100%);
            box-shadow: 0 2px 10px rgba(201, 169, 110, 0.35);
        }

        /* ── AVATAR ── */
        .user-avatar {
            background: linear-gradient(135deg, rgba(201, 169, 110, 0.25), rgba(201, 169, 110, 0.1));
            border: 1px solid rgba(201, 169, 110, 0.3);
            color: var(--accent);
            font-weight: 600;
        }

        /* ── CARD ── */
        .card {
            background: var(--surface);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }

        /* ── STAT CARDS — coloured accents ── */
        .stat-icon-blue {
            background: #eff6ff;
            color: #2563eb;
        }

        .stat-icon-teal {
            background: #f0fdfa;
            color: #0d9488;
        }

        .stat-icon-amber {
            background: #fffbeb;
            color: #d97706;
        }

        .stat-icon-rose {
            background: #fff1f2;
            color: #e11d48;
        }

        .stat-tag-blue {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .stat-tag-teal {
            background: #f0fdfa;
            color: #0f766e;
        }

        .stat-tag-amber {
            background: #fef3c7;
            color: #b45309;
        }

        .stat-tag-rose {
            background: #ffe4e6;
            color: #be123c;
        }

        /* ── BADGES — status with colour ── */
        .badge-dipinjam {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde047;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 9px;
            border-radius: 999px;
        }

        .badge-dikembalikan {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 9px;
            border-radius: 999px;
        }

        .badge-terlambat {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 9px;
            border-radius: 999px;
        }

        /* ── BUTTONS ── */
        .btn-primary {
            background: linear-gradient(135deg, #1e3a5f 0%, #16213e 100%);
            color: #fff;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(22, 33, 62, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #243d6a 0%, #1a2845 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(22, 33, 62, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #fff;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25);
        }

        .btn-danger:hover {
            filter: brightness(1.08);
            transform: translateY(-1px);
        }

        /* ── TABLE ── */
        .table-row {
            transition: background 0.12s;
        }

        .table-row:hover {
            background: #f8fafc;
        }

        /* ── ALERTS ── */
        @keyframes slideIn {
            from {
                transform: translateY(-6px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert {
            animation: slideIn 0.22s ease;
        }

        /* ── MOBILE ── */
        #sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(2px);
            z-index: 29;
        }

        #sidebar-overlay.active {
            display: block;
        }

        @media (max-width: 1023px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.24s cubic-bezier(.4, 0, .2, 1);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            main.main-content {
                margin-left: 0 !important;
                padding-top: 5rem;
            }
        }

        /* ── TOPBAR ── */
        #topbar {
            background: var(--sidebar);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(22, 33, 62, 0.15);
            border-radius: 2px;
        }

        /* ── SUBTLE PAGE DECORATIONS ── */
        .page-header {
            position: relative;
        }

        .page-header::after {
            content: '';
            display: block;
            width: 40px;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), transparent);
            border-radius: 2px;
            margin-top: 8px;
        }
    </style>
</head>

<body class="min-h-screen">

    <?php if (isLoggedIn()): ?>

        <!-- MOBILE TOPBAR -->
        <div id="topbar" style="display:none" class="fixed top-0 left-0 right-0 z-40 px-4 py-3 flex items-center gap-3">
            <button id="hamburger"
                class="text-white/60 hover:text-white w-8 h-8 flex items-center justify-center transition-colors">
                <i class="fas fa-bars"></i>
            </button>
            <div class="flex items-center gap-2.5">
                <div class="logo-badge w-7 h-7 rounded-lg flex items-center justify-center">
                    <i class="fas fa-book-open text-white text-xs"></i>
                </div>
                <span class="text-white font-semibold text-sm tracking-tight"><?= APP_NAME ?></span>
            </div>
        </div>

        <div id="sidebar-overlay"></div>

        <div class="flex min-h-screen">
            <!-- SIDEBAR -->
            <aside class="sidebar w-[240px] min-h-screen fixed left-0 top-0 z-30 flex flex-col">

                <!-- Logo -->
                <div class="px-5 py-5 sidebar-divider border-b">
                    <div class="flex items-center gap-3">
                        <div class="logo-badge w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-book-open text-white text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-white font-semibold text-sm leading-tight tracking-tight truncate">
                                <?= APP_NAME ?></p>
                            <p class="text-white/28 text-[11px] mt-0.5 truncate" style="color:rgba(255,255,255,0.28)">
                                <?= APP_SCHOOL ?></p>
                        </div>
                    </div>
                </div>

                <!-- User Info -->
                <div class="px-5 py-4 sidebar-divider border-b">
                    <div class="flex items-center gap-3">
                        <div class="user-avatar w-8 h-8 rounded-lg flex items-center justify-center text-sm flex-shrink-0">
                            <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                        </div>
                        <div class="overflow-hidden">
                            <p class="text-white/85 text-[13px] font-medium truncate"><?= htmlspecialchars($user['nama']) ?>
                            </p>
                            <p class="text-white/30 text-[11px] capitalize">
                                <?= $user['role'] ?>    <?= $user['no_anggota'] ? ' · ' . $user['no_anggota'] : '' ?></p>
                        </div>
                    </div>
                </div>

                <!-- Nav -->
                <nav class="flex-1 px-3 py-4 overflow-y-auto space-y-0">
                    <?php if (isAdmin()): ?>
                        <p class="nav-section-label mt-1 mb-2">Overview</p>
                        <a href="/pages/admin/dashboard.php"
                            class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'admin/dashboard') !== false) ? 'active' : '' ?>">
                            <i class="fas fa-gauge-high"></i> Dashboard
                        </a>
                        <p class="nav-section-label mt-4 mb-2">Manajemen</p>
                        <a href="/pages/admin/buku.php"
                            class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'admin/buku') !== false) ? 'active' : '' ?>">
                            <i class="fas fa-book"></i> Data Buku
                        </a>
                        <a href="/pages/admin/anggota.php"
                            class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'admin/anggota') !== false) ? 'active' : '' ?>">
                            <i class="fas fa-users"></i> Kelola Anggota
                        </a>
                        <a href="/pages/admin/admin.php"
                            class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'admin/admin') !== false) ? 'active' : '' ?>">
                            <i class="fas fa-user-shield"></i> Kelola Admin
                        </a>
                        <a href="/pages/admin/transaksi.php"
                            class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'admin/transaksi') !== false) ? 'active' : '' ?>">
                            <i class="fas fa-right-left"></i> Transaksi
                        </a>
                        <a href="/pages/admin/denda.php"
                            class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'admin/denda') !== false) ? 'active' : '' ?>">
                            <i class="fas fa-coins"></i> Kelola Denda
                            <?php
                            $cntDenda = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE denda>0 AND denda_terbayar=0")->fetchColumn();
                            if ($cntDenda > 0): ?>
                                <span
                                    class="ml-auto text-[10px] bg-rose-500 text-white px-1.5 py-0.5 rounded-full font-bold min-w-[18px] text-center"><?= $cntDenda ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/pages/admin/laporan.php"
                            class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'admin/laporan') !== false) ? 'active' : '' ?>">
                            <i class="fas fa-chart-bar"></i> Laporan
                        </a>
                    <?php else: ?>
                        <p class="nav-section-label mt-1 mb-2">Menu</p>
                        <a href="/pages/siswa/dashboard.php"
                            class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'siswa/dashboard') !== false) ? 'active' : '' ?>">
                            <i class="fas fa-gauge-high"></i> Dashboard
                        </a>
                        <a href="/pages/siswa/katalog.php"
                            class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'katalog') !== false) ? 'active' : '' ?>">
                            <i class="fas fa-search"></i> Katalog Buku
                        </a>
                        <a href="/pages/siswa/peminjaman.php"
                            class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'siswa/peminjaman') !== false) ? 'active' : '' ?>">
                            <i class="fas fa-book-bookmark"></i> Peminjaman Saya
                        </a>
                        <a href="/pages/siswa/denda.php"
                            class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'siswa/denda') !== false) ? 'active' : '' ?>">
                            <i class="fas fa-coins"></i> Denda Saya
                            <?php
                            $cntDendaSiswa = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE user_id=? AND denda>0 AND denda_terbayar=0");
                            $cntDendaSiswa->execute([$user['id']]);
                            $cntDendaSiswa = $cntDendaSiswa->fetchColumn();
                            if ($cntDendaSiswa > 0): ?>
                                <span
                                    class="ml-auto text-[10px] bg-rose-500 text-white px-1.5 py-0.5 rounded-full font-bold min-w-[18px] text-center"><?= $cntDendaSiswa ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/pages/siswa/profil.php"
                            class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'profil') !== false) ? 'active' : '' ?>">
                            <i class="fas fa-user"></i> Profil Saya
                        </a>
                    <?php endif; ?>
                </nav>

                <!-- Logout -->
                <div class="px-3 py-4 border-t sidebar-divider">
                    <a href="/logout.php"
                        class="nav-link hover:text-rose-400 hover:bg-rose-500/10 hover:border-rose-500/10">
                        <i class="fas fa-right-from-bracket"></i> Keluar
                    </a>
                </div>
            </aside>

            <!-- MAIN -->
            <main class="main-content ml-[240px] flex-1 min-w-0 p-7">
                <?php if ($flash): ?>
                    <div class="alert mb-5 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2
                    <?= $flash['type'] === 'success'
                        ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                        : 'bg-red-50 text-red-800 border border-red-200' ?>">
                        <i
                            class="fas <?= $flash['type'] === 'success' ? 'fa-circle-check text-emerald-500' : 'fa-circle-exclamation text-red-500' ?>"></i>
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

            <script>
                const hamburger = document.getElementById('hamburger');
                const sidebar = document.querySelector('.sidebar');
                const overlay = document.getElementById('sidebar-overlay');
                const topbar = document.getElementById('topbar');

                function checkTopbar() {
                    if (!topbar) return;
                    topbar.style.display = window.innerWidth < 1024 ? 'flex' : 'none';
                }
                checkTopbar();
                window.addEventListener('resize', checkTopbar);

                if (hamburger) {
                    hamburger.addEventListener('click', () => {
                        sidebar.classList.toggle('open');
                        overlay.classList.toggle('active');
                    });
                    overlay.addEventListener('click', () => {
                        sidebar.classList.remove('open');
                        overlay.classList.remove('active');
                    });
                }
            </script>