<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireAdmin();

$pageTitle = 'Laporan';

$bulan = $_GET['bulan'] ?? date('Y-m');

// Stats bulan ini
$totalPinjamBulan = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE DATE_FORMAT(tanggal_pinjam,'%Y-%m')=?");
$totalPinjamBulan->execute([$bulan]);
$totalPinjamBulan = $totalPinjamBulan->fetchColumn();

$totalKembaliBulan = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE status='dikembalikan' AND DATE_FORMAT(tanggal_kembali_aktual,'%Y-%m')=?");
$totalKembaliBulan->execute([$bulan]);
$totalKembaliBulan = $totalKembaliBulan->fetchColumn();

$dendaLunas = $pdo->prepare("SELECT COALESCE(SUM(denda),0) FROM peminjaman WHERE denda_terbayar=1 AND DATE_FORMAT(updated_at,'%Y-%m')=?");
$dendaLunas->execute([$bulan]);
$dendaLunas = $dendaLunas->fetchColumn();

// Buku terpopuler
$bukuPopuler = $pdo->query("SELECT b.judul, b.kode_buku, COUNT(p.id) AS jml FROM peminjaman p JOIN buku b ON p.buku_id=b.id GROUP BY p.buku_id ORDER BY jml DESC LIMIT 10")->fetchAll();

// Anggota paling aktif
$anggotaAktif = $pdo->query("SELECT u.nama, u.no_anggota, u.kelas, COUNT(p.id) AS jml FROM peminjaman p JOIN users u ON p.user_id=u.id GROUP BY p.user_id ORDER BY jml DESC LIMIT 10")->fetchAll();

// Peminjaman per bulan (6 bulan terakhir)
$trenData = $pdo->query("SELECT DATE_FORMAT(tanggal_pinjam,'%Y-%m') AS bulan, COUNT(*) AS jml FROM peminjaman WHERE tanggal_pinjam >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY bulan ORDER BY bulan")->fetchAll();

// Data peminjaman bulan dipilih
$laporanBulan = $pdo->prepare("
    SELECT p.*, u.nama AS nama_siswa, u.no_anggota, b.judul AS judul_buku
    FROM peminjaman p JOIN users u ON p.user_id=u.id JOIN buku b ON p.buku_id=b.id
    WHERE DATE_FORMAT(p.tanggal_pinjam,'%Y-%m')=?
    ORDER BY p.tanggal_pinjam DESC
");
$laporanBulan->execute([$bulan]);
$laporanBulanList = $laporanBulan->fetchAll();

require_once '../../includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900  ">Laporan Perpustakaan</h1>
        <p class="text-slate-400 text-sm mt-1">Ringkasan aktivitas perpustakaan</p>
    </div>
    <form method="GET" class="flex gap-2 items-center">
        <input type="month" name="bulan" value="<?= $bulan ?>"
            class="border border-gray-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary-200">
        <button type="submit" class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold">Filter</button>
    </form>
</div>

<!-- Stats -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="card p-5 text-center">
        <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-book-bookmark text-black text-lg"></i>
        </div>
        <p class="text-3xl font-bold text-slate-900"><?= $totalPinjamBulan ?></p>
        <p class="text-slate-400 text-sm mt-1">Peminjaman</p>
    </div>
    <div class="card p-5 text-center">
        <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-check-circle text-slate-600 text-lg"></i>
        </div>
        <p class="text-3xl font-bold text-slate-900"><?= $totalKembaliBulan ?></p>
        <p class="text-slate-400 text-sm mt-1">Pengembalian</p>
    </div>
    <div class="card p-5 text-center">
        <div class="w-12 h-12 bg-slate-200 rounded-xl flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-coins text-slate-500 text-lg"></i>
        </div>
        <p class="text-2xl font-bold text-slate-900"><?= formatRupiah($dendaLunas) ?></p>
        <p class="text-slate-400 text-sm mt-1">Denda Terkumpul</p>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-4">
    <!-- Buku Populer -->
    <div class="card p-6">
        <h2 class="font-semibold text-slate-900 mb-4">Buku Terpopuler</h2>
        <div class="space-y-3">
            <?php foreach ($bukuPopuler as $i => $b): ?>
                <div class="flex items-center gap-3">
                    <span
                        class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0
                    <?= $i === 0 ? 'bg-slate-200 text-slate-500' : ($i === 1 ? 'bg-gray-200 text-slate-500' : ($i === 2 ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-slate-400')) ?>">
                        <?= $i + 1 ?>
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-900 truncate"><?= htmlspecialchars($b['judul']) ?></p>
                            <span class="text-sm font-bold text-slate-500 ml-2 flex-shrink-0"><?= $b['jml'] ?>x</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full mt-1">
                            <div class="h-full bg-primary-400 rounded-full"
                                style="width:<?= min(100, ($b['jml'] / max(1, $bukuPopuler[0]['jml'])) * 100) ?>%"></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($bukuPopuler)): ?>
                <p class="text-slate-400 text-sm text-center py-4">Belum ada data</p><?php endif; ?>
        </div>
    </div>

    <!-- Anggota Aktif -->
    <div class="card p-6">
        <h2 class="font-semibold text-slate-900 mb-4">Anggota Paling Aktif</h2>
        <div class="space-y-3">
            <?php foreach ($anggotaAktif as $i => $a): ?>
                <div class="flex items-center gap-3">
                    <span
                        class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0
                    <?= $i === 0 ? 'bg-slate-200 text-slate-500' : ($i === 1 ? 'bg-gray-200 text-slate-500' : ($i === 2 ? 'bg-orange-100 text-orange-600' : 'bg-gray-100 text-slate-400')) ?>">
                        <?= $i + 1 ?>
                    </span>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-900"><?= htmlspecialchars($a['nama']) ?></p>
                                <p class="text-xs text-slate-400"><?= $a['kelas'] ?> · <?= $a['no_anggota'] ?></p>
                            </div>
                            <span class="text-sm font-bold text-slate-500"><?= $a['jml'] ?>x</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($anggotaAktif)): ?>
                <p class="text-slate-400 text-sm text-center py-4">Belum ada data</p><?php endif; ?>
        </div>
    </div>
</div>

<!-- Detail Transaksi Bulan Ini -->
<div class="card p-6">
    <h2 class="font-semibold text-slate-900 mb-4">Detail Transaksi — <?= date('F Y', strtotime($bulan . '-01')) ?></h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr class="text-left text-slate-400">
                    <th class="px-4 py-3 font-semibold">Kode</th>
                    <th class="px-4 py-3 font-semibold">Anggota</th>
                    <th class="px-4 py-3 font-semibold">Buku</th>
                    <th class="px-4 py-3 font-semibold">Tgl Pinjam</th>
                    <th class="px-4 py-3 font-semibold">Denda</th>
                    <th class="px-4 py-3 font-semibold text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($laporanBulanList as $l): ?>
                    <tr class="table-row">
                        <td class="px-4 py-2.5 text-xs font-mono text-slate-400"><?= $l['kode_pinjam'] ?></td>
                        <td class="px-4 py-2.5">
                            <p class="font-medium text-slate-900"><?= htmlspecialchars($l['nama_siswa']) ?></p>
                            <p class="text-slate-400 text-xs"><?= $l['no_anggota'] ?></p>
                        </td>
                        <td class="px-4 py-2.5 text-slate-600 text-xs"><?= htmlspecialchars($l['judul_buku']) ?></td>
                        <td class="px-4 py-2.5 text-slate-400 text-xs"><?= formatTanggal($l['tanggal_pinjam']) ?></td>
                        <td class="px-4 py-2.5 text-xs <?= $l['denda'] > 0 ? 'text-slate-800 font-semibold' : '' ?>">
                            <?= $l['denda'] > 0 ? formatRupiah($l['denda']) : '—' ?></td>
                        <td class="px-4 py-2.5 text-center"><span
                                class="px-2 py-0.5 rounded-full text-xs font-semibold badge-<?= $l['status'] ?>"><?= ucfirst($l['status']) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($laporanBulanList)): ?>
                    <tr>
                        <td colspan="6" class="py-8 text-center text-slate-400">Tidak ada transaksi bulan ini</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>