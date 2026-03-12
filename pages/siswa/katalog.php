<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireSiswa();

$pageTitle = 'Katalog Buku';
$uid = $user['id'];

// ── PROSES PEMINJAMAN OLEH SISWA ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pinjam') {
    $buku_id = (int) $_POST['buku_id'];
    $search_redirect = $_POST['q'] ?? '';

    // Cek stok
    $stmtStok = $pdo->prepare("SELECT stok_tersedia FROM buku WHERE id = ?");
    $stmtStok->execute([$buku_id]);
    $stok = $stmtStok->fetchColumn();

    // Cek sudah dipinjam buku yang sama
    $stmtDouble = $pdo->prepare("SELECT id FROM peminjaman WHERE user_id = ? AND buku_id = ? AND status IN ('dipinjam','terlambat')");
    $stmtDouble->execute([$uid, $buku_id]);

    // Cek jumlah pinjaman aktif
    $stmtJml = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE user_id = ? AND status IN ('dipinjam','terlambat')");
    $stmtJml->execute([$uid]);
    $jmlAktif = $stmtJml->fetchColumn();

    // Cek denda belum lunas
    $stmtDenda = $pdo->prepare("SELECT COALESCE(SUM(denda),0) FROM peminjaman WHERE user_id = ? AND denda > 0 AND denda_terbayar = 0");
    $stmtDenda->execute([$uid]);
    $dendaAktif = $stmtDenda->fetchColumn();

    if ($stok < 1) {
        setFlash('error', 'Maaf, stok buku sedang habis.');
    } elseif ($stmtDouble->fetch()) {
        setFlash('error', 'Kamu sudah meminjam buku ini dan belum mengembalikannya.');
    } elseif ($jmlAktif >= MAX_PINJAM) {
        setFlash('error', 'Kamu sudah mencapai batas maksimal peminjaman (' . MAX_PINJAM . ' buku). Kembalikan buku terlebih dahulu.');
    } elseif ($dendaAktif > 0) {
        setFlash('error', 'Kamu masih memiliki denda sebesar ' . formatRupiah($dendaAktif) . '. Lunasi denda terlebih dahulu sebelum meminjam buku baru.');
    } else {
        $tglPinjam = date('Y-m-d');
        $tglKembali = date('Y-m-d', strtotime('+' . MASA_PINJAM . ' days'));
        $cnt = $pdo->query("SELECT COUNT(*)+1 FROM peminjaman")->fetchColumn();
        $kode = 'TRX-' . date('Ymd') . '-' . str_pad($cnt, 3, '0', STR_PAD_LEFT);

        $pdo->prepare("INSERT INTO peminjaman (kode_pinjam, user_id, buku_id, tanggal_pinjam, tanggal_kembali_rencana, created_by) VALUES (?,?,?,?,?,?)")
            ->execute([$kode, $uid, $buku_id, $tglPinjam, $tglKembali, $uid]);

        setFlash('success', 'Berhasil meminjam buku! Harap dikembalikan paling lambat ' . formatTanggal($tglKembali) . '. Kode: ' . $kode);
    }

    header('Location: katalog.php' . ($search_redirect ? '?q=' . urlencode($search_redirect) : ''));
    exit;
}

// ── READ DATA ──────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$kategori = $_GET['kategori'] ?? '';
$where = '1=1';
$params = [];
if ($search) {
    $where .= " AND (judul LIKE ? OR pengarang LIKE ? OR kode_buku LIKE ? OR isbn LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}
if ($kategori) {
    $where .= " AND kategori = ?";
    $params[] = $kategori;
}

$bukuStmt = $pdo->prepare("SELECT * FROM buku WHERE $where ORDER BY judul");
$bukuStmt->execute($params);
$bukuList = $bukuStmt->fetchAll();

$kategoriList = $pdo->query("SELECT DISTINCT kategori FROM buku WHERE kategori != '' ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);

// Buku yang sedang dipinjam user ini
$stmtSedang = $pdo->prepare("SELECT buku_id FROM peminjaman WHERE user_id = ? AND status IN ('dipinjam','terlambat')");
$stmtSedang->execute([$uid]);
$bukuSedangDipinjam = $stmtSedang->fetchAll(PDO::FETCH_COLUMN);

// Kondisi siswa
$jmlAktif = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE user_id = ? AND status IN ('dipinjam','terlambat')");
$jmlAktif->execute([$uid]);
$jmlAktif = $jmlAktif->fetchColumn();

$dendaAktif = $pdo->prepare("SELECT COALESCE(SUM(denda),0) FROM peminjaman WHERE user_id = ? AND denda > 0 AND denda_terbayar = 0");
$dendaAktif->execute([$uid]);
$dendaAktif = $dendaAktif->fetchColumn();

$bisaPinjam = ($jmlAktif < MAX_PINJAM) && ($dendaAktif == 0);

require_once '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900  ">Katalog Buku</h1>
    <p class="text-slate-400 text-sm mt-1">Temukan dan pinjam buku yang kamu butuhkan</p>
</div>

<!-- Info bar kondisi siswa -->
<?php if ($dendaAktif > 0): ?>
    <div class="mb-4 px-4 py-3 rounded-xl bg-slate-100 border border-neutral-300 text-slate-800 text-sm flex items-center gap-2">
        <i class="fas fa-triangle-exclamation flex-shrink-0"></i>
        Kamu memiliki denda belum lunas sebesar <strong class="mx-1"><?= formatRupiah($dendaAktif) ?></strong>. Lunasi ke
        petugas perpustakaan sebelum meminjam buku baru.
    </div>
<?php elseif ($jmlAktif >= MAX_PINJAM): ?>
    <div
        class="mb-4 px-4 py-3 rounded-xl bg-amber-50 border border-amber-200 text-slate-600 text-sm flex items-center gap-2">
        <i class="fas fa-circle-info flex-shrink-0"></i>
        Kamu sudah meminjam <?= $jmlAktif ?> buku (batas maksimal <?= MAX_PINJAM ?>). Kembalikan buku terlebih dahulu untuk
        bisa meminjam lagi.
    </div>
<?php else: ?>
    <div
        class="mb-4 px-4 py-3 rounded-xl bg-slate-100 border border-neutral-300 text-slate-600 text-sm flex items-center gap-2">
        <i class="fas fa-circle-check flex-shrink-0"></i>
        Kamu bisa meminjam <strong class="mx-1"><?= MAX_PINJAM - $jmlAktif ?> buku lagi</strong>. Masa pinjam
        <?= MASA_PINJAM ?> hari sejak tanggal peminjaman.
    </div>
<?php endif; ?>

<!-- Search & Filter -->
<div class="card p-4 mb-5">
    <form method="GET" class="flex gap-3 flex-wrap">
        <div class="relative flex-1 min-w-48">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i
                    class="fas fa-search text-sm"></i></span>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                placeholder="Cari judul, pengarang, ISBN..."
                class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-200 outline-none">
        </div>
        <select name="kategori"
            class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
            <option value="">Semua Kategori</option>
            <?php foreach ($kategoriList as $k): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= $k == $kategori ? 'selected' : '' ?>>
                    <?= htmlspecialchars($k) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary px-4 py-2.5 rounded-xl text-sm font-semibold">Cari</button>
        <?php if ($search || $kategori): ?>
            <a href="katalog.php"
                class="px-4 py-2.5 rounded-xl text-sm border border-gray-200 text-slate-500 hover:bg-slate-50">Reset</a>
        <?php endif; ?>
    </form>
</div>

<p class="text-slate-400 text-sm mb-4"><?= count($bukuList) ?> buku ditemukan</p>

<!-- Grid Buku -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4">
    <?php foreach ($bukuList as $b):
        $sedangDipinjamUser = in_array($b['id'], $bukuSedangDipinjam);
        $stokHabis = $b['stok_tersedia'] < 1;
        $bolehPinjam = $bisaPinjam && !$sedangDipinjamUser && !$stokHabis;
        ?>
        <div class="card p-5 flex flex-col <?= $stokHabis ? 'opacity-60' : '' ?>">
            <!-- Header -->
            <div class="flex items-start gap-3 mb-3">
                <div
                    class="w-12 h-14 bg-gradient-to-br from-primary-100 to-primary-200 rounded-lg flex items-center justify-center flex-shrink-0 relative">
                    <i class="fas fa-book text-primary-500 text-xl"></i>
                    <?php if ($sedangDipinjamUser): ?>
                        <span
                            class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-amber-400 rounded-full flex items-center justify-center shadow">
                            <i class="fas fa-bookmark text-white" style="font-size:9px"></i>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-slate-900 text-sm leading-tight line-clamp-2">
                        <?= htmlspecialchars($b['judul']) ?></p>
                    <p class="text-slate-400 text-xs mt-0.5"><?= htmlspecialchars($b['pengarang']) ?></p>
                </div>
            </div>

            <!-- Detail -->
            <div class="space-y-1.5 text-xs text-slate-400 mb-3 flex-1">
                <?php if ($b['penerbit']): ?>
                    <div class="flex justify-between gap-2">
                        <span>Penerbit</span>
                        <span
                            class="text-slate-600 text-right truncate max-w-[60%]"><?= htmlspecialchars($b['penerbit']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($b['tahun_terbit']): ?>
                    <div class="flex justify-between"><span>Tahun</span><span
                            class="text-slate-600"><?= $b['tahun_terbit'] ?></span></div>
                <?php endif; ?>
                <div class="flex justify-between"><span>Kode</span><span
                        class="font-mono text-black font-semibold"><?= $b['kode_buku'] ?></span></div>
                <?php if ($b['lokasi_rak']): ?>
                    <div class="flex justify-between"><span>Lokasi Rak</span><span
                            class="text-slate-600"><?= htmlspecialchars($b['lokasi_rak']) ?></span></div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="pt-3 border-t border-slate-100 space-y-2.5">
                <div class="flex items-center justify-between">
                    <?php if ($b['kategori']): ?>
                        <span
                            class="bg-gray-100 text-slate-500 text-xs px-2 py-0.5 rounded-full"><?= htmlspecialchars($b['kategori']) ?></span>
                    <?php else: ?><span></span><?php endif; ?>

                    <?php if ($stokHabis): ?>
                        <span class="text-slate-600 text-xs font-bold">✗ Stok Habis</span>
                    <?php elseif ($sedangDipinjamUser): ?>
                        <span class="text-slate-500 text-xs font-bold"><i class="fas fa-bookmark mr-0.5"></i> Dipinjam</span>
                    <?php else: ?>
                        <span class="text-slate-600 text-xs font-bold">✓ Tersedia (<?= $b['stok_tersedia'] ?>)</span>
                    <?php endif; ?>
                </div>

                <!-- Tombol aksi -->
                <?php if ($sedangDipinjamUser): ?>
                    <div
                        class="w-full py-2 rounded-xl text-xs font-semibold text-center bg-amber-50 text-slate-600 border border-amber-200">
                        <i class="fas fa-clock mr-1"></i> Sedang kamu pinjam
                    </div>
                <?php elseif ($stokHabis): ?>
                    <div class="w-full py-2 rounded-xl text-xs font-semibold text-center bg-slate-50 text-slate-400">
                        Stok tidak tersedia
                    </div>
                <?php elseif (!$bisaPinjam): ?>
                    <div class="w-full py-2 rounded-xl text-xs font-semibold text-center bg-slate-50 text-slate-400 cursor-not-allowed border border-slate-100"
                        title="<?= $dendaAktif > 0 ? 'Lunasi denda terlebih dahulu' : 'Batas pinjaman tercapai' ?>">
                        <i
                            class="fas fa-lock mr-1"></i><?= $dendaAktif > 0 ? 'Ada denda belum lunas' : 'Batas pinjam tercapai' ?>
                    </div>
                <?php else: ?>
                    <button
                        class="btn-pinjam btn-primary w-full py-2 rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5"
                        data-id="<?= $b['id'] ?>" data-judul="<?= htmlspecialchars($b['judul'], ENT_QUOTES) ?>"
                        data-pengarang="<?= htmlspecialchars($b['pengarang'], ENT_QUOTES) ?>">
                        <i class="fas fa-book-bookmark"></i> Pinjam Buku Ini
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($bukuList)): ?>
        <div class="col-span-full text-center py-16 text-slate-400">
            <i class="fas fa-search text-4xl mb-3 block opacity-30"></i>
            <p>Buku tidak ditemukan</p>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL KONFIRMASI PINJAM -->
<div id="modalPinjam" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl">
        <div class="p-6">
            <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-book-bookmark text-black text-2xl"></i>
            </div>
            <h3 class="font-bold text-slate-900 text-lg text-center mb-1">Konfirmasi Peminjaman</h3>
            <p class="text-slate-400 text-sm text-center mb-5">Pastikan kamu yakin ingin meminjam buku ini</p>

            <div class="bg-slate-50 rounded-xl p-4 mb-5 text-sm space-y-2.5">
                <div class="flex gap-3">
                    <span class="text-slate-400 w-24 flex-shrink-0">Judul</span>
                    <span class="font-semibold text-slate-900 leading-tight" id="modalJudul"></span>
                </div>
                <div class="flex gap-3">
                    <span class="text-slate-400 w-24 flex-shrink-0">Pengarang</span>
                    <span class="text-slate-600" id="modalPengarang"></span>
                </div>
                <div class="border-t border-gray-200 pt-2 mt-2 space-y-1.5">
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-400">Tanggal Pinjam</span>
                        <span class="font-medium text-slate-600"><?= date('d M Y') ?></span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-400">Jatuh Tempo</span>
                        <span
                            class="font-bold text-slate-800"><?= date('d M Y', strtotime('+' . MASA_PINJAM . ' days')) ?></span>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-400">Denda terlambat</span>
                        <span class="text-slate-500"><?= formatRupiah(DENDA_PER_HARI) ?>/hari</span>
                    </div>
                </div>
            </div>

            <form method="POST" id="formPinjam">
                <input type="hidden" name="action" value="pinjam">
                <input type="hidden" name="buku_id" id="inputBukuId" value="">
                <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
                <div class="flex gap-3">
                    <button type="button" onclick="tutupModal()"
                        class="flex-1 border border-gray-200 text-slate-500 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="flex-1 btn-primary py-2.5 rounded-xl text-sm font-semibold">
                        <i class="fas fa-check mr-1"></i> Ya, Pinjam!
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Pakai event delegation agar pasti bekerja
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-pinjam');
        if (btn) {
            document.getElementById('inputBukuId').value = btn.dataset.id;
            document.getElementById('modalJudul').textContent = btn.dataset.judul;
            document.getElementById('modalPengarang').textContent = btn.dataset.pengarang;
            document.getElementById('modalPinjam').classList.remove('hidden');
        }
    });

    function tutupModal() {
        document.getElementById('modalPinjam').classList.add('hidden');
    }

    document.getElementById('modalPinjam').addEventListener('click', function (e) {
        if (e.target === this) tutupModal();
    });
</script>

<?php require_once '../../includes/footer.php'; ?>