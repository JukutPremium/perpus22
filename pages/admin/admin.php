<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireAdmin();

$pageTitle = 'Kelola Admin';

// ── CREATE ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $nama   = trim($_POST['nama']);
    $email  = trim($_POST['email']);
    $password = $_POST['password'];
    $telepon = trim($_POST['telepon'] ?? '');

    if (!$nama || !$email || !$password) {
        setFlash('error', 'Data tidak lengkap.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('error', 'Email tidak valid.');
    } elseif (strlen($password) < 6) {
        setFlash('error', 'Password minimal 6 karakter.');
    } else {
        $cek = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $cek->execute([$email]);
        if ($cek->fetch()) {
            setFlash('error', 'Email sudah terdaftar.');
        } else {
            $cnt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() + 1;
            $no  = 'ADM-' . str_pad($cnt, 3, '0', STR_PAD_LEFT);
            // Pastikan no_anggota unik
            while (true) {
                $cekNo = $pdo->prepare("SELECT id FROM users WHERE no_anggota=?");
                $cekNo->execute([$no]);
                if (!$cekNo->fetch()) break;
                $cnt++;
                $no = 'ADM-' . str_pad($cnt, 3, '0', STR_PAD_LEFT);
            }
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (nama,email,password,role,no_anggota,telepon,status) VALUES (?,?,?,'admin',?,?,'aktif')")
                ->execute([$nama, $email, $hash, $no, $telepon]);
            setFlash('success', 'Admin baru berhasil ditambahkan.');
        }
    }
    header('Location: admin.php');
    exit;
}

// ── UPDATE ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id      = (int) $_POST['id'];
    $nama    = trim($_POST['nama']);
    $telepon = trim($_POST['telepon'] ?? '');
    $status  = $_POST['status'];
    $password = $_POST['password'] ?? '';

    // Jangan ubah status diri sendiri
    if ($id === (int)($_SESSION['user_id'] ?? 0) && $status === 'nonaktif') {
        setFlash('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        header('Location: admin.php');
        exit;
    }

    $sql    = "UPDATE users SET nama=?,telepon=?,status=?";
    $params = [$nama, $telepon, $status];
    if (!empty($password)) {
        if (strlen($password) < 6) {
            setFlash('error', 'Password minimal 6 karakter.');
            header('Location: admin.php?edit=' . $id);
            exit;
        }
        $sql .= ", password=?";
        $params[] = password_hash($password, PASSWORD_BCRYPT);
    }
    $sql .= " WHERE id=? AND role='admin'";
    $params[] = $id;
    $pdo->prepare($sql)->execute($params);
    setFlash('success', 'Data admin berhasil diperbarui.');
    header('Location: admin.php');
    exit;
}

// ── DELETE ─────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    // Tidak bisa hapus diri sendiri
    if ($id === (int)($_SESSION['user_id'] ?? 0)) {
        setFlash('error', 'Anda tidak dapat menghapus akun sendiri.');
    } else {
        // Pastikan minimal ada 1 admin aktif setelah dihapus
        $totalAdmin = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='aktif'")->fetchColumn();
        $targetStatus = $pdo->prepare("SELECT status FROM users WHERE id=? AND role='admin'");
        $targetStatus->execute([$id]);
        $target = $targetStatus->fetch();

        if (!$target) {
            setFlash('error', 'Admin tidak ditemukan.');
        } elseif ($totalAdmin <= 1 && $target['status'] === 'aktif') {
            setFlash('error', 'Tidak dapat menghapus admin terakhir yang aktif.');
        } else {
            $pdo->prepare("DELETE FROM users WHERE id=? AND role='admin'")->execute([$id]);
            setFlash('success', 'Admin berhasil dihapus.');
        }
    }
    header('Location: admin.php');
    exit;
}

// ── TOGGLE STATUS ──────────────────────────────────────────
if (isset($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];
    if ($id === (int)($_SESSION['user_id'] ?? 0)) {
        setFlash('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
    } else {
        $cur = $pdo->prepare("SELECT status FROM users WHERE id=? AND role='admin'");
        $cur->execute([$id]);
        $row = $cur->fetch();
        if ($row && $row['status'] === 'aktif') {
            $totalAktif = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status='aktif'")->fetchColumn();
            if ($totalAktif <= 1) {
                setFlash('error', 'Tidak dapat menonaktifkan admin terakhir yang aktif.');
                header('Location: admin.php');
                exit;
            }
        }
        $pdo->prepare("UPDATE users SET status = IF(status='aktif','nonaktif','aktif') WHERE id=? AND role='admin'")->execute([$id]);
        setFlash('success', 'Status admin diperbarui.');
    }
    header('Location: admin.php');
    exit;
}

// ── READ ───────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$params = [];
$where  = "role='admin'";
if ($search) {
    $where .= " AND (nama LIKE ? OR email LIKE ? OR no_anggota LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$stmt = $pdo->prepare("SELECT * FROM users WHERE $where ORDER BY created_at ASC");
$stmt->execute($params);
$adminList = $stmt->fetchAll();

$editAdmin = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='admin'");
    $s->execute([(int) $_GET['edit']]);
    $editAdmin = $s->fetch();
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);

require_once '../../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Kelola Admin</h1>
        <p class="text-slate-400 text-sm mt-1"><?= count($adminList) ?> admin terdaftar</p>
    </div>
    <button onclick="document.getElementById('modalTambah').classList.remove('hidden')"
        class="btn-primary px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2">
        <i class="fas fa-user-shield"></i> Tambah Admin
    </button>
</div>

<!-- Flash Message -->
<?php $flash = getFlash(); if ($flash): ?>
<div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium
    <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
    <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Search -->
<div class="card p-4 mb-4">
    <form method="GET" class="flex gap-3">
        <div class="relative flex-1">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i class="fas fa-search text-sm"></i></span>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                placeholder="Cari nama, email, no. admin..."
                class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-200 outline-none">
        </div>
        <button type="submit" class="btn-primary px-4 py-2.5 rounded-xl text-sm font-semibold">Cari</button>
        <?php if ($search): ?>
            <a href="admin.php" class="px-4 py-2.5 rounded-xl text-sm border border-gray-200 text-slate-500 hover:bg-slate-50">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr class="text-left text-slate-400">
                    <th class="px-4 py-3.5 font-semibold">No. Admin</th>
                    <th class="px-4 py-3.5 font-semibold">Nama / Email</th>
                    <th class="px-4 py-3.5 font-semibold">Telepon</th>
                    <th class="px-4 py-3.5 font-semibold">Dibuat</th>
                    <th class="px-4 py-3.5 font-semibold text-center">Status</th>
                    <th class="px-4 py-3.5 font-semibold text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($adminList as $a): ?>
                    <tr class="table-row">
                        <td class="px-4 py-3">
                            <span class="bg-amber-50 text-amber-700 px-2 py-0.5 rounded text-xs font-mono font-semibold">
                                <?= htmlspecialchars($a['no_anggota'] ?? '-') ?>
                            </span>
                            <?php if ($a['id'] === $currentUserId): ?>
                                <span class="ml-1 bg-blue-50 text-blue-600 text-xs px-1.5 py-0.5 rounded">Anda</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                    <?= strtoupper(substr($a['nama'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="font-medium text-slate-900"><?= htmlspecialchars($a['nama']) ?></p>
                                    <p class="text-slate-400 text-xs"><?= htmlspecialchars($a['email']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($a['telepon'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-slate-500 text-xs"><?= formatTanggal(substr($a['created_at'], 0, 10)) ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($a['id'] === $currentUserId): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">
                                    <i class="fas fa-circle text-[6px]"></i> <?= ucfirst($a['status']) ?>
                                </span>
                            <?php else: ?>
                                <a href="?toggle=<?= $a['id'] ?>"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold
                                    <?= $a['status'] === 'aktif' ? 'bg-slate-100 text-slate-600 hover:bg-slate-200' : 'bg-slate-200 text-slate-800 hover:bg-slate-300' ?>">
                                    <i class="fas fa-circle text-[6px]"></i> <?= ucfirst($a['status']) ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="?edit=<?= $a['id'] ?>"
                                    class="w-8 h-8 bg-primary-50 hover:bg-slate-100 text-black rounded-lg flex items-center justify-center transition-colors"
                                    title="Edit">
                                    <i class="fas fa-pen text-xs"></i>
                                </a>
                                <?php if ($a['id'] !== $currentUserId): ?>
                                    <a href="?delete=<?= $a['id'] ?>"
                                        onclick="return confirmDelete('Hapus admin <?= htmlspecialchars(addslashes($a['nama'])) ?>?')"
                                        class="w-8 h-8 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg flex items-center justify-center transition-colors"
                                        title="Hapus">
                                        <i class="fas fa-trash text-xs"></i>
                                    </a>
                                <?php else: ?>
                                    <div class="w-8 h-8 bg-slate-50 text-gray-300 rounded-lg flex items-center justify-center cursor-not-allowed"
                                        title="Tidak bisa menghapus akun sendiri">
                                        <i class="fas fa-lock text-xs"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($adminList)): ?>
                    <tr>
                        <td colspan="6" class="py-12 text-center text-slate-400">Tidak ada data admin</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH -->
<div id="modalTambah" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md">
        <div class="flex items-center justify-between p-6 border-b">
            <h3 class="font-bold text-slate-900 text-lg">Tambah Admin Baru</h3>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')"
                class="text-slate-400 w-8 h-8 flex items-center justify-center hover:text-slate-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="create">
            <div>
                <label class="text-sm font-medium text-slate-600 block mb-1">Nama Lengkap *</label>
                <input type="text" name="nama" required
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-600 block mb-1">Email *</label>
                <input type="email" name="email" required
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-600 block mb-1">Password * <span class="text-slate-400 font-normal">(min. 6 karakter)</span></label>
                <div class="relative">
                    <input type="password" name="password" id="passCreate" required minlength="6"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200 pr-10">
                    <button type="button" onclick="togglePass('passCreate', this)"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                        <i class="fas fa-eye text-sm"></i>
                    </button>
                </div>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-600 block mb-1">Telepon</label>
                <input type="text" name="telepon" placeholder="08xx-xxxx-xxxx"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')"
                    class="flex-1 border border-gray-200 text-slate-500 py-2.5 rounded-xl text-sm hover:bg-slate-50">Batal</button>
                <button type="submit" class="flex-1 btn-primary py-2.5 rounded-xl text-sm font-semibold">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<?php if ($editAdmin): ?>
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-md">
            <div class="flex items-center justify-between p-6 border-b">
                <h3 class="font-bold text-slate-900 text-lg">Edit Admin</h3>
                <a href="admin.php" class="text-slate-400 w-8 h-8 flex items-center justify-center hover:text-slate-600">
                    <i class="fas fa-times"></i>
                </a>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= $editAdmin['id'] ?>">
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Nama Lengkap *</label>
                    <input type="text" name="nama" required value="<?= htmlspecialchars($editAdmin['nama']) ?>"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Email</label>
                    <input type="email" value="<?= htmlspecialchars($editAdmin['email']) ?>" disabled
                        class="w-full border border-gray-100 bg-slate-50 rounded-xl px-3 py-2.5 text-sm text-slate-400 cursor-not-allowed">
                    <p class="text-xs text-slate-400 mt-1">Email tidak dapat diubah</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Telepon</label>
                    <input type="text" name="telepon" value="<?= htmlspecialchars($editAdmin['telepon'] ?? '') ?>"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
                <?php if ($editAdmin['id'] !== $currentUserId): ?>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Status</label>
                    <select name="status"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                        <option value="aktif" <?= $editAdmin['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= $editAdmin['status'] === 'nonaktif' ? 'selected' : '' ?>>Non-Aktif</option>
                    </select>
                </div>
                <?php else: ?>
                    <input type="hidden" name="status" value="aktif">
                <?php endif; ?>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">
                        Password Baru <span class="text-slate-400 font-normal">(kosongkan jika tidak diubah)</span>
                    </label>
                    <div class="relative">
                        <input type="password" name="password" id="passEdit" minlength="6"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200 pr-10">
                        <button type="button" onclick="togglePass('passEdit', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                            <i class="fas fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>
                <div class="flex gap-3 pt-2">
                    <a href="admin.php"
                        class="flex-1 text-center border border-gray-200 text-slate-500 py-2.5 rounded-xl text-sm hover:bg-slate-50">Batal</a>
                    <button type="submit" class="flex-1 btn-primary py-2.5 rounded-xl text-sm font-semibold">Update</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
