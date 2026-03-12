<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireAdmin();

$pageTitle = 'Dashboard Admin';

// Stats
$totalBuku = $pdo->query("SELECT COUNT(*) FROM buku")->fetchColumn();
$totalAnggota = $pdo->query("SELECT COUNT(*) FROM users WHERE role='siswa'")->fetchColumn();
$totalDipinjam = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status='dipinjam'")->fetchColumn();
$totalTerlambat = $pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status='terlambat' OR (status='dipinjam' AND tanggal_kembali_rencana < CURDATE())")->fetchColumn();
$totalDenda = $pdo->query("SELECT COALESCE(SUM(denda),0) FROM peminjaman WHERE denda > 0 AND denda_terbayar=0")->fetchColumn();

// Peminjaman terbaru
$recentPinjam = $pdo->query("
    SELECT p.*, u.nama AS nama_siswa, u.no_anggota, b.judul AS judul_buku, b.kode_buku
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    JOIN buku b ON p.buku_id = b.id
    ORDER BY p.created_at DESC LIMIT 8
")->fetchAll();

// Buku stok menipis
$stokMenipis = $pdo->query("SELECT * FROM buku WHERE stok_tersedia <= 1 ORDER BY stok_tersedia ASC LIMIT 5")->fetchAll();

require_once '../../includes/header.php';
?>
<div class="mb-6">
    <h1 class="page-header text-2xl font-bold text-slate-900  ">Dashboard Admin</h1>
    <p class="text-slate-400 text-sm mt-1">Selamat datang, <?= htmlspecialchars($user['nama']) ?>!</p>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="card stat-card p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 stat-icon-blue rounded-xl flex items-center justify-center">
                <i class="fas fa-book text-lg"></i>
            </div>
            <span class="text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded-full font-medium">Total</span>
        </div>
        <p class="text-3xl font-bold text-slate-900 font-display"><?= $totalBuku ?></p>
        <p class="text-slate-400 text-sm mt-1">Judul Buku</p>
    </div>

    <div class="card stat-card stat-green p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 stat-icon-teal rounded-xl flex items-center justify-center">
                <i class="fas fa-users text-lg"></i>
            </div>
            <span class="text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded-full font-medium">Siswa</span>
        </div>
        <p class="text-3xl font-bold text-slate-900 font-display"><?= $totalAnggota ?></p>
        <p class="text-slate-400 text-sm mt-1">Anggota Aktif</p>
    </div>

    <div class="card stat-card stat-yellow p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 stat-icon-amber rounded-xl flex items-center justify-center">
                <i class="fas fa-book-bookmark text-lg"></i>
            </div>
            <span class="text-xs bg-slate-200 text-slate-600 px-2 py-1 rounded-full font-medium">Aktif</span>
        </div>
        <p class="text-3xl font-bold text-slate-900 font-display"><?= $totalDipinjam ?></p>
        <p class="text-slate-400 text-sm mt-1">Sedang Dipinjam</p>
    </div>
    
    <div class="card stat-card stat-red p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 stat-icon-rose rounded-xl flex items-center justify-center">
                <i class="fas fa-triangle-exclamation text-lg"></i>
            </div>
            <span class="text-xs bg-slate-200 text-slate-800 px-2 py-1 rounded-full font-medium">Denda</span>
        </div>
        <p class="text-3xl font-bold text-slate-900 font-display"><?= $totalTerlambat ?></p>
        <p class="text-slate-400 text-sm mt-1">Terlambat · <?= formatRupiah($totalDenda) ?></p>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    <!-- Recent Transactions -->
    <div class="card xl:col-span-2 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-slate-900">Transaksi Terbaru</h2>
            <a href="transaksi.php" class="text-slate-500 text-sm font-medium hover:text-slate-900">Lihat semua →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-400 border-b border-slate-100">
                        <th class="pb-3 font-semibold">Anggota</th>
                        <th class="pb-3 font-semibold">Buku</th>
                        <th class="pb-3 font-semibold">Tgl Kembali</th>
                        <th class="pb-3 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($recentPinjam as $p):
                        $isLate = ($p['status'] === 'dipinjam' && $p['tanggal_kembali_rencana'] < date('Y-m-d'));
                        $displayStatus = $isLate ? 'terlambat' : $p['status'];
                        ?>
                        <tr class="table-row">
                            <td class="py-3">
                                <p class="font-medium text-slate-900"><?= htmlspecialchars($p['nama_siswa']) ?></p>
                                <p class="text-slate-400 text-xs"><?= $p['no_anggota'] ?></p>
                            </td>
                            <td class="py-3">
                                <p class="text-slate-600 truncate max-w-[140px]"><?= htmlspecialchars($p['judul_buku']) ?>
                                </p>
                                <p class="text-slate-400 text-xs"><?= $p['kode_buku'] ?></p>
                            </td>
                            <td class="py-3 text-slate-500"><?= formatTanggal($p['tanggal_kembali_rencana']) ?></td>
                            <td class="py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold badge-<?= $displayStatus ?>">
                                    <?= ucfirst($displayStatus) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentPinjam)): ?>
                        <tr>
                            <td colspan="4" class="py-8 text-center text-slate-400">Belum ada transaksi</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Stok Menipis -->
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-slate-900">Stok Menipis</h2>
            <a href="buku.php" class="text-slate-500 text-sm font-medium hover:text-slate-900">Kelola →</a>
        </div>
        <?php if (empty($stokMenipis)): ?>
            <div class="text-center py-8">
                <i class="fas fa-check-circle text-emerald-400 text-3xl mb-2"></i>
                <p class="text-slate-400 text-sm">Stok semua buku aman</p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($stokMenipis as $b): ?>
                    <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                        <div class="w-9 h-9 bg-slate-200 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-book text-slate-600 text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900 truncate"><?= htmlspecialchars($b['judul']) ?></p>
                            <p class="text-xs text-slate-400"><?= $b['kode_buku'] ?></p>
                        </div>
                        <span class="text-sm font-bold <?= $b['stok_tersedia'] == 0 ? 'text-slate-800' : 'text-slate-500' ?>">
                            <?= $b['stok_tersedia'] ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>