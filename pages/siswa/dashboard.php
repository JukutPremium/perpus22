<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireSiswa();

$pageTitle = 'Dashboard Siswa';
$uid = $user['id'];

// Update denda terlambat
$pdo->exec("UPDATE peminjaman SET status='terlambat', denda=DATEDIFF(CURDATE(),tanggal_kembali_rencana)*" . DENDA_PER_HARI . " WHERE status='dipinjam' AND tanggal_kembali_rencana < CURDATE()");

$pinjamAktif = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE user_id=? AND status IN ('dipinjam','terlambat')");
$pinjamAktif->execute([$uid]);
$pinjamAktif = $pinjamAktif->fetchColumn();

$totalPinjam = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE user_id=?");
$totalPinjam->execute([$uid]);
$totalPinjam = $totalPinjam->fetchColumn();

$dendaBelumBayar = $pdo->prepare("SELECT COALESCE(SUM(denda),0) FROM peminjaman WHERE user_id=? AND denda>0 AND denda_terbayar=0");
$dendaBelumBayar->execute([$uid]);
$dendaBelumBayar = $dendaBelumBayar->fetchColumn();

// Pinjaman aktif detail
$pinjamList = $pdo->prepare("SELECT p.*, b.judul, b.kode_buku, b.pengarang FROM peminjaman p JOIN buku b ON p.buku_id=b.id WHERE p.user_id=? AND p.status IN ('dipinjam','terlambat') ORDER BY p.tanggal_kembali_rencana");
$pinjamList->execute([$uid]);
$pinjamList = $pinjamList->fetchAll();

require_once '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900  ">Halo, <?= htmlspecialchars(explode(' ', $user['nama'])[0]) ?>! 👋
    </h1>
    <p class="text-slate-400 text-sm mt-1">Selamat datang di Perpustakaan Digital <?= APP_SCHOOL ?></p>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="card p-5">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-book-bookmark text-black"></i>
            </div>
            <p class="text-slate-400 text-sm">Sedang Dipinjam</p>
        </div>
        <p class="text-3xl font-bold text-slate-900"><?= $pinjamAktif ?> <span
                class="text-base font-normal text-slate-400">/ <?= MAX_PINJAM ?></span></p>
    </div>
    <div class="card p-5">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-history text-slate-600"></i>
            </div>
            <p class="text-slate-400 text-sm">Total Pinjaman</p>
        </div>
        <p class="text-3xl font-bold text-slate-900"><?= $totalPinjam ?></p>
    </div>
    <div class="card p-5">
        <div class="flex items-center gap-3 mb-2">
            <div
                class="w-10 h-10 <?= $dendaBelumBayar > 0 ? 'bg-slate-200' : 'bg-gray-100' ?> rounded-xl flex items-center justify-center">
                <i class="fas fa-coins <?= $dendaBelumBayar > 0 ? 'text-slate-600' : 'text-slate-400' ?>"></i>
            </div>
            <p class="text-slate-400 text-sm">Denda Belum Bayar</p>
        </div>
        <p class="text-2xl font-bold <?= $dendaBelumBayar > 0 ? 'text-slate-800' : 'text-slate-900' ?>">
            <?= formatRupiah($dendaBelumBayar) ?></p>
    </div>
</div>

<!-- Peminjaman Aktif -->
<div class="card p-6 mb-4">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-semibold text-slate-900">Buku yang Sedang Dipinjam</h2>
        <a href="peminjaman.php" class="text-black text-sm hover:underline">Lihat semua →</a>
    </div>
    <?php if (empty($pinjamList)): ?>
        <div class="text-center py-10">
            <i class="fas fa-book-open text-4xl text-gray-200 mb-3 block"></i>
            <p class="text-slate-400">Kamu belum meminjam buku apapun.</p>
            <a href="katalog.php" class="inline-block mt-3 btn-primary px-4 py-2 rounded-xl text-sm font-semibold">Cari
                Buku</a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <?php foreach ($pinjamList as $p):
                $isLate = $p['tanggal_kembali_rencana'] < date('Y-m-d');
                $daysLeft = (new DateTime())->diff(new DateTime($p['tanggal_kembali_rencana']))->days;
                ?>
                <div class="border border-slate-100 rounded-xl p-4 <?= $isLate ? 'border-neutral-300 bg-slate-100' : '' ?>">
                    <div class="flex items-start gap-3 mb-3">
                        <div
                            class="w-10 h-10 <?= $isLate ? 'bg-slate-200' : 'bg-slate-100' ?> rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-book <?= $isLate ? 'text-slate-600' : 'text-black' ?>"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900 text-sm leading-tight"><?= htmlspecialchars($p['judul']) ?>
                            </p>
                            <p class="text-slate-400 text-xs mt-0.5"><?= htmlspecialchars($p['pengarang']) ?></p>
                        </div>
                    </div>
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Dipinjam</span>
                            <span class="text-slate-600"><?= formatTanggal($p['tanggal_pinjam']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Jatuh Tempo</span>
                            <span
                                class="<?= $isLate ? 'text-slate-800 font-bold' : '' ?>"><?= formatTanggal($p['tanggal_kembali_rencana']) ?></span>
                        </div>
                        <?php if ($isLate): ?>
                            <div class="flex justify-between mt-2 pt-2 border-t border-neutral-300">
                                <span class="text-slate-600 font-medium">Terlambat <?= $daysLeft ?> hari</span>
                                <span class="text-slate-800 font-bold"><?= formatRupiah($p['denda']) ?></span>
                            </div>
                        <?php else: ?>
                            <p class="text-slate-600 text-xs mt-1 font-medium"><i class="fas fa-clock mr-1"></i><?= $daysLeft ?>
                                hari lagi</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>