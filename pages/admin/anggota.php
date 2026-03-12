<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireAdmin();

$pageTitle = 'Kelola Anggota';

// ── CREATE ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $kelas = trim($_POST['kelas'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');

    if (!$nama || !$email || !$password) {
        setFlash('error', 'Data tidak lengkap.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('error', 'Email tidak valid.');
    } else {
        $cek = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $cek->execute([$email]);
        if ($cek->fetch()) {
            setFlash('error', 'Email sudah terdaftar.');
        } else {
            $cnt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='siswa'")->fetchColumn() + 1;
            $no = 'SIS-' . str_pad($cnt, 3, '0', STR_PAD_LEFT);
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (nama,email,password,role,no_anggota,kelas,telepon) VALUES (?,?,?,'siswa',?,?,?)")
                ->execute([$nama, $email, $hash, $no, $kelas, $telepon]);
            setFlash('success', 'Anggota berhasil ditambahkan.');
        }
    }
    header('Location: anggota.php');
    exit;
}

// ── UPDATE ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id = (int) $_POST['id'];
    $nama = trim($_POST['nama']);
    $kelas = trim($_POST['kelas'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $status = $_POST['status'];
    $password = $_POST['password'] ?? '';

    $sql = "UPDATE users SET nama=?,kelas=?,telepon=?,status=?";
    $params = [$nama, $kelas, $telepon, $status];
    if (!empty($password)) {
        $sql .= ", password=?";
        $params[] = password_hash($password, PASSWORD_BCRYPT);
    }
    $sql .= " WHERE id=?";
    $params[] = $id;
    $pdo->prepare($sql)->execute($params);
    setFlash('success', 'Data anggota berhasil diperbarui.');
    header('Location: anggota.php');
    exit;
}

// ── DELETE ─────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if (!canDeleteUser($pdo, $id)) {
        setFlash('error', 'Anggota tidak dapat dihapus karena masih memiliki peminjaman aktif atau denda yang belum dibayar.');
    } else {
        $pdo->prepare("DELETE FROM users WHERE id=? AND role='siswa'")->execute([$id]);
        setFlash('success', 'Anggota berhasil dihapus.');
    }
    header('Location: anggota.php');
    exit;
}

// ── TOGGLE STATUS ──────────────────────────────────────────
if (isset($_GET['toggle'])) {
    $id = (int) $_GET['toggle'];
    $pdo->prepare("UPDATE users SET status = IF(status='aktif','nonaktif','aktif') WHERE id=?")->execute([$id]);
    setFlash('success', 'Status anggota diperbarui.');
    header('Location: anggota.php');
    exit;
}

// ── READ ───────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$params = [];
$where = "role='siswa'";
if ($search) {
    $where .= " AND (nama LIKE ? OR email LIKE ? OR no_anggota LIKE ? OR kelas LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%", "%$search%"];
}
$stmt = $pdo->prepare("SELECT u.*, 
    (SELECT COUNT(*) FROM peminjaman WHERE user_id=u.id AND status='dipinjam') AS jml_pinjam,
    (SELECT COALESCE(SUM(denda),0) FROM peminjaman WHERE user_id=u.id AND denda>0 AND denda_terbayar=0) AS total_denda
    FROM users u WHERE $where ORDER BY u.created_at DESC");
$stmt->execute($params);
$anggotaList = $stmt->fetchAll();

$editAnggota = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='siswa'");
    $s->execute([(int) $_GET['edit']]);
    $editAnggota = $s->fetch();
}

require_once '../../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900  ">Kelola Anggota</h1>
        <p class="text-slate-400 text-sm mt-1"><?= count($anggotaList) ?> anggota terdaftar</p>
    </div>
    <button onclick="document.getElementById('modalTambah').classList.remove('hidden')"
        class="btn-primary px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2">
        <i class="fas fa-user-plus"></i> Tambah Anggota
    </button>
</div>

<!-- Search -->
<div class="card p-4 mb-4">
    <form method="GET" class="flex gap-3">
        <div class="relative flex-1">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i
                    class="fas fa-search text-sm"></i></span>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                placeholder="Cari nama, email, no. anggota, kelas..."
                class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-200 outline-none">
        </div>
        <button type="submit" class="btn-primary px-4 py-2.5 rounded-xl text-sm font-semibold">Cari</button>
        <?php if ($search): ?><a href="anggota.php"
                class="px-4 py-2.5 rounded-xl text-sm border border-gray-200 text-slate-500 hover:bg-slate-50">Reset</a><?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr class="text-left text-slate-400">
                    <th class="px-4 py-3.5 font-semibold">No. Anggota</th>
                    <th class="px-4 py-3.5 font-semibold">Nama / Email</th>
                    <th class="px-4 py-3.5 font-semibold">Kelas</th>
                    <th class="px-4 py-3.5 font-semibold text-center">Pinjam Aktif</th>
                    <th class="px-4 py-3.5 font-semibold">Denda</th>
                    <th class="px-4 py-3.5 font-semibold text-center">Status</th>
                    <th class="px-4 py-3.5 font-semibold text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($anggotaList as $a):
                    $canDelete = ($a['jml_pinjam'] == 0 && $a['total_denda'] == 0);
                    ?>
                    <tr class="table-row">
                        <td class="px-4 py-3">
                            <span
                                class="bg-slate-100 text-slate-400 px-2 py-0.5 rounded text-xs font-mono font-semibold"><?= htmlspecialchars($a['no_anggota'] ?? '-') ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <div
                                    class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                    <?= strtoupper(substr($a['nama'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="font-medium text-slate-900"><?= htmlspecialchars($a['nama']) ?></p>
                                    <p class="text-slate-400 text-xs"><?= htmlspecialchars($a['email']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-500"><?= htmlspecialchars($a['kelas'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($a['jml_pinjam'] > 0): ?>
                                <span
                                    class="bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full text-xs font-semibold"><?= $a['jml_pinjam'] ?>
                                    buku</span>
                            <?php else: ?>
                                <span class="text-gray-300">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($a['total_denda'] > 0): ?>
                                <span class="text-slate-800 font-semibold text-xs"><?= formatRupiah($a['total_denda']) ?></span>
                            <?php else: ?>
                                <span class="text-gray-300 text-xs">Lunas</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="?toggle=<?= $a['id'] ?>"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold
                            <?= $a['status'] === 'aktif' ? 'bg-slate-100 text-slate-600' : 'bg-slate-200 text-slate-800' ?>">
                                <i class="fas fa-circle text-[6px]"></i> <?= ucfirst($a['status']) ?>
                            </a>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="?edit=<?= $a['id'] ?>"
                                    class="w-8 h-8 bg-primary-50 hover:bg-slate-100 text-black rounded-lg flex items-center justify-center transition-colors">
                                    <i class="fas fa-pen text-xs"></i>
                                </a>
                                <?php if ($canDelete): ?>
                                    <a href="?delete=<?= $a['id'] ?>"
                                        onclick="return confirmDelete('Hapus anggota <?= htmlspecialchars(addslashes($a['nama'])) ?>?')"
                                        class="w-8 h-8 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg flex items-center justify-center transition-colors">
                                        <i class="fas fa-trash text-xs"></i>
                                    </a>
                                <?php else: ?>
                                    <div class="w-8 h-8 bg-slate-50 text-gray-300 rounded-lg flex items-center justify-center cursor-not-allowed"
                                        title="Tidak bisa dihapus: ada pinjaman aktif atau denda belum lunas">
                                        <i class="fas fa-lock text-xs"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($anggotaList)): ?>
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400">Tidak ada data anggota</td>
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
            <h3 class="font-bold text-slate-900 text-lg">Tambah Anggota</h3>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')"
                class="text-slate-400 w-8 h-8 flex items-center justify-center"><i class="fas fa-times"></i></button>
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
                <label class="text-sm font-medium text-slate-600 block mb-1">Password *</label>
                <input type="password" name="password" required
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Kelas</label>
                    <input type="text" name="kelas" placeholder="XII RPL 1"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Telepon</label>
                    <input type="text" name="telepon"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')"
                    class="flex-1 border border-gray-200 text-slate-500 py-2.5 rounded-xl text-sm">Batal</button>
                <button type="submit" class="flex-1 btn-primary py-2.5 rounded-xl text-sm font-semibold">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<?php if ($editAnggota): ?>
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-md">
            <div class="flex items-center justify-between p-6 border-b">
                <h3 class="font-bold text-slate-900 text-lg">Edit Anggota</h3>
                <a href="anggota.php" class="text-slate-400 w-8 h-8 flex items-center justify-center"><i
                        class="fas fa-times"></i></a>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= $editAnggota['id'] ?>">
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Nama Lengkap *</label>
                    <input type="text" name="nama" required value="<?= htmlspecialchars($editAnggota['nama']) ?>"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium text-slate-600 block mb-1">Kelas</label>
                        <input type="text" name="kelas" value="<?= htmlspecialchars($editAnggota['kelas'] ?? '') ?>"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600 block mb-1">Telepon</label>
                        <input type="text" name="telepon" value="<?= htmlspecialchars($editAnggota['telepon'] ?? '') ?>"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Status</label>
                    <select name="status"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                        <option value="aktif" <?= $editAnggota['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= $editAnggota['status'] === 'nonaktif' ? 'selected' : '' ?>>Non-Aktif</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Password Baru <span
                            class="text-slate-400">(kosongkan jika tidak diubah)</span></label>
                    <input type="password" name="password"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
                <div class="flex gap-3 pt-2">
                    <a href="anggota.php"
                        class="flex-1 text-center border border-gray-200 text-slate-500 py-2.5 rounded-xl text-sm">Batal</a>
                    <button type="submit" class="flex-1 btn-primary py-2.5 rounded-xl text-sm font-semibold">Update</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>