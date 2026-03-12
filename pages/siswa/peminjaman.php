<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireSiswa();

$pageTitle = 'Riwayat Peminjaman';
$uid = $user['id'];

// Update denda
$pdo->exec("UPDATE peminjaman SET status='terlambat', denda=DATEDIFF(CURDATE(),tanggal_kembali_rencana)*" . DENDA_PER_HARI . " WHERE status='dipinjam' AND tanggal_kembali_rencana < CURDATE()");

$statusFilter = $_GET['status'] ?? '';
$where = "p.user_id=?";
$params = [$uid];
if ($statusFilter) {
    $where .= " AND p.status=?";
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("
    SELECT p.*, b.judul, b.kode_buku, b.pengarang, b.penerbit
    FROM peminjaman p JOIN buku b ON p.buku_id=b.id
    WHERE $where ORDER BY p.created_at DESC
");
$stmt->execute($params);
$pinjamList = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900  ">Riwayat Peminjaman</h1>
        <p class="text-slate-400 text-sm mt-1">Semua riwayat peminjaman bukumu</p>
    </div>
    <div class="flex gap-2">
        <?php
        $statuses = ['' => 'Semua', 'dipinjam' => 'Dipinjam', 'terlambat' => 'Terlambat', 'dikembalikan' => 'Dikembalikan'];
        foreach ($statuses as $val => $lbl):
            ?>
            <a href="?status=<?= $val ?>" class="px-3 py-2 rounded-xl text-xs font-semibold transition-all
            <?= $statusFilter === $val ? 'btn-primary' : 'border border-gray-200 text-slate-500 hover:bg-slate-50' ?>">
                <?= $lbl ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (empty($pinjamList)): ?>
    <div class="card p-16 text-center">
        <i class="fas fa-book-open text-5xl text-gray-200 mb-4 block"></i>
        <p class="text-slate-400">Belum ada riwayat peminjaman.</p>
        <a href="katalog.php" class="inline-block mt-4 btn-primary px-4 py-2 rounded-xl text-sm font-semibold">Jelajahi
            Katalog</a>
    </div>
<?php else: ?>
    <div class="space-y-3">
        <?php foreach ($pinjamList as $p):
            $isLate = ($p['status'] !== 'dikembalikan') && $p['tanggal_kembali_rencana'] < date('Y-m-d');
            ?>
            <div class="card p-5 flex items-center gap-4 <?= $isLate ? 'border border-neutral-300' : '' ?>">
                <!-- Icon -->
                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0
            <?= $p['status'] === 'dikembalikan' ? 'bg-slate-100' : ($isLate ? 'bg-slate-200' : 'bg-slate-200') ?>">
                    <i
                        class="fas <?= $p['status'] === 'dikembalikan' ? 'fa-check text-slate-600' : ($isLate ? 'fa-exclamation text-slate-800' : 'fa-book text-slate-500') ?>"></i>
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-slate-900 truncate"><?= htmlspecialchars($p['judul']) ?></p>
                    <p class="text-slate-400 text-xs mt-0.5"><?= htmlspecialchars($p['pengarang']) ?>
                        <?= $p['penerbit'] ? '· ' . $p['penerbit'] : '' ?></p>
                    <div class="flex items-center gap-4 mt-1.5 text-xs text-slate-400">
                        <span><i class="fas fa-calendar-check mr-1"></i><?= formatTanggal($p['tanggal_pinjam']) ?></span>
                        <span class="<?= $isLate ? 'text-slate-800 font-semibold' : '' ?>"><i
                                class="fas fa-calendar-xmark mr-1"></i><?= formatTanggal($p['tanggal_kembali_rencana']) ?></span>
                        <?php if ($p['tanggal_kembali_aktual']): ?>
                            <span class="text-slate-600"><i
                                    class="fas fa-rotate-left mr-1"></i><?= formatTanggal($p['tanggal_kembali_aktual']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status & Denda -->
                <div class="flex-shrink-0 text-right">
                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold badge-<?= $p['status'] ?>">
                        <?= $isLate ? 'Terlambat' : ucfirst($p['status']) ?>
                    </span>
                    <?php if ($p['denda'] > 0): ?>
                        <p class="text-xs mt-1.5 <?= $p['denda_terbayar'] ? 'text-slate-600' : 'text-slate-800 font-bold' ?>">
                            <?= formatRupiah($p['denda']) ?>
                            <?= $p['denda_terbayar'] ? ' ✓' : ' (Belum Lunas)' ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>