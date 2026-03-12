<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireAdmin();

$pageTitle = 'Data Buku';

// ── CREATE ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $kode = trim($_POST['kode_buku']);
    $judul = trim($_POST['judul']);
    $pengarang = trim($_POST['pengarang']);
    $penerbit = trim($_POST['penerbit'] ?? '');
    $tahun = $_POST['tahun_terbit'] ?? null;
    $isbn = trim($_POST['isbn'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $stok = (int) $_POST['stok'];
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $rak = trim($_POST['lokasi_rak'] ?? '');

    try {
        $stmt = $pdo->prepare("INSERT INTO buku (kode_buku,judul,pengarang,penerbit,tahun_terbit,isbn,kategori,stok,stok_tersedia,deskripsi,lokasi_rak) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$kode, $judul, $pengarang, $penerbit, $tahun, $isbn, $kategori, $stok, $stok, $deskripsi, $rak]);
        setFlash('success', 'Buku berhasil ditambahkan.');
    } catch (PDOException $e) {
        setFlash('error', 'Gagal: kode buku mungkin sudah ada.');
    }
    header('Location: buku.php');
    exit;
}

// ── UPDATE ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id = (int) $_POST['id'];
    $judul = trim($_POST['judul']);
    $pengarang = trim($_POST['pengarang']);
    $penerbit = trim($_POST['penerbit'] ?? '');
    $tahun = $_POST['tahun_terbit'] ?? null;
    $isbn = trim($_POST['isbn'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $stok = (int) $_POST['stok'];
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $rak = trim($_POST['lokasi_rak'] ?? '');

    // Hitung selisih stok untuk update stok_tersedia
    $oldBuku = $pdo->prepare("SELECT stok, stok_tersedia FROM buku WHERE id=?");
    $oldBuku->execute([$id]);
    $old = $oldBuku->fetch();
    $selisih = $stok - $old['stok'];
    $newTersedia = max(0, $old['stok_tersedia'] + $selisih);

    $stmt = $pdo->prepare("UPDATE buku SET judul=?,pengarang=?,penerbit=?,tahun_terbit=?,isbn=?,kategori=?,stok=?,stok_tersedia=?,deskripsi=?,lokasi_rak=? WHERE id=?");
    $stmt->execute([$judul, $pengarang, $penerbit, $tahun, $isbn, $kategori, $stok, $newTersedia, $deskripsi, $rak, $id]);
    setFlash('success', 'Data buku berhasil diperbarui.');
    header('Location: buku.php');
    exit;
}

// ── DELETE ─────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    // Cek ada peminjaman aktif
    $cek = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE buku_id=? AND status='dipinjam'");
    $cek->execute([$id]);
    if ($cek->fetchColumn() > 0) {
        setFlash('error', 'Buku tidak dapat dihapus karena sedang dipinjam.');
    } else {
        $pdo->prepare("DELETE FROM buku WHERE id=?")->execute([$id]);
        setFlash('success', 'Buku berhasil dihapus.');
    }
    header('Location: buku.php');
    exit;
}

// ── READ ───────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$kategoriFilter = $_GET['kategori'] ?? '';
$where = '1=1';
$params = [];
if ($search) {
    $where .= " AND (judul LIKE ? OR pengarang LIKE ? OR kode_buku LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($kategoriFilter) {
    $where .= " AND kategori = ?";
    $params[] = $kategoriFilter;
}

$buku = $pdo->prepare("SELECT * FROM buku WHERE $where ORDER BY judul");
$buku->execute($params);
$bukuList = $buku->fetchAll();

$kategoriList = $pdo->query("SELECT DISTINCT kategori FROM buku WHERE kategori != '' ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);

// Edit mode
$editBuku = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM buku WHERE id=?");
    $stmt->execute([(int) $_GET['edit']]);
    $editBuku = $stmt->fetch();
}

require_once '../../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900  ">Data Buku</h1>
        <p class="text-slate-400 text-sm mt-1"><?= count($bukuList) ?> judul buku ditemukan</p>
    </div>
    <button onclick="document.getElementById('modalTambah').classList.remove('hidden')"
        class="btn-primary px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2">
        <i class="fas fa-plus"></i> Tambah Buku
    </button>
</div>

<!-- Search & Filter -->
<div class="card p-4 mb-4 flex flex-col sm:flex-row gap-3">
    <form method="GET" class="flex gap-3 flex-1">
        <div class="relative flex-1">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i
                    class="fas fa-search text-sm"></i></span>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                placeholder="Cari judul, pengarang, kode..."
                class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 outline-none">
        </div>
        <select name="kategori"
            class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
            <option value="">Semua Kategori</option>
            <?php foreach ($kategoriList as $k): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= $k == $kategoriFilter ? 'selected' : '' ?>>
                    <?= htmlspecialchars($k) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary px-4 py-2.5 rounded-xl text-sm font-semibold">Cari</button>
        <?php if ($search || $kategoriFilter): ?>
            <a href="buku.php"
                class="px-4 py-2.5 rounded-xl text-sm border border-gray-200 text-slate-500 hover:bg-slate-50">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr class="text-left text-slate-400">
                    <th class="px-4 py-3.5 font-semibold">Kode</th>
                    <th class="px-4 py-3.5 font-semibold">Judul / Pengarang</th>
                    <th class="px-4 py-3.5 font-semibold">Kategori</th>
                    <th class="px-4 py-3.5 font-semibold text-center">Stok</th>
                    <th class="px-4 py-3.5 font-semibold text-center">Tersedia</th>
                    <th class="px-4 py-3.5 font-semibold">Rak</th>
                    <th class="px-4 py-3.5 font-semibold text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($bukuList as $b): ?>
                    <tr class="table-row">
                        <td class="px-4 py-3">
                            <span
                                class="bg-primary-50 text-slate-600 px-2 py-0.5 rounded text-xs font-mono font-semibold"><?= htmlspecialchars($b['kode_buku']) ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900"><?= htmlspecialchars($b['judul']) ?></p>
                            <p class="text-slate-400 text-xs"><?= htmlspecialchars($b['pengarang']) ?>
                                <?= $b['tahun_terbit'] ? '· ' . $b['tahun_terbit'] : '' ?></p>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($b['kategori']): ?>
                                <span
                                    class="bg-gray-100 text-slate-500 px-2 py-0.5 rounded-full text-xs"><?= htmlspecialchars($b['kategori']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center font-semibold text-slate-600"><?= $b['stok'] ?></td>
                        <td class="px-4 py-3 text-center">
                            <span
                                class="font-bold <?= $b['stok_tersedia'] == 0 ? 'text-slate-600' : ($b['stok_tersedia'] == 1 ? 'text-amber-500' : 'text-slate-600') ?>">
                                <?= $b['stok_tersedia'] ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-400 text-xs"><?= htmlspecialchars($b['lokasi_rak'] ?? '-') ?></td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="?edit=<?= $b['id'] ?>"
                                    class="w-8 h-8 bg-primary-50 hover:bg-slate-100 text-black rounded-lg flex items-center justify-center transition-colors"
                                    title="Edit">
                                    <i class="fas fa-pen text-xs"></i>
                                </a>
                                <a href="?delete=<?= $b['id'] ?>" onclick="return confirmDelete('Hapus buku ini?')"
                                    class="w-8 h-8 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg flex items-center justify-center transition-colors"
                                    title="Hapus">
                                    <i class="fas fa-trash text-xs"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($bukuList)): ?>
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400"><i
                                class="fas fa-book-open text-3xl mb-2 block opacity-30"></i>Tidak ada data buku</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH -->
<div id="modalTambah" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-6 border-b">
            <h3 class="font-bold text-slate-900 text-lg">Tambah Buku Baru</h3>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')"
                class="text-slate-400 hover:text-slate-500 w-8 h-8 flex items-center justify-center"><i
                    class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="create">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Kode Buku *</label>
                    <input type="text" name="kode_buku" required placeholder="BK-001"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Tahun Terbit</label>
                    <input type="number" name="tahun_terbit" min="1900" max="<?= date('Y') ?>"
                        placeholder="<?= date('Y') ?>"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-600 block mb-1">Judul Buku *</label>
                <input type="text" name="judul" required placeholder="Judul buku lengkap"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Pengarang *</label>
                    <input type="text" name="pengarang" required
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Penerbit</label>
                    <input type="text" name="penerbit"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">ISBN</label>
                    <input type="text" name="isbn"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Kategori</label>
                    <input type="text" name="kategori" list="kategoriSuggest"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                    <datalist id="kategoriSuggest">
                        <?php foreach ($kategoriList as $k): ?>
                            <option value="<?= htmlspecialchars($k) ?>"><?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Stok *</label>
                    <input type="number" name="stok" required min="0" value="1"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Lokasi Rak</label>
                    <input type="text" name="lokasi_rak" placeholder="A-01"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-600 block mb-1">Deskripsi</label>
                <textarea name="deskripsi" rows="2"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200 resize-none"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')"
                    class="flex-1 border border-gray-200 text-slate-500 py-2.5 rounded-xl text-sm hover:bg-slate-50">Batal</button>
                <button type="submit" class="flex-1 btn-primary py-2.5 rounded-xl text-sm font-semibold">Simpan
                    Buku</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<?php if ($editBuku): ?>
    <div id="modalEdit" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between p-6 border-b">
                <h3 class="font-bold text-slate-900 text-lg">Edit Buku</h3>
                <a href="buku.php" class="text-slate-400 hover:text-slate-500 w-8 h-8 flex items-center justify-center"><i
                        class="fas fa-times"></i></a>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= $editBuku['id'] ?>">
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Kode Buku</label>
                    <input type="text" value="<?= htmlspecialchars($editBuku['kode_buku']) ?>" disabled
                        class="w-full border border-slate-100 bg-slate-50 rounded-xl px-3 py-2.5 text-sm text-slate-400">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Judul Buku *</label>
                    <input type="text" name="judul" required value="<?= htmlspecialchars($editBuku['judul']) ?>"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-slate-600 block mb-1">Pengarang *</label>
                        <input type="text" name="pengarang" required value="<?= htmlspecialchars($editBuku['pengarang']) ?>"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600 block mb-1">Penerbit</label>
                        <input type="text" name="penerbit" value="<?= htmlspecialchars($editBuku['penerbit'] ?? '') ?>"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-slate-600 block mb-1">Tahun Terbit</label>
                        <input type="number" name="tahun_terbit" value="<?= $editBuku['tahun_terbit'] ?>"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600 block mb-1">ISBN</label>
                        <input type="text" name="isbn" value="<?= htmlspecialchars($editBuku['isbn'] ?? '') ?>"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-slate-600 block mb-1">Kategori</label>
                        <input type="text" name="kategori" value="<?= htmlspecialchars($editBuku['kategori'] ?? '') ?>"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600 block mb-1">Lokasi Rak</label>
                        <input type="text" name="lokasi_rak" value="<?= htmlspecialchars($editBuku['lokasi_rak'] ?? '') ?>"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Stok Total *</label>
                    <input type="number" name="stok" required min="0" value="<?= $editBuku['stok'] ?>"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                    <p class="text-xs text-slate-400 mt-1">Tersedia saat ini: <?= $editBuku['stok_tersedia'] ?></p>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Deskripsi</label>
                    <textarea name="deskripsi" rows="2"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200 resize-none"><?= htmlspecialchars($editBuku['deskripsi'] ?? '') ?></textarea>
                </div>
                <div class="flex gap-3 pt-2">
                    <a href="buku.php"
                        class="flex-1 text-center border border-gray-200 text-slate-500 py-2.5 rounded-xl text-sm hover:bg-slate-50">Batal</a>
                    <button type="submit" class="flex-1 btn-primary py-2.5 rounded-xl text-sm font-semibold">Update
                        Buku</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>