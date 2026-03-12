<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireAdmin();

$pageTitle = 'Kelola Denda';

// ── Auto-update denda terlambat ────────────────────────────
$pdo->exec("UPDATE peminjaman SET status='terlambat', denda=DATEDIFF(CURDATE(),tanggal_kembali_rencana)*" . DENDA_PER_HARI . " WHERE status='dipinjam' AND tanggal_kembali_rencana < CURDATE()");

// ── PROSES PEMBAYARAN ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bayar') {
    $peminjaman_id = (int) $_POST['peminjaman_id'];
    $jumlah_bayar = (float) str_replace(['.', ','], ['', '.'], $_POST['jumlah_bayar']);
    $metode = $_POST['metode'] ?? 'tunai';
    $catatan = trim($_POST['catatan'] ?? '');

    // Ambil data peminjaman
    $stmt = $pdo->prepare("SELECT p.*, u.id AS uid FROM peminjaman p JOIN users u ON p.user_id=u.id WHERE p.id=?");
    $stmt->execute([$peminjaman_id]);
    $pinjam = $stmt->fetch();

    if (!$pinjam) {
        setFlash('error', 'Data peminjaman tidak ditemukan.');
    } elseif ($pinjam['denda'] <= 0) {
        setFlash('error', 'Tidak ada denda untuk transaksi ini.');
    } elseif ($jumlah_bayar < $pinjam['denda']) {
        setFlash('error', 'Jumlah pembayaran kurang dari total denda (' . formatRupiah($pinjam['denda']) . ').');
    } else {
        $kembalian = $jumlah_bayar - $pinjam['denda'];

        // Generate kode bayar
        $cnt = $pdo->query("SELECT COUNT(*)+1 FROM pembayaran_denda")->fetchColumn();
        $kode_bayar = 'PAY-' . date('Ymd') . '-' . str_pad($cnt, 3, '0', STR_PAD_LEFT);

        // Simpan pembayaran
        $pdo->prepare("INSERT INTO pembayaran_denda (kode_bayar, peminjaman_id, user_id, jumlah_denda, jumlah_bayar, kembalian, metode, catatan, dibayar_oleh)
                        VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$kode_bayar, $peminjaman_id, $pinjam['user_id'], $pinjam['denda'], $jumlah_bayar, $kembalian, $metode, $catatan, $_SESSION['user_id']]);

        // Tandai denda lunas di peminjaman
        $pdo->prepare("UPDATE peminjaman SET denda_terbayar=1 WHERE id=?")->execute([$peminjaman_id]);

        setFlash('success', "Pembayaran berhasil! Kode: $kode_bayar. Kembalian: " . formatRupiah($kembalian));
        header('Location: denda.php?tab=lunas');
        exit;
    }
    header('Location: denda.php');
    exit;
}

// ── READ DATA ──────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'belum';
$search = trim($_GET['q'] ?? '');

// Denda BELUM LUNAS (aktif)
$whereB = "p.denda > 0 AND p.denda_terbayar = 0";
$paramsB = [];
if ($search) {
    $whereB .= " AND (u.nama LIKE ? OR u.no_anggota LIKE ? OR b.judul LIKE ? OR p.kode_pinjam LIKE ?)";
    $paramsB = ["%$search%", "%$search%", "%$search%", "%$search%"];
}
$stmtBelum = $pdo->prepare("
    SELECT p.*, u.nama AS nama_siswa, u.no_anggota, u.kelas, u.telepon,
           b.judul AS judul_buku, b.kode_buku
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    JOIN buku b  ON p.buku_id = b.id
    WHERE $whereB
    ORDER BY p.denda DESC, p.tanggal_kembali_rencana ASC
");
$stmtBelum->execute($paramsB);
$dendaBelum = $stmtBelum->fetchAll();

// Denda SUDAH LUNAS (riwayat pembayaran)
$whereL = "pd.id IS NOT NULL";
$paramsL = [];
if ($search) {
    $whereL .= " AND (u.nama LIKE ? OR u.no_anggota LIKE ? OR b.judul LIKE ? OR pd.kode_bayar LIKE ?)";
    $paramsL = ["%$search%", "%$search%", "%$search%", "%$search%"];
}
$stmtLunas = $pdo->prepare("
    SELECT pd.*, p.kode_pinjam, p.denda AS denda_asal, p.tanggal_kembali_rencana,
           p.tanggal_kembali_aktual, p.status AS status_pinjam,
           u.nama AS nama_siswa, u.no_anggota, u.kelas,
           b.judul AS judul_buku, b.kode_buku,
           a.nama AS nama_admin
    FROM pembayaran_denda pd
    JOIN peminjaman p ON pd.peminjaman_id = p.id
    JOIN users u      ON pd.user_id = u.id
    JOIN buku b       ON p.buku_id = b.id
    JOIN users a      ON pd.dibayar_oleh = a.id
    ORDER BY pd.created_at DESC
");
$stmtLunas->execute($paramsL);
$dendaLunas = $stmtLunas->fetchAll();

// Stats
$totalBelumLunas = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE denda>0 AND denda_terbayar=0")->fetchColumn();
$nominalBelum = $pdo->query("SELECT COALESCE(SUM(denda),0) FROM peminjaman WHERE denda>0 AND denda_terbayar=0")->fetchColumn();
$totalLunas = $pdo->query("SELECT COUNT(*) FROM pembayaran_denda")->fetchColumn();
$nominalLunas = $pdo->query("SELECT COALESCE(SUM(jumlah_denda),0) FROM pembayaran_denda")->fetchColumn();

// Data untuk modal bayar
$bayarData = null;
if (isset($_GET['bayar'])) {
    $s = $pdo->prepare("SELECT p.*, u.nama, u.no_anggota, u.kelas, b.judul, b.kode_buku FROM peminjaman p JOIN users u ON p.user_id=u.id JOIN buku b ON p.buku_id=b.id WHERE p.id=? AND p.denda>0 AND p.denda_terbayar=0");
    $s->execute([(int) $_GET['bayar']]);
    $bayarData = $s->fetch();
    if (!$bayarData) {
        setFlash('error', 'Data tidak ditemukan.');
        header('Location: denda.php');
        exit;
    }
}

// Data untuk kwitansi
$kwitansiData = null;
if (isset($_GET['kwitansi'])) {
    $s = $pdo->prepare("SELECT pd.*, p.kode_pinjam, p.tanggal_kembali_rencana, p.tanggal_kembali_aktual, u.nama, u.no_anggota, u.kelas, b.judul, b.kode_buku, a.nama AS nama_admin FROM pembayaran_denda pd JOIN peminjaman p ON pd.peminjaman_id=p.id JOIN users u ON pd.user_id=u.id JOIN buku b ON p.buku_id=b.id JOIN users a ON pd.dibayar_oleh=a.id WHERE pd.id=?");
    $s->execute([(int) $_GET['kwitansi']]);
    $kwitansiData = $s->fetch();
}

require_once '../../includes/header.php';
?>

<!-- ── HALAMAN UTAMA ──────────────────────────────────────── -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900  ">Kelola Denda</h1>
        <p class="text-slate-400 text-sm mt-1">Manajemen pembayaran denda keterlambatan</p>
    </div>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="card p-5">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-slate-200 rounded-xl flex items-center justify-center">
                <i class="fas fa-circle-exclamation text-slate-600"></i>
            </div>
            <p class="text-slate-400 text-sm">Belum Lunas</p>
        </div>
        <p class="text-2xl font-bold text-slate-900"><?= $totalBelumLunas ?> <span
                class="text-sm font-normal text-slate-400">kasus</span></p>
        <p class="text-slate-800 font-semibold text-sm mt-0.5"><?= formatRupiah($nominalBelum) ?></p>
    </div>
    <div class="card p-5">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-circle-check text-slate-500"></i>
            </div>
            <p class="text-slate-400 text-sm">Sudah Lunas</p>
        </div>
        <p class="text-2xl font-bold text-slate-900"><?= $totalLunas ?> <span
                class="text-sm font-normal text-slate-400">kasus</span></p>
        <p class="text-slate-600 font-semibold text-sm mt-0.5"><?= formatRupiah($nominalLunas) ?></p>
    </div>
    <div class="card p-5 xl:col-span-2">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-coins text-black"></i>
            </div>
            <p class="text-slate-400 text-sm">Total Denda Terkumpul</p>
        </div>
        <p class="text-3xl font-bold text-slate-600"><?= formatRupiah($nominalLunas) ?></p>
        <div class="mt-2 bg-gray-100 rounded-full h-2">
            <?php $pct = ($nominalBelum + $nominalLunas) > 0 ? ($nominalLunas / ($nominalBelum + $nominalLunas)) * 100 : 0; ?>
            <div class="bg-primary-500 h-2 rounded-full" style="width:<?= $pct ?>%"></div>
        </div>
        <p class="text-xs text-slate-400 mt-1"><?= number_format($pct, 0) ?>% dari total denda sudah dilunasi</p>
    </div>
</div>

<!-- Search -->
<div class="card p-4 mb-4">
    <form method="GET" class="flex gap-3">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <div class="relative flex-1">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><i
                    class="fas fa-search text-sm"></i></span>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                placeholder="Cari nama, no. anggota, judul buku..."
                class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-200 outline-none">
        </div>
        <button type="submit" class="btn-primary px-4 py-2.5 rounded-xl text-sm font-semibold">Cari</button>
        <?php if ($search): ?><a href="?tab=<?= $tab ?>"
                class="px-4 py-2.5 rounded-xl text-sm border border-gray-200 text-slate-500 hover:bg-slate-50">Reset</a><?php endif; ?>
    </form>
</div>

<!-- Tabs -->
<div class="flex gap-1 mb-4 bg-gray-100 p-1 rounded-xl w-fit">
    <a href="?tab=belum<?= $search ? '&q=' . urlencode($search) : '' ?>"
        class="px-5 py-2 rounded-lg text-sm font-semibold transition-all <?= $tab === 'belum' ? 'bg-white shadow text-slate-900' : 'text-slate-400 hover:text-slate-600' ?>">
        Belum Lunas
        <?php if ($totalBelumLunas > 0): ?>
            <span class="ml-1.5 bg-slate-1000 text-white text-xs px-1.5 py-0.5 rounded-full"><?= $totalBelumLunas ?></span>
        <?php endif; ?>
    </a>
    <a href="?tab=lunas<?= $search ? '&q=' . urlencode($search) : '' ?>"
        class="px-5 py-2 rounded-lg text-sm font-semibold transition-all <?= $tab === 'lunas' ? 'bg-white shadow text-slate-900' : 'text-slate-400 hover:text-slate-600' ?>">
        Riwayat Lunas
    </a>
</div>

<?php if ($tab === 'belum'): ?>
    <!-- ── TAB BELUM LUNAS ─────────────────────────────────── -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr class="text-left text-slate-400">
                        <th class="px-4 py-3.5 font-semibold">Anggota</th>
                        <th class="px-4 py-3.5 font-semibold">Buku</th>
                        <th class="px-4 py-3.5 font-semibold">Jatuh Tempo</th>
                        <th class="px-4 py-3.5 font-semibold">Keterlambatan</th>
                        <th class="px-4 py-3.5 font-semibold text-right">Total Denda</th>
                        <th class="px-4 py-3.5 font-semibold text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($dendaBelum as $d):
                        $hariTerlambat = (new DateTime())->diff(new DateTime($d['tanggal_kembali_rencana']))->days;
                        ?>
                        <tr class="table-row">
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="w-9 h-9 rounded-full bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                        <?= strtoupper(substr($d['nama_siswa'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-900"><?= htmlspecialchars($d['nama_siswa']) ?></p>
                                        <p class="text-slate-400 text-xs"><?= $d['no_anggota'] ?> ·
                                            <?= htmlspecialchars($d['kelas'] ?? '-') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="text-slate-600 font-medium text-xs truncate max-w-[160px]">
                                    <?= htmlspecialchars($d['judul_buku']) ?></p>
                                <p class="text-slate-400 text-xs"><?= $d['kode_buku'] ?> · <?= $d['kode_pinjam'] ?></p>
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="text-slate-800 font-semibold text-sm">
                                    <?= formatTanggal($d['tanggal_kembali_rencana']) ?></p>
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="bg-slate-200 text-slate-800 text-xs font-bold px-2.5 py-1 rounded-full">
                                    <?= $hariTerlambat ?> hari
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <p class="text-slate-800 font-bold text-base"><?= formatRupiah($d['denda']) ?></p>
                                <p class="text-slate-400 text-xs"><?= formatRupiah(DENDA_PER_HARI) ?>/hari</p>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <a href="?bayar=<?= $d['id'] ?>"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-slate-1000 hover:bg-emerald-600 text-white rounded-xl text-xs font-semibold transition-colors">
                                    <i class="fas fa-money-bill-wave"></i> Bayar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($dendaBelum)): ?>
                        <tr>
                            <td colspan="6" class="py-16 text-center text-slate-400">
                                <i class="fas fa-circle-check text-5xl text-emerald-200 mb-3 block"></i>
                                <p class="font-medium">Tidak ada denda yang belum lunas</p>
                                <p class="text-xs mt-1">Semua anggota sudah melunasi dendanya</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- ── TAB RIWAYAT LUNAS ───────────────────────────────── -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr class="text-left text-slate-400">
                        <th class="px-4 py-3.5 font-semibold">Kode Bayar</th>
                        <th class="px-4 py-3.5 font-semibold">Anggota</th>
                        <th class="px-4 py-3.5 font-semibold">Buku</th>
                        <th class="px-4 py-3.5 font-semibold">Total Denda</th>
                        <th class="px-4 py-3.5 font-semibold">Dibayar</th>
                        <th class="px-4 py-3.5 font-semibold">Kembalian</th>
                        <th class="px-4 py-3.5 font-semibold">Metode</th>
                        <th class="px-4 py-3.5 font-semibold">Tgl Bayar</th>
                        <th class="px-4 py-3.5 font-semibold text-center">Kwitansi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($dendaLunas as $d): ?>
                        <tr class="table-row">
                            <td class="px-4 py-3">
                                <span
                                    class="font-mono text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded"><?= $d['kode_bayar'] ?></span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900"><?= htmlspecialchars($d['nama_siswa']) ?></p>
                                <p class="text-slate-400 text-xs"><?= $d['no_anggota'] ?></p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-slate-600 text-xs truncate max-w-[140px]">
                                    <?= htmlspecialchars($d['judul_buku']) ?></p>
                                <p class="text-slate-400 text-xs"><?= $d['kode_pinjam'] ?></p>
                            </td>
                            <td class="px-4 py-3 text-slate-800 font-semibold"><?= formatRupiah($d['jumlah_denda']) ?></td>
                            <td class="px-4 py-3 text-slate-600 font-medium"><?= formatRupiah($d['jumlah_bayar']) ?></td>
                            <td class="px-4 py-3 text-slate-600 font-semibold"><?= formatRupiah($d['kembalian']) ?></td>
                            <td class="px-4 py-3">
                                <span
                                    class="text-xs px-2 py-0.5 rounded-full font-medium
                            <?= $d['metode'] === 'tunai' ? 'bg-slate-200 text-slate-600' : 'bg-blue-100 text-blue-700' ?>">
                                    <i
                                        class="fas <?= $d['metode'] === 'tunai' ? 'fa-money-bill' : 'fa-mobile-screen' ?> mr-1"></i>
                                    <?= ucfirst($d['metode']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-400 text-xs"><?= date('d M Y H:i', strtotime($d['created_at'])) ?>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button
                                        class="btn-preview-kwitansi w-8 h-8 bg-primary-50 hover:bg-slate-100 text-black rounded-lg flex items-center justify-center transition-colors"
                                        title="Preview Kwitansi" data-id="<?= $d['id'] ?>"
                                        data-kode="<?= htmlspecialchars($d['kode_bayar'], ENT_QUOTES) ?>"
                                        data-tgl="<?= date('d M Y H:i', strtotime($d['created_at'])) ?>"
                                        data-kode-pinjam="<?= htmlspecialchars($d['kode_pinjam'], ENT_QUOTES) ?>"
                                        data-nama="<?= htmlspecialchars($d['nama_siswa'], ENT_QUOTES) ?>"
                                        data-no-anggota="<?= htmlspecialchars($d['no_anggota'], ENT_QUOTES) ?>"
                                        data-kelas="<?= htmlspecialchars($d['kelas'] ?? '-', ENT_QUOTES) ?>"
                                        data-judul="<?= htmlspecialchars($d['judul_buku'], ENT_QUOTES) ?>"
                                        data-jatuh-tempo="<?= htmlspecialchars(formatTanggal($d['tanggal_kembali_rencana']), ENT_QUOTES) ?>"
                                        data-tgl-kembali="<?= htmlspecialchars(formatTanggal($d['tanggal_kembali_aktual']), ENT_QUOTES) ?>"
                                        data-jumlah-denda="<?= htmlspecialchars(formatRupiah($d['jumlah_denda']), ENT_QUOTES) ?>"
                                        data-jumlah-bayar="<?= htmlspecialchars(formatRupiah($d['jumlah_bayar']), ENT_QUOTES) ?>"
                                        data-kembalian="<?= htmlspecialchars(formatRupiah($d['kembalian']), ENT_QUOTES) ?>"
                                        data-metode="<?= ucfirst($d['metode']) ?>"
                                        data-admin="<?= htmlspecialchars($d['nama_admin'], ENT_QUOTES) ?>">
                                        <i class="fas fa-eye text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($dendaLunas)): ?>
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-400">Belum ada riwayat pembayaran denda</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>


<!-- ── MODAL BAYAR DENDA ──────────────────────────────────── -->
<?php if ($bayarData): ?>
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl">
            <!-- Header -->
            <div class="p-6 pb-0">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-slate-900 text-lg">Proses Pembayaran Denda</h3>
                    <a href="denda.php"
                        class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-slate-500 hover:bg-gray-100 rounded-lg">
                        <i class="fas fa-times"></i>
                    </a>
                </div>

                <!-- Info Peminjam -->
                <div class="bg-slate-50 rounded-xl p-4 mb-5 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Peminjam</span>
                        <span class="font-semibold text-slate-900"><?= htmlspecialchars($bayarData['nama']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">No. Anggota</span>
                        <span class="text-slate-600"><?= $bayarData['no_anggota'] ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Buku</span>
                        <span
                            class="text-slate-600 text-right max-w-[55%] leading-tight"><?= htmlspecialchars($bayarData['judul']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Jatuh Tempo</span>
                        <span
                            class="text-slate-800 font-medium"><?= formatTanggal($bayarData['tanggal_kembali_rencana']) ?></span>
                    </div>
                    <div class="border-t border-gray-200 pt-2 mt-1 flex justify-between items-center">
                        <span class="text-slate-600 font-semibold">Total Denda</span>
                        <span class="text-slate-800 font-bold text-xl"><?= formatRupiah($bayarData['denda']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <form method="POST" class="px-6 pb-6 space-y-4" id="formBayar">
                <input type="hidden" name="action" value="bayar">
                <input type="hidden" name="peminjaman_id" value="<?= $bayarData['id'] ?>">

                <div>
                    <label class="text-sm font-semibold text-slate-600 block mb-1.5">Metode Pembayaran</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="metode" value="tunai" class="peer sr-only" checked>
                            <div
                                class="border-2 border-gray-200 peer-checked:border-primary-500 peer-checked:bg-primary-50 rounded-xl p-3 text-center transition-all">
                                <i
                                    class="fas fa-money-bill-wave text-lg text-slate-400 peer-checked:text-black mb-1 block"></i>
                                <p class="text-sm font-medium text-slate-500">Tunai</p>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="metode" value="transfer" class="peer sr-only">
                            <div
                                class="border-2 border-gray-200 peer-checked:border-primary-500 peer-checked:bg-primary-50 rounded-xl p-3 text-center transition-all">
                                <i class="fas fa-mobile-screen text-lg text-slate-400 mb-1 block"></i>
                                <p class="text-sm font-medium text-slate-500">Transfer</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-600 block mb-1.5">Jumlah Dibayar (Rp)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-medium text-sm">Rp</span>
                        <input type="text" name="jumlah_bayar" id="inputJumlahBayar"
                            placeholder="<?= number_format($bayarData['denda'], 0, ',', '.') ?>" required
                            class="w-full border-2 border-gray-200 focus:border-primary-400 rounded-xl pl-9 pr-4 py-3 text-sm outline-none font-semibold text-slate-900 transition-colors"
                            oninput="hitungKembalian(<?= $bayarData['denda'] ?>)">
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Minimum: <?= formatRupiah($bayarData['denda']) ?></p>
                </div>

                <!-- Kembalian Display -->
                <div id="kembalianBox"
                    class="hidden bg-slate-100 border border-neutral-300 rounded-xl px-4 py-3 flex items-center justify-between">
                    <span class="text-slate-600 text-sm font-medium"><i class="fas fa-coins mr-2"></i>Kembalian</span>
                    <span class="text-slate-600 font-bold text-lg" id="kembalianVal">Rp 0</span>
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-600 block mb-1.5">Catatan <span
                            class="font-normal text-slate-400">(opsional)</span></label>
                    <textarea name="catatan" rows="2" placeholder="Misal: Dibayar tunai oleh orang tua..."
                        class="w-full border-2 border-gray-200 focus:border-primary-400 rounded-xl px-3 py-2.5 text-sm outline-none resize-none transition-colors"></textarea>
                </div>

                <div class="flex gap-3 pt-1">
                    <a href="denda.php"
                        class="flex-1 text-center border-2 border-gray-200 text-slate-500 py-3 rounded-xl text-sm font-medium hover:bg-slate-50">Batal</a>
                    <button type="submit"
                        class="flex-1 bg-slate-1000 hover:bg-emerald-600 text-white py-3 rounded-xl text-sm font-bold transition-colors">
                        <i class="fas fa-check mr-1.5"></i> Konfirmasi Bayar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function hitungKembalian(totalDenda) {
            const raw = document.getElementById('inputJumlahBayar').value.replace(/\./g, '').replace(',', '.');
            const bayar = parseFloat(raw) || 0;
            const kembalian = bayar - totalDenda;
            const box = document.getElementById('kembalianBox');
            const val = document.getElementById('kembalianVal');

            if (bayar >= totalDenda) {
                box.classList.remove('hidden');
                val.textContent = 'Rp ' + kembalian.toLocaleString('id-ID');
                box.classList.toggle('border-neutral-300', false);
                box.classList.toggle('bg-slate-100', false);
                box.classList.add('bg-slate-100', 'border-neutral-300');
                val.className = 'text-slate-600 font-bold text-lg';
            } else if (bayar > 0) {
                box.classList.remove('hidden');
                box.classList.remove('bg-slate-100', 'border-neutral-300');
                box.classList.add('bg-slate-100', 'border-neutral-300');
                val.textContent = 'Kurang ' + 'Rp ' + Math.abs(kembalian).toLocaleString('id-ID');
                val.className = 'text-slate-800 font-bold text-lg';
            } else {
                box.classList.add('hidden');
            }
        }

        // Styling radio yang diklik
        document.querySelectorAll('input[name="metode"]').forEach(radio => {
            radio.addEventListener('change', function () {
                document.querySelectorAll('input[name="metode"]').forEach(r => {
                    r.nextElementSibling.querySelector('i').classList.remove('text-black');
                    r.nextElementSibling.querySelector('i').classList.add('text-slate-400');
                });
                if (this.checked) {
                    this.nextElementSibling.querySelector('i').classList.remove('text-slate-400');
                    this.nextElementSibling.querySelector('i').classList.add('text-black');
                }
            });
        });
    </script>
<?php endif; ?>

<!-- ── MODAL PREVIEW KWITANSI ──────────────────────────────── -->
<div id="modalKwitansi" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl flex flex-col max-h-[90vh]">
        <!-- Modal Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 flex-shrink-0">
            <h3 class="font-bold text-slate-900">Preview Kwitansi</h3>
            <div class="flex items-center gap-2">
                <button id="btnDownloadPdf"
                    class="flex items-center gap-2 px-4 py-2 bg-black hover:bg-primary-700 text-white rounded-xl text-sm font-semibold transition-colors">
                    <i class="fas fa-download"></i> Download PDF
                </button>
                <button id="btnTutupKwitansi"
                    class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-slate-500 hover:bg-gray-100 rounded-lg">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <!-- Preview Area -->
        <div class="overflow-y-auto flex-1 p-6 bg-slate-50">
            <div id="kwitansiPreview" class="bg-white mx-auto shadow-sm"
                style="width:420px; padding:32px; font-family: 'Georgia', serif;">
                <!-- Header Kwitansi -->
                <div
                    style="text-align:center; border-bottom: 3px double #312e81; padding-bottom:16px; margin-bottom:16px;">
                    <h2
                        style="margin:0; font-size:15px; color:#312e81; letter-spacing:1px; text-transform:uppercase; font-weight:bold;">
                        Kwitansi Pembayaran Denda</h2>
                    <p style="margin:4px 0 0; font-size:11px; color:#666;" id="kw-sekolah"></p>
                </div>

                <!-- Info Transaksi -->
                <table style="width:100%; font-size:12px; border-collapse:collapse; margin-bottom:12px;">
                    <tr>
                        <td style="padding:3px 0; color:#888; width:145px;">No. Kwitansi</td>
                        <td style="font-weight:bold; color:#312e81;" id="kw-kode"></td>
                    </tr>
                    <tr>
                        <td style="padding:3px 0; color:#888;">Tanggal</td>
                        <td id="kw-tgl"></td>
                    </tr>
                    <tr>
                        <td style="padding:3px 0; color:#888;">No. Transaksi</td>
                        <td id="kw-kode-pinjam"></td>
                    </tr>
                </table>

                <hr style="border:none; border-top:1px dashed #ccc; margin:10px 0;">

                <!-- Info Peminjam -->
                <table style="width:100%; font-size:12px; border-collapse:collapse; margin-bottom:12px;">
                    <tr>
                        <td style="padding:3px 0; color:#888; width:145px;">Nama Peminjam</td>
                        <td style="font-weight:600;" id="kw-nama"></td>
                    </tr>
                    <tr>
                        <td style="padding:3px 0; color:#888;">No. Anggota</td>
                        <td id="kw-no-anggota"></td>
                    </tr>
                    <tr>
                        <td style="padding:3px 0; color:#888;">Kelas</td>
                        <td id="kw-kelas"></td>
                    </tr>
                </table>

                <hr style="border:none; border-top:1px dashed #ccc; margin:10px 0;">

                <!-- Info Buku -->
                <table style="width:100%; font-size:12px; border-collapse:collapse; margin-bottom:12px;">
                    <tr>
                        <td style="padding:3px 0; color:#888; width:145px;">Judul Buku</td>
                        <td style="font-weight:600;" id="kw-judul"></td>
                    </tr>
                    <tr>
                        <td style="padding:3px 0; color:#888;">Jatuh Tempo</td>
                        <td id="kw-jatuh-tempo"></td>
                    </tr>
                    <tr>
                        <td style="padding:3px 0; color:#888;">Tgl Dikembalikan</td>
                        <td id="kw-tgl-kembali"></td>
                    </tr>
                </table>

                <hr style="border:none; border-top:1px dashed #ccc; margin:10px 0;">

                <!-- Rincian Pembayaran -->
                <table style="width:100%; font-size:12px; border-collapse:collapse; margin-bottom:16px;">
                    <tr>
                        <td style="padding:4px 0; color:#888; width:145px;">Total Denda</td>
                        <td style="color:#dc2626; font-weight:bold;" id="kw-jumlah-denda"></td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0; color:#888;">Jumlah Dibayar</td>
                        <td style="font-weight:600;" id="kw-jumlah-bayar"></td>
                    </tr>
                    <tr style="background:#f0fdf4;">
                        <td style="padding:6px 8px; color:#166534; font-weight:bold; border-radius:4px 0 0 4px;">
                            Kembalian</td>
                        <td style="padding:6px 8px; color:#166534; font-weight:bold; font-size:14px; border-radius:0 4px 4px 0;"
                            id="kw-kembalian"></td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0; color:#888;">Metode Bayar</td>
                        <td id="kw-metode"></td>
                    </tr>
                </table>

                <!-- Tanda Tangan -->
                <div style="display:flex; justify-content:space-between; margin-top:24px; font-size:11px; color:#444;">
                    <div style="text-align:center; width:45%;">
                        <p style="margin:0 0 40px;">Peminjam,</p>
                        <div style="border-top:1px solid #333; padding-top:4px;" id="kw-nama-ttd"></div>
                    </div>
                    <div style="text-align:center; width:45%;">
                        <p style="margin:0 0 40px;">Petugas Perpustakaan,</p>
                        <div style="border-top:1px solid #333; padding-top:4px;" id="kw-admin-ttd"></div>
                    </div>
                </div>

                <p
                    style="text-align:center; font-size:9px; margin-top:16px; color:#aaa; border-top:1px solid #eee; padding-top:8px;">
                    Dokumen ini merupakan bukti pembayaran denda yang sah.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- jsPDF dari CDN (perlu internet sekali untuk load) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
    // ── State kwitansi aktif ───────────────────────────────────
    let activeKwitansi = {};

    // ── Buka modal preview ─────────────────────────────────────
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-preview-kwitansi');
        if (!btn) return;

        const d = btn.dataset;
        activeKwitansi = d; // simpan untuk PDF

        // Isi konten preview
        document.getElementById('kw-sekolah').textContent = '<?= APP_NAME ?> — <?= APP_SCHOOL ?>';
        document.getElementById('kw-kode').textContent = d.kode;
        document.getElementById('kw-tgl').textContent = d.tgl;
        document.getElementById('kw-kode-pinjam').textContent = d.kodePinjam;
        document.getElementById('kw-nama').textContent = d.nama;
        document.getElementById('kw-no-anggota').textContent = d.noAnggota;
        document.getElementById('kw-kelas').textContent = d.kelas;
        document.getElementById('kw-judul').textContent = d.judul;
        document.getElementById('kw-jatuh-tempo').textContent = d.jatuhTempo;
        document.getElementById('kw-tgl-kembali').textContent = d.tglKembali;
        document.getElementById('kw-jumlah-denda').textContent = d.jumlahDenda;
        document.getElementById('kw-jumlah-bayar').textContent = d.jumlahBayar;
        document.getElementById('kw-kembalian').textContent = d.kembalian;
        document.getElementById('kw-metode').textContent = d.metode;
        document.getElementById('kw-nama-ttd').textContent = d.nama;
        document.getElementById('kw-admin-ttd').textContent = d.admin;

        document.getElementById('modalKwitansi').classList.remove('hidden');
    });

    // ── Tutup modal ────────────────────────────────────────────
    document.getElementById('btnTutupKwitansi').addEventListener('click', function () {
        document.getElementById('modalKwitansi').classList.add('hidden');
    });
    document.getElementById('modalKwitansi').addEventListener('click', function (e) {
        if (e.target === this) this.classList.add('hidden');
    });

    // ── Download PDF — menulis teks langsung ke jsPDF ─────────
    // Tidak pakai html2canvas sama sekali, 100% reliable offline
    document.getElementById('btnDownloadPdf').addEventListener('click', function () {
        const btn = this;
        const d = activeKwitansi;

        if (!d || !d.kode) { alert('Data kwitansi tidak tersedia.'); return; }
        if (typeof window.jspdf === 'undefined') {
            alert('Library jsPDF belum termuat. Pastikan ada koneksi internet saat pertama kali membuka halaman ini.'); return;
        }

        const { jsPDF } = window.jspdf;

        // Ukuran A5: 148 x 210 mm
        const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a5' });
        const pw = 148; // page width
        const lm = 15;  // left margin
        const rm = 133; // right margin (pw - lm)
        const cw = rm - lm; // content width
        let y = 15;

        // ── Helper functions ───────────────────────────────────
        const setFont = (style, size) => { pdf.setFont('helvetica', style); pdf.setFontSize(size); };
        const setColor = (r, g, b) => pdf.setTextColor(r, g, b);
        const line = (x1, y1, x2, y2, r = 200, g = 200, b = 200) => {
            pdf.setDrawColor(r, g, b); pdf.setLineWidth(0.3); pdf.line(x1, y1, x2, y2);
        };
        const row = (label, value, yPos, bold = false) => {
            setFont('normal', 9); setColor(120, 120, 120);
            pdf.text(label, lm, yPos);
            setFont(bold ? 'bold' : 'normal', 9); setColor(30, 30, 30);
            pdf.text(String(value), lm + 42, yPos);
        };

        // ── HEADER ─────────────────────────────────────────────
        // Kotak header biru gelap
        pdf.setFillColor(49, 46, 129); // indigo-900
        pdf.roundedRect(lm, y, cw, 22, 3, 3, 'F');

        setFont('bold', 13); setColor(255, 255, 255);
        pdf.text('KWITANSI PEMBAYARAN DENDA', pw / 2, y + 9, { align: 'center' });

        setFont('normal', 8); setColor(180, 180, 220);
        pdf.text('<?= APP_NAME ?> — <?= APP_SCHOOL ?>', pw / 2, y + 16, { align: 'center' });
        y += 28;

        // ── BADGE LUNAS ────────────────────────────────────────
        pdf.setFillColor(220, 252, 231); // green-100
        pdf.roundedRect(lm, y, cw, 8, 2, 2, 'F');
        setFont('bold', 8); setColor(22, 101, 52);
        pdf.text('✔ LUNAS', pw / 2, y + 5.5, { align: 'center' });
        y += 13;

        // ── SEKSI: INFO TRANSAKSI ───────────────────────────────
        setFont('bold', 8); setColor(99, 102, 241);
        pdf.text('INFORMASI TRANSAKSI', lm, y);
        line(lm, y + 2, rm, y + 2, 99, 102, 241);
        y += 7;

        row('No. Kwitansi', d.kode, y, true); y += 5.5;
        row('Tanggal', d.tgl, y); y += 5.5;
        row('No. Transaksi', d.kodePinjam, y); y += 9;

        // ── SEKSI: DATA PEMINJAM ────────────────────────────────
        setFont('bold', 8); setColor(99, 102, 241);
        pdf.text('DATA PEMINJAM', lm, y);
        line(lm, y + 2, rm, y + 2, 99, 102, 241);
        y += 7;

        row('Nama', d.nama, y, true); y += 5.5;
        row('No. Anggota', d.noAnggota, y); y += 5.5;
        row('Kelas', d.kelas, y); y += 9;

        // ── SEKSI: DATA BUKU ────────────────────────────────────
        setFont('bold', 8); setColor(99, 102, 241);
        pdf.text('DATA BUKU', lm, y);
        line(lm, y + 2, rm, y + 2, 99, 102, 241);
        y += 7;

        // Judul buku mungkin panjang, wrap
        setFont('normal', 9); setColor(120, 120, 120);
        pdf.text('Judul Buku', lm, y);
        setFont('bold', 9); setColor(30, 30, 30);
        const judulLines = pdf.splitTextToSize(d.judul, cw - 45);
        pdf.text(judulLines, lm + 42, y);
        y += (judulLines.length * 5);

        row('Jatuh Tempo', d.jatuhTempo, y); y += 5.5;
        row('Tgl Dikembalikan', d.tglKembali, y); y += 9;

        // ── SEKSI: RINCIAN PEMBAYARAN ───────────────────────────
        setFont('bold', 8); setColor(99, 102, 241);
        pdf.text('RINCIAN PEMBAYARAN', lm, y);
        line(lm, y + 2, rm, y + 2, 99, 102, 241);
        y += 7;

        row('Total Denda', d.jumlahDenda, y); y += 5.5;
        row('Jumlah Dibayar', d.jumlahBayar, y); y += 5.5;
        row('Metode', d.metode, y); y += 6;

        // Kembalian — kotak hijau menonjol
        pdf.setFillColor(240, 253, 244);
        pdf.setDrawColor(134, 239, 172);
        pdf.setLineWidth(0.5);
        pdf.roundedRect(lm, y, cw, 10, 2, 2, 'FD');
        setFont('normal', 9); setColor(22, 101, 52);
        pdf.text('Kembalian', lm + 3, y + 6.5);
        setFont('bold', 11); setColor(21, 128, 61);
        pdf.text(d.kembalian, rm - 3, y + 6.5, { align: 'right' });
        y += 16;

        // ── TANDA TANGAN ───────────────────────────────────────
        const ttdY = y + 25;
        const col1 = lm + 10;
        const col2 = pw / 2 + 10;

        setFont('normal', 8); setColor(80, 80, 80);
        pdf.text('Peminjam,', col1, y, { align: 'center' });
        pdf.text('Petugas Perpustakaan,', col2, y, { align: 'center' });

        // Garis tanda tangan
        line(col1 - 18, ttdY, col1 + 18, ttdY, 50, 50, 50);
        line(col2 - 22, ttdY, col2 + 22, ttdY, 50, 50, 50);

        setFont('bold', 8); setColor(30, 30, 30);
        pdf.text(d.nama, col1, ttdY + 4, { align: 'center' });
        pdf.text(d.admin, col2, ttdY + 4, { align: 'center' });
        y = ttdY + 12;

        // ── FOOTER ─────────────────────────────────────────────
        line(lm, y, rm, y);
        setFont('normal', 7); setColor(160, 160, 160);
        pdf.text('Dokumen ini merupakan bukti pembayaran denda yang sah.', pw / 2, y + 4, { align: 'center' });

        // ── SAVE ───────────────────────────────────────────────
        pdf.save('Kwitansi-' + d.kode + '.pdf');
    });
</script>

<?php require_once '../../includes/footer.php'; ?>