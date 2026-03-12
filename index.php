<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header(isAdmin()
        ? 'Location: /perpustakaan/pages/admin/dashboard.php'
        : 'Location: /perpustakaan/pages/siswa/dashboard.php');
    exit;
}

$error = $success = '';
$mode  = $_GET['mode'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $nama = trim($_POST['nama'] ?? ''); $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; $kelas = trim($_POST['kelas'] ?? '');
    if (!$nama || !$email || !$password) {
        $error = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?"); $stmt->execute([$email]);
        if ($stmt->fetch()) { $error = 'Email sudah terdaftar.'; }
        else {
            $n = 'SIS-' . str_pad($pdo->query("SELECT COUNT(*) FROM users WHERE role='siswa'")->fetchColumn() + 1, 3, '0', STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO users (nama,email,password,role,no_anggota,kelas) VALUES (?,?,?,'siswa',?,?)")
                ->execute([$nama, $email, password_hash($password, PASSWORD_BCRYPT), $n, $kelas]);
            $success = 'Pendaftaran berhasil! Silakan login.'; $mode = 'login';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $email = trim($_POST['email'] ?? ''); $password = $_POST['password'] ?? '';
    if (!$email || !$password) { $error = 'Email dan password wajib diisi.'; }
    else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'aktif'"); $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id']; $_SESSION['nama'] = $user['nama'];
            $_SESSION['email'] = $user['email']; $_SESSION['role'] = $user['role'];
            $_SESSION['no_anggota'] = $user['no_anggota'];
            header($user['role'] === 'admin'
                ? 'Location: /perpustakaan/pages/admin/dashboard.php'
                : 'Location: /perpustakaan/pages/siswa/dashboard.php'); exit;
        } else { $error = 'Email atau password salah, atau akun tidak aktif.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — <?= APP_SCHOOL ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DM Sans', sans-serif; margin: 0; }

        /* Left: deep navy */
        .panel-left {
            background: #0f172a;
            background-image:
                radial-gradient(ellipse 100% 70% at 50% 0%, rgba(201,169,110,0.08) 0%, transparent 65%),
                radial-gradient(ellipse 60% 40% at 20% 80%, rgba(30,58,138,0.35) 0%, transparent 60%);
            position: relative;
            overflow: hidden;
        }

        .panel-left::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.025) 1px, transparent 0);
            background-size: 30px 30px;
        }

        /* Right: warm off-white */
        .panel-right { background: #f8f7f5; }

        .form-card {
            background: #fff;
            border: 1px solid #e8e4de;
            box-shadow: 0 2px 6px rgba(15,23,42,0.05), 0 20px 50px rgba(15,23,42,0.08);
        }

        /* Inputs */
        .inp {
            background: #faf9f7;
            border: 1px solid #ddd9d3;
            color: #1a1a2e;
            transition: all 0.18s;
            width: 100%; padding: 11px 12px 11px 36px;
            border-radius: 10px; font-size: 13.5px;
            font-family: 'DM Sans', sans-serif;
        }
        .inp:focus { outline: none; border-color: #c9a96e; background: #fff; box-shadow: 0 0 0 3px rgba(201,169,110,0.12); }
        .inp::placeholder { color: #bbb; }
        .inp-icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #bbb; font-size: 12px; }

        /* Tabs */
        .tab-wrap { background: #f0ece6; border-radius: 10px; padding: 3px; }
        .tab {
            flex: 1; padding: 9px; text-align: center;
            font-size: 13px; font-weight: 500;
            border-radius: 8px;
            color: #9a9188; cursor: pointer;
            text-decoration: none; display: block;
            transition: all 0.18s;
        }
        .tab:hover { color: #555; }
        .tab.active {
            background: #0f172a;
            color: #fff;
            box-shadow: 0 2px 8px rgba(15,23,42,0.25);
        }

        /* Button */
        .btn-login {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, #16213e 0%, #0f172a 100%);
            color: #fff; border: none; border-radius: 10px;
            font-size: 13.5px; font-weight: 600; cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            transition: all 0.2s;
            box-shadow: 0 2px 10px rgba(15,23,42,0.25);
            letter-spacing: 0.01em;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #1e3a5f 0%, #16213e 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 18px rgba(15,23,42,0.32);
        }

        /* Gold accent line */
        .accent-bar {
            width: 36px; height: 2px;
            background: linear-gradient(90deg, #c9a96e, rgba(201,169,110,0.2));
            border-radius: 1px; margin: 10px 0 20px;
        }

        /* Stat chips */
        .stat-chip {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 12px; padding: 14px;
            text-align: center;
        }

        @keyframes rise { from { opacity:0; transform: translateY(14px); } to { opacity:1; transform: translateY(0); } }
        .rise { animation: rise 0.5s ease both; }
        .rise-2 { animation-delay: 0.08s; }
        .rise-3 { animation-delay: 0.16s; }
    </style>
</head>

<body class="min-h-screen flex" style="min-height:100vh">

    <!-- LEFT -->
    <div class="panel-left hidden lg:flex flex-col justify-center items-start flex-1 relative px-14 py-16">
        <!-- Logo -->
        <div class="flex items-center gap-3 mb-12 rise">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                 style="background: linear-gradient(135deg, #c9a96e, #b8935a); box-shadow: 0 4px 14px rgba(201,169,110,0.4);">
                <i class="fas fa-book-open text-white text-base"></i>
            </div>
            <div>
                <p class="text-white font-semibold text-sm leading-none"><?= APP_NAME ?></p>
                <p class="text-xs mt-0.5" style="color:rgba(255,255,255,0.3)"><?= APP_SCHOOL ?></p>
            </div>
        </div>

        <!-- Headline -->
        <div class="rise rise-2">
            <h1 style="font-family:'Cormorant Garamond',serif; font-size:52px; line-height:1.1; font-weight:600; color:#fff; margin:0">
                Perpustakaan<br>
                <span style="color:#c9a96e">Digital</span>
            </h1>
            <div class="accent-bar"></div>
            <p style="color:rgba(255,255,255,0.38); font-size:14px; line-height:1.7; max-width:300px">
                Sistem manajemen perpustakaan sekolah yang modern, cerdas, dan mudah digunakan oleh seluruh civitas akademika.
            </p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-3 mt-12 w-full max-w-xs rise rise-3">
            <?php foreach ([
                ['fa-book',       'Koleksi Buku'],
                ['fa-users',      'Anggota Aktif'],
                ['fa-right-left', 'Transaksi'],
            ] as $s): ?>
                <div class="stat-chip">
                    <i class="fas <?= $s[0] ?> text-sm mb-2 block" style="color:rgba(201,169,110,0.7)"></i>
                    <p style="color:rgba(255,255,255,0.3); font-size:10px; font-weight:500"><?= $s[1] ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="absolute bottom-8 left-14 text-xs" style="color:rgba(255,255,255,0.15)">
            &copy; <?= date('Y') ?> <?= APP_NAME ?>
        </p>
    </div>

    <!-- RIGHT -->
    <div class="panel-right flex items-center justify-center w-full lg:w-[460px] p-6 lg:p-10">
        <div class="w-full max-w-sm">

            <!-- Mobile header -->
            <div class="lg:hidden text-center mb-8">
                <div class="w-12 h-12 rounded-xl mx-auto mb-3 flex items-center justify-center"
                     style="background: linear-gradient(135deg, #c9a96e, #b8935a); box-shadow: 0 4px 14px rgba(201,169,110,0.35);">
                    <i class="fas fa-book-open text-white"></i>
                </div>
                <h2 style="font-family:'Cormorant Garamond',serif; font-size:26px; font-weight:600; color:#0f172a; margin:0">
                    Perpustakaan Digital
                </h2>
            </div>

            <div class="form-card rounded-2xl p-8">
                <div class="mb-6">
                    <h2 style="font-family:'Cormorant Garamond',serif; font-size:26px; font-weight:600; color:#0f172a; margin:0; line-height:1.2">
                        <?= $mode === 'login' ? 'Selamat Datang' : 'Buat Akun Baru' ?>
                    </h2>
                    <div class="accent-bar" style="margin-top:8px; margin-bottom:0"></div>
                </div>

                <!-- Tabs -->
                <div class="tab-wrap flex mb-6">
                    <a href="?mode=login" class="tab <?= $mode === 'login' ? 'active' : '' ?>">Masuk</a>
                    <a href="?mode=register" class="tab <?= $mode === 'register' ? 'active' : '' ?>">Daftar</a>
                </div>

                <?php if ($error): ?>
                    <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:10px; padding:10px 14px; font-size:13px; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-circle-exclamation" style="color:#ef4444; font-size:13px"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; border-radius:10px; padding:10px 14px; font-size:13px; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-circle-check" style="color:#22c55e; font-size:13px"></i> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($mode === 'login'): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        <div style="margin-bottom:14px">
                            <label style="display:block; font-size:12.5px; font-weight:500; color:#555; margin-bottom:5px">Alamat Email</label>
                            <div style="position:relative">
                                <i class="fas fa-envelope inp-icon"></i>
                                <input type="email" name="email" placeholder="nama@email.com" required class="inp" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                        </div>
                        <div style="margin-bottom:20px">
                            <label style="display:block; font-size:12.5px; font-weight:500; color:#555; margin-bottom:5px">Kata Sandi</label>
                            <div style="position:relative">
                                <i class="fas fa-lock inp-icon"></i>
                                <input type="password" name="password" placeholder="••••••••" required class="inp">
                            </div>
                        </div>
                        <button type="submit" class="btn-login">
                            <i class="fas fa-right-to-bracket mr-2" style="opacity:.75"></i> Masuk ke Sistem
                        </button>
                    </form>

                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="register">
                        <?php foreach ([
                            ['nama',     'Nama Lengkap', 'text',     'fa-user',           'Nama lengkap',   true],
                            ['email',    'Email',        'email',    'fa-envelope',        'nama@email.com', true],
                            ['password', 'Kata Sandi',   'password', 'fa-lock',            'Min. 6 karakter',true],
                            ['kelas',    'Kelas',        'text',     'fa-graduation-cap',  'XII RPL 1',      false],
                        ] as [$n,$l,$t,$ic,$ph,$req]): ?>
                            <div style="margin-bottom:14px">
                                <label style="display:block; font-size:12.5px; font-weight:500; color:#555; margin-bottom:5px"><?= $l ?></label>
                                <div style="position:relative">
                                    <i class="fas <?= $ic ?> inp-icon"></i>
                                    <input type="<?= $t ?>" name="<?= $n ?>" placeholder="<?= $ph ?>" <?= $req ? 'required' : '' ?> class="inp">
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" class="btn-login" style="margin-top:6px">
                            <i class="fas fa-user-plus mr-2" style="opacity:.75"></i> Daftar Sekarang
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <p style="text-align:center; color:#ccc; font-size:11px; margin-top:18px">
                &copy; <?= date('Y') ?> <?= APP_NAME ?> — <?= APP_SCHOOL ?>
            </p>
        </div>
    </div>
</body>
</html>
