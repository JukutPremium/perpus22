<?php
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireSiswa();

$pageTitle = 'Profil Saya';
$uid = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama']);
    $telepon = trim($_POST['telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordLama = $_POST['password_lama'] ?? '';

    // Verifikasi password lama jika ingin ganti password
    if (!empty($password)) {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $stmt->execute([$uid]);
        $row = $stmt->fetch();
        if (!password_verify($passwordLama, $row['password'])) {
            setFlash('error', 'Password lama tidak sesuai.');
            header('Location: profil.php');
            exit;
        }
        if (strlen($password) < 6) {
            setFlash('error', 'Password baru minimal 6 karakter.');
            header('Location: profil.php');
            exit;
        }
    }

    $sql = "UPDATE users SET nama=?,telepon=?,alamat=?";
    $params = [$nama, $telepon, $alamat];
    if (!empty($password)) {
        $sql .= ",password=?";
        $params[] = password_hash($password, PASSWORD_BCRYPT);
    }
    $sql .= " WHERE id=?";
    $params[] = $uid;
    $pdo->prepare($sql)->execute($params);

    // Update session
    $_SESSION['nama'] = $nama;
    setFlash('success', 'Profil berhasil diperbarui.');
    header('Location: profil.php');
    exit;
}

$profil = $pdo->prepare("SELECT * FROM users WHERE id=?");
$profil->execute([$uid]);
$profil = $profil->fetch();

$totalPinjam = $pdo->prepare("SELECT COUNT(*) FROM peminjaman WHERE user_id=?");
$totalPinjam->execute([$uid]);
$totalPinjam = $totalPinjam->fetchColumn();

$totalDenda = $pdo->prepare("SELECT COALESCE(SUM(denda),0) FROM peminjaman WHERE user_id=? AND denda_terbayar=0 AND denda>0");
$totalDenda->execute([$uid]);
$totalDenda = $totalDenda->fetchColumn();

require_once '../../includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900  ">Profil Saya</h1>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
    <!-- Card Profil -->
    <div class="card p-6 text-center">
        <div
            class="w-20 h-20 rounded-full bg-gradient-to-br from-primary-400 to-primary-700 flex items-center justify-center text-white text-3xl font-bold mx-auto mb-3">
            <?= strtoupper(substr($profil['nama'], 0, 1)) ?>
        </div>
        <h2 class="font-bold text-slate-900 text-lg"><?= htmlspecialchars($profil['nama']) ?></h2>
        <p class="text-slate-400 text-sm"><?= htmlspecialchars($profil['email']) ?></p>
        <span
            class="inline-block mt-2 bg-slate-100 text-slate-400 text-xs px-3 py-1 rounded-full font-semibold"><?= $profil['no_anggota'] ?></span>
        <?php if ($profil['kelas']): ?>
            <p class="text-slate-400 text-sm mt-1"><?= htmlspecialchars($profil['kelas']) ?></p>
        <?php endif; ?>

        <div class="mt-5 pt-5 border-t border-slate-100 grid grid-cols-2 gap-3">
            <div class="bg-slate-50 rounded-xl p-3">
                <p class="text-2xl font-bold text-slate-900"><?= $totalPinjam ?></p>
                <p class="text-slate-400 text-xs mt-0.5">Total Pinjaman</p>
            </div>
            <div class="bg-<?= $totalDenda > 0 ? 'red' : 'gray' ?>-50 rounded-xl p-3">
                <p class="text-lg font-bold <?= $totalDenda > 0 ? 'text-slate-800' : 'text-slate-900' ?>">
                    <?= formatRupiah($totalDenda) ?></p>
                <p class="text-slate-400 text-xs mt-0.5">Denda Aktif</p>
            </div>
        </div>
    </div>

    <!-- Form Edit -->
    <div class="card p-6 xl:col-span-2">
        <h3 class="font-semibold text-slate-900 mb-5">Edit Informasi Profil</h3>
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">Nama Lengkap</label>
                    <input type="text" name="nama" required value="<?= htmlspecialchars($profil['nama']) ?>"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-600 block mb-1">No. Telepon</label>
                    <input type="text" name="telepon" value="<?= htmlspecialchars($profil['telepon'] ?? '') ?>"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                </div>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-600 block mb-1">Alamat</label>
                <textarea name="alamat" rows="2"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200 resize-none"><?= htmlspecialchars($profil['alamat'] ?? '') ?></textarea>
            </div>
            <div class="border-t border-slate-100 pt-4">
                <p class="text-sm font-semibold text-slate-600 mb-3">Ganti Password <span
                        class="font-normal text-slate-400">(kosongkan jika tidak ingin mengubah)</span></p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-slate-600 block mb-1">Password Lama</label>
                        <input type="password" name="password_lama"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-600 block mb-1">Password Baru</label>
                        <input type="password" name="password"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-primary-200">
                    </div>
                </div>
            </div>
            <div class="flex justify-end pt-2">
                <button type="submit" class="btn-primary px-6 py-2.5 rounded-xl text-sm font-semibold">
                    <i class="fas fa-save mr-2"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>