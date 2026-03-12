<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireSiswa();

$pageTitle = 'Denda Saya';
$uid = $user['id'];

// Auto-update denda
$pdo->exec("UPDATE peminjaman SET status='terlambat', denda=DATEDIFF(CURDATE(),tanggal_kembali_rencana)*" . DENDA_PER_HARI . " WHERE status='dipinjam' AND tanggal_kembali_rencana < CURDATE()");

// Denda aktif (belum lunas)
$stmtAktif = $pdo->prepare("
    SELECT p.*, b.judul, b.kode_buku, b.pengarang
    FROM peminjaman p
    JOIN buku b ON p.buku_id = b.id
    WHERE p.user_id = ? AND p.denda > 0 AND p.denda_terbayar = 0
    ORDER BY p.denda DESC
");
$stmtAktif->execute([$uid]);
$dendaAktif = $stmtAktif->fetchAll();

// Riwayat denda yang sudah lunas
$stmtLunas = $pdo->prepare("
    SELECT pd.*, p.kode_pinjam, p.tanggal_kembali_rencana, p.tanggal_kembali_aktual,
           b.judul, b.kode_buku, a.nama AS nama_admin
    FROM pembayaran_denda pd
    JOIN peminjaman p ON pd.peminjaman_id = p.id
    JOIN buku b       ON p.buku_id = b.id
    JOIN users a      ON pd.dibayar_oleh = a.id
    WHERE pd.user_id = ?
    ORDER BY pd.created_at DESC
");
$stmtLunas->execute([$uid]);
$dendaLunas = $stmtLunas->fetchAll();

$totalAktif = array_sum(array_column($dendaAktif, 'denda'));
$totalDibayar = array_sum(array_column($dendaLunas, 'jumlah_denda'));

require_once '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900  ">Denda Saya</h1>
    <p class="text-slate-400 text-sm mt-1">Informasi denda keterlambatan pengembalian buku</p>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="card p-5 <?= $totalAktif > 0 ? 'border-2 border-neutral-300' : '' ?>">
        <div class="flex items-center gap-3 mb-2">
            <div
                class="w-10 h-10 <?= $totalAktif > 0 ? 'bg-slate-200' : 'bg-gray-100' ?> rounded-xl flex items-center justify-center">
                <i class="fas fa-circle-exclamation <?= $totalAktif > 0 ? 'text-slate-600' : 'text-slate-400' ?>"></i>
            </div>
            <p class="text-slate-400 text-sm">Denda Aktif</p>
        </div>
        <p class="text-2xl font-bold <?= $totalAktif > 0 ? 'text-slate-800' : 'text-slate-900' ?>">
            <?= formatRupiah($totalAktif) ?></p>
        <p class="text-xs text-slate-400 mt-0.5"><?= count($dendaAktif) ?> transaksi belum lunas</p>
    </div>
    <div class="card p-5">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-circle-check text-slate-500"></i>
            </div>
            <p class="text-slate-400 text-sm">Total Dibayar</p>
        </div>
        <p class="text-2xl font-bold text-slate-900"><?= formatRupiah($totalDibayar) ?></p>
        <p class="text-xs text-slate-400 mt-0.5"><?= count($dendaLunas) ?> transaksi lunas</p>
    </div>
    <div class="card p-5">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-slate-200 rounded-xl flex items-center justify-center">
                <i class="fas fa-calendar-day text-amber-500"></i>
            </div>
            <p class="text-slate-400 text-sm">Denda Per Hari</p>
        </div>
        <p class="text-2xl font-bold text-slate-900"><?= formatRupiah(DENDA_PER_HARI) ?></p>
        <p class="text-xs text-slate-400 mt-0.5">Per hari keterlambatan</p>
    </div>
</div>

<!-- Denda Aktif -->
<?php if (!empty($dendaAktif)): ?>
    <div class="card p-6 mb-4 border-2 border-red-100">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-2 h-2 bg-slate-1000 rounded-full animate-pulse"></div>
            <h2 class="font-semibold text-slate-900">Denda Belum Lunas</h2>
            <span
                class="bg-slate-200 text-slate-800 text-xs font-bold px-2 py-0.5 rounded-full ml-auto"><?= formatRupiah($totalAktif) ?></span>
        </div>

        <div class="space-y-3">
            <?php foreach ($dendaAktif as $d):
                $hariTerlambat = (new DateTime())->diff(new DateTime($d['tanggal_kembali_rencana']))->days;
                ?>
                <div class="flex items-center gap-4 p-4 bg-slate-100 rounded-xl border border-red-100">
                    <div class="w-11 h-11 bg-slate-200 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-book text-slate-600"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-slate-900 truncate"><?= htmlspecialchars($d['judul']) ?></p>
                        <p class="text-slate-400 text-xs mt-0.5"><?= $d['kode_buku'] ?> · <?= $d['kode_pinjam'] ?></p>
                        <div class="flex items-center gap-3 mt-1.5 text-xs">
                            <span class="text-slate-800"><i class="fas fa-calendar-xmark mr-1"></i>Jatuh tempo:
                                <?= formatTanggal($d['tanggal_kembali_rencana']) ?></span>
                            <span class="bg-red-200 text-slate-800 px-2 py-0.5 rounded-full font-semibold"><?= $hariTerlambat ?>
                                hari terlambat</span>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-slate-800 font-bold text-lg"><?= formatRupiah($d['denda']) ?></p>
                        <p class="text-slate-400 text-xs mt-0.5">Hubungi petugas</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 pt-4 border-t border-red-100">
            <div class="flex items-start gap-3 text-sm text-slate-800 bg-slate-100 rounded-xl p-3">
                <i class="fas fa-info-circle mt-0.5 flex-shrink-0"></i>
                <p>Segera lunasi denda ke petugas perpustakaan. Selama denda belum lunas, kamu <strong>tidak bisa meminjam
                        buku baru</strong>. Denda terus bertambah <?= formatRupiah(DENDA_PER_HARI) ?>/hari hingga buku
                    dikembalikan.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card p-8 text-center mb-4 border-2 border-emerald-100">
        <i class="fas fa-circle-check text-5xl text-emerald-400 mb-3 block"></i>
        <h3 class="font-semibold text-slate-900 text-lg">Tidak Ada Denda Aktif</h3>
        <p class="text-slate-400 text-sm mt-1">Kamu tidak memiliki denda yang harus dibayar.</p>
    </div>
<?php endif; ?>

<!-- Riwayat Lunas -->
<div class="card p-6">
    <h2 class="font-semibold text-slate-900 mb-4">Riwayat Pembayaran Denda</h2>
    <?php if (empty($dendaLunas)): ?>
        <div class="text-center py-8 text-slate-400">
            <i class="fas fa-receipt text-3xl mb-2 block opacity-30"></i>
            <p class="text-sm">Belum ada riwayat pembayaran denda</p>
        </div>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($dendaLunas as $d): ?>
                <div class="flex items-center gap-4 p-4 bg-slate-50 rounded-xl hover:bg-gray-100 transition-colors">
                    <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-receipt text-slate-600 text-sm"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span
                                class="font-mono text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-semibold"><?= $d['kode_bayar'] ?></span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium
                        <?= $d['metode'] === 'tunai' ? 'bg-slate-200 text-slate-600' : 'bg-blue-100 text-blue-700' ?>">
                                <?= ucfirst($d['metode']) ?>
                            </span>
                        </div>
                        <p class="text-slate-600 text-sm font-medium mt-0.5 truncate"><?= htmlspecialchars($d['judul']) ?></p>
                        <p class="text-slate-400 text-xs"><?= $d['kode_pinjam'] ?> · Diproses oleh:
                            <?= htmlspecialchars($d['nama_admin']) ?></p>
                        <p class="text-slate-400 text-xs"><?= date('d M Y H:i', strtotime($d['created_at'])) ?></p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-slate-400 text-xs line-through"><?= formatRupiah($d['jumlah_denda']) ?></p>
                        <p class="text-slate-600 font-bold">Lunas ✓</p>
                        <?php if ($d['kembalian'] > 0): ?>
                            <p class="text-xs text-slate-400">Kembalian: <?= formatRupiah($d['kembalian']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>