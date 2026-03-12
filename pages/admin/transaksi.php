<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireAdmin();

$pageTitle = 'Manajemen Transaksi';

// ── UPDATE DENDA OTOMATIS ──────────────────────────────────
// Update status terlambat & hitung denda untuk yang belum dikembalikan
$pdo->exec("
    UPDATE peminjaman 
    SET status='terlambat', 
        denda = DATEDIFF(CURDATE(), tanggal_kembali_rencana) * " . DENDA_PER_HARI . "
    WHERE status='dipinjam' AND tanggal_kembali_rencana < CURDATE()
");

// ── CREATE PEMINJAMAN ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pinjam') {
    $user_id = (int) $_POST['user_id'];
    $buku_id = (int) $_POST['buku_id'];
    $tgl_pinjam = $_POST['tanggal_pinjam'];
    $tgl_kembali = $_POST['tanggal_kembali'];

    // Validasi stok
    $stok = $pdo->prepare("SELECT stok_tersedia FROM buku WHERE id=?");
    $stok->execute([$buku_id]);
    $stokRow = $stok->fetch();

    // Cek buku sudah dipinjam user ini
    $cekDouble = $pdo->prepare("SELECT id FROM peminjaman WHERE user_id=? AND buku_id=? AND status IN ('dipinjam','terlambat')");
    $cekDouble->execute([$user_id, $buku_id]);

    // Cek max pinjam
    $jmlPinjam = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE user_id=? AND status IN ('dipinjam','terlambat')");
    $jmlPinjam->execute([$user_id]);

    if (!$stokRow || $stokRow['stok_tersedia'] < 1) {
        setFlash('error', 'Stok buku tidak tersedia.');
    } elseif ($cekDouble->fetch()) {
        setFlash('error', 'Anggota sudah meminjam buku ini.');
    } elseif ($jmlPinjam->fetchColumn() >= MAX_PINJAM) {
        setFlash('error', 'Anggota sudah mencapai batas maksimal peminjaman (' . MAX_PINJAM . ' buku).');
    } else {
        $cnt = $pdo->query("SELECT COUNT(*)+1 FROM peminjaman")->fetchColumn();
        $kode = 'TRX-' . date('Ymd') . '-' . str_pad($cnt, 3, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO peminjaman (kode_pinjam,user_id,buku_id,tanggal_pinjam,tanggal_kembali_rencana,created_by) VALUES (?,?,?,?,?,?)")
            ->execute([$kode, $user_id, $buku_id, $tgl_pinjam, $tgl_kembali, $_SESSION['user_id']]);
        setFlash('success', 'Peminjaman berhasil dicatat. Kode: ' . $kode);
    }
    header('Location: transaksi.php');
    exit;
}

// ── KEMBALIKAN ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'kembali') {
    $id = (int) $_POST['id'];
    $tgl_aktual = $_POST['tanggal_aktual'];
    $bayar_denda = isset($_POST['bayar_denda']) ? 1 : 0;

    $pinjam = $pdo->prepare("SELECT * FROM peminjaman WHERE id=?");
    $pinjam->execute([$id]);
    $p = $pinjam->fetch();

    // Hitung denda aktual
    $denda = 0;
    $tglKembali = new DateTime($p['tanggal_kembali_rencana']);
    $tglAktual = new DateTime($tgl_aktual);
    if ($tglAktual > $tglKembali) {
        $denda = $tglAktual->diff($tglKembali)->days * DENDA_PER_HARI;
    }

    $pdo->prepare("UPDATE peminjaman SET status='dikembalikan', tanggal_kembali_aktual=?, denda=?, denda_terbayar=? WHERE id=?")
        ->execute([$tgl_aktual, $denda, $bayar_denda, $id]);
    setFlash('success', 'Buku berhasil dikembalikan' . ($denda > 0 ? '. Denda: ' . formatRupiah($denda) : '.'));
    header('Location: transaksi.php');
    exit;
}

// ── DELETE ─────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $cek = $pdo->prepare("SELECT * FROM peminjaman WHERE id=?");
    $cek->execute([$id]);
    $p = $cek->fetch();
    if ($p && $p['status'] !== 'dipinjam' && $p['status'] !== 'terlambat') {
        $pdo->prepare("DELETE FROM peminjaman WHERE id=?")->execute([$id]);
        setFlash('success', 'Transaksi berhasil dihapus.');
    } else {
        setFlash('error', 'Transaksi aktif tidak dapat dihapus.');
    }
    header('Location: transaksi.php');
    exit;
}

// ── READ ───────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$where = '1=1';
$params = [];
if ($search) {
    $where .= " AND (u.nama LIKE ? OR b.judul LIKE ? OR p.kode_pinjam LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($statusFilter) {
    $where .= " AND p.status=?";
    $params[] = $statusFilter;
}

$transaksi = $pdo->prepare("
    SELECT p.*, u.nama AS nama_siswa, u.no_anggota, u.kelas,
           b.judul AS judul_buku, b.kode_buku
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    JOIN buku b ON p.buku_id = b.id
    WHERE $where
    ORDER BY p.created_at DESC
");
$transaksi->execute($params);
$transaksiList = $transaksi->fetchAll();

// Data untuk form
$anggotaList = $pdo->query("SELECT id,nama,no_anggota,kelas FROM users WHERE role='siswa' AND status='aktif' ORDER BY nama")->fetchAll();
$bukuList = $pdo->query("SELECT id,kode_buku,judul,stok_tersedia FROM buku WHERE stok_tersedia>0 ORDER BY judul")->fetchAll();

// Kembalikan mode
$kembaliData = null;
if (isset($_GET['kembali'])) {
    $s = $pdo->prepare("SELECT p.*,u.nama,b.judul FROM peminjaman p JOIN users u ON p.user_id=u.id JOIN buku b ON p.buku_id=b.id WHERE p.id=?");
    $s->execute([(int) $_GET['kembali']]);
    $kembaliData = $s->fetch();
}

require_once '../../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900  ">Manajemen Transaksi</h1>
        <p class="text-slate-400 text-sm mt-1"><?= count($transaksiList) ?> transaksi ditemukan</p>
    </div>
    <button onclick="document.getElementById('modalPinjam').classList.remove('hidden')"
        class="btn-primary px-4 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2">
        <i class="fas fa-plus"></i> Catat Peminjaman
    </button>
</div>

<!-- Filter -->
<div class="card p-4 mb-4">
    <form method="GET" class="flex gap-3 flex-wrap">
        <div class="relative flex-1 min-w-48">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i
                    class="fas fa-search text-sm"></i></span>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                placeholder="Cari nama, buku, kode transaksi..."
                class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-200 outline-none">
        </div>
        <select name="status"
            class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
            <option value="">Semua Status</option>
            <option value="dipinjam" <?= $statusFilter === 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
            <option value="terlambat" <?= $statusFilter === 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
            <option value="dikembalikan" <?= $statusFilter === 'dikembalikan' ? 'selected' : '' ?>>Dikembalikan</option>
        </select>
        <button type="submit" class="btn-primary px-4 py-2.5 rounded-xl text-sm font-semibold">Filter</button>
        <?php if ($search || $statusFilter): ?><a href="transaksi.php"
                class="px-4 py-2.5 rounded-xl text-sm border border-gray-200 text-slate-500 hover:bg-slate-50">Reset</a><?php endif; ?>
    </form>
</div>

<!-- Table -->
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr class="text-left text-slate-400">
                    <th class="px-4 py-3.5 font-semibold">Kode</th>
                    <th class="px-4 py-3.5 font-semibold">Anggota</th>
                    <th class="px-4 py-3.5 font-semibold">Buku</th>
                    <th class="px-4 py-3.5 font-semibold">Tgl Pinjam</th>
                    <th class="px-4 py-3.5 font-semibold">Tgl Kembali</th>
                    <th class="px-4 py-3.5 font-semibold">Denda</th>
                    <th class="px-4 py-3.5 font-semibold text-center">Status</th>
                    <th class="px-4 py-3.5 font-semibold text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($transaksiList as $t): ?>
                    <tr class="table-row">
                        <td class="px-4 py-3">
                            <span class="text-xs font-mono text-slate-400"><?= $t['kode_pinjam'] ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900"><?= htmlspecialchars($t['nama_siswa']) ?></p>
                            <p class="text-slate-400 text-xs"><?= $t['kelas'] ?></p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-slate-600 text-xs font-medium truncate max-w-[140px]">
                                <?= htmlspecialchars($t['judul_buku']) ?></p>
                            <p class="text-slate-400 text-xs"><?= $t['kode_buku'] ?></p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-xs"><?= formatTanggal($t['tanggal_pinjam']) ?></td>
                        <td
                            class="px-4 py-3 text-xs <?= ($t['status'] !== 'dikembalikan' && $t['tanggal_kembali_rencana'] < date('Y-m-d')) ? 'text-slate-800 font-semibold' : 'text-slate-500' ?>">
                            <?= formatTanggal($t['tanggal_kembali_rencana']) ?>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <?php if ($t['denda'] > 0): ?>
                                <span class="text-slate-800 font-semibold"><?= formatRupiah($t['denda']) ?></span>
                                <?php if ($t['denda_terbayar']): ?>
                                    <span class="block text-slate-500 text-[10px]">✓ Lunas</span>
                                <?php endif; ?>
                            <?php else: ?><span class="text-gray-300">—</span><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold badge-<?= $t['status'] ?>">
                                <?= ucfirst($t['status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <?php if ($t['status'] !== 'dikembalikan'): ?>
                                    <a href="?kembali=<?= $t['id'] ?>"
                                        class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-100 text-slate-600 rounded-lg text-xs font-medium transition-colors">
                                        Kembalikan
                                    </a>
                                <?php else: ?>
                                    <a href="?delete=<?= $t['id'] ?>"
                                        onclick="return confirmDelete('Hapus data transaksi ini?')"
                                        class="w-7 h-7 bg-slate-100 hover:bg-slate-200 text-red-400 rounded-lg flex items-center justify-center transition-colors">
                                        <i class="fas fa-trash text-xs"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($transaksiList)): ?>
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-400">Tidak ada data transaksi</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL PEMINJAMAN BARU -->
<div id="modalPinjam" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-md">
        <div class="flex items-center justify-between p-6 border-b">
            <h3 class="font-bold text-slate-900 text-lg">Catat Peminjaman</h3>
            <button onclick="document.getElementById('modalPinjam').classList.add('hidden')"
                class="text-slate-400 w-8 h-8 flex items-center justify-center"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="pinjam">
            <div>
                <label class="text-sm font-medium text-slate-600 block mb-1">Anggota *</label>
                <select name="user_id" required
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                    <option value="">Pilih anggota...</option>
                    <?php foreach ($anggotaList as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nama']) ?> — <?= $a['no_anggota'] ?>
                            (<?= $a['kelas'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-600 block mb-1">Buku *</label>
                <select name="buku_id" required
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                    <option value="">Pilih buku...</option>
                    <?php foreach ($bukuList as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['judul']) ?> [Sisa:
                            <?= $b['stok_tersedia'] ?>]</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Tgl Pinjam *</label>
                    <input type="date" name="tanggal_pinjam" required value="<?= date('Y-m-d') ?>"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Tgl Kembali *</label>
                    <input type="date" name="tanggal_kembali" required
                        value="<?= date('Y-m-d', strtotime('+' . MASA_PINJAM . ' days')) ?>"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
            </div>
            <p class="text-xs text-slate-400"><i class="fas fa-info-circle"></i> Masa pinjam default: <?= MASA_PINJAM ?>
                hari. Maks: <?= MAX_PINJAM ?> buku/anggota.</p>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalPinjam').classList.add('hidden')"
                    class="flex-1 border border-gray-200 text-slate-500 py-2.5 rounded-xl text-sm">Batal</button>
                <button type="submit" class="flex-1 btn-primary py-2.5 rounded-xl text-sm font-semibold">Catat
                    Pinjam</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL PENGEMBALIAN -->
<?php if ($kembaliData): ?>
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-md">
            <div class="flex items-center justify-between p-6 border-b">
                <h3 class="font-bold text-slate-900 text-lg">Proses Pengembalian</h3>
                <a href="transaksi.php" class="text-slate-400 w-8 h-8 flex items-center justify-center"><i
                        class="fas fa-times"></i></a>
            </div>
            <div class="p-6">
                <div class="bg-slate-50 rounded-xl p-4 mb-4 space-y-1.5 text-sm">
                    <p><span class="text-slate-400">Anggota:</span> <span
                            class="font-semibold text-slate-900"><?= htmlspecialchars($kembaliData['nama']) ?></span></p>
                    <p><span class="text-slate-400">Buku:</span> <span
                            class="font-semibold text-slate-900"><?= htmlspecialchars($kembaliData['judul']) ?></span></p>
                    <p><span class="text-slate-400">Tgl Pinjam:</span> <?= formatTanggal($kembaliData['tanggal_pinjam']) ?>
                    </p>
                    <p><span class="text-slate-400">Jatuh Tempo:</span>
                        <span
                            class="<?= $kembaliData['tanggal_kembali_rencana'] < date('Y-m-d') ? 'text-slate-800 font-bold' : '' ?>">
                            <?= formatTanggal($kembaliData['tanggal_kembali_rencana']) ?>
                        </span>
                    </p>
                    <?php if ($kembaliData['denda'] > 0): ?>
                        <p><span class="text-slate-400">Denda Saat Ini:</span> <span
                                class="text-slate-800 font-bold"><?= formatRupiah($kembaliData['denda']) ?></span></p>
                    <?php endif; ?>
                </div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="kembali">
                    <input type="hidden" name="id" value="<?= $kembaliData['id'] ?>">
                    <div>
                        <label class="text-sm font-medium text-slate-600 block mb-1">Tanggal Pengembalian Aktual</label>
                        <input type="date" name="tanggal_aktual" value="<?= date('Y-m-d') ?>" required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                    </div>
                    <?php if ($kembaliData['denda'] > 0): ?>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" name="bayar_denda" id="bayarDenda" class="w-4 h-4 rounded text-black">
                            <label for="bayarDenda" class="text-sm text-slate-600">Denda sudah dibayar</label>
                        </div>
                    <?php endif; ?>
                    <div class="flex gap-3 pt-2">
                        <a href="transaksi.php"
                            class="flex-1 text-center border border-gray-200 text-slate-500 py-2.5 rounded-xl text-sm">Batal</a>
                        <button type="submit"
                            class="flex-1 bg-slate-1000 hover:bg-emerald-600 text-white py-2.5 rounded-xl text-sm font-semibold">Konfirmasi
                            Kembali</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>