<?php
// ============================================================
// Session & Auth Helper
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isSiswa() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'siswa';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /perpustakaan/index.php?msg=login_required');
        exit;
    }
    // Make $user available globally in the calling script
    $GLOBALS['user'] = getCurrentUser();
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /perpustakaan/pages/siswa/dashboard.php');
        exit;
    }
    $GLOBALS['user'] = getCurrentUser();
}

function requireSiswa() {
    requireLogin();
    if (!isSiswa()) {
        header('Location: /perpustakaan/pages/admin/dashboard.php');
        exit;
    }
    $GLOBALS['user'] = getCurrentUser();
}

function getCurrentUser() {
    return [
        'id'         => $_SESSION['user_id'] ?? null,
        'nama'       => $_SESSION['nama'] ?? '',
        'email'      => $_SESSION['email'] ?? '',
        'role'       => $_SESSION['role'] ?? '',
        'no_anggota' => $_SESSION['no_anggota'] ?? '',
    ];
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function formatTanggal($date) {
    if (!$date) return '-';
    $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
               'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $parts = explode('-', $date);
    return $parts[2] . ' ' . $months[(int)$parts[1]] . ' ' . $parts[0];
}

function hitungDenda($tanggal_kembali_rencana) {
    $today = new DateTime();
    $rencana = new DateTime($tanggal_kembali_rencana);
    if ($today > $rencana) {
        $diff = $today->diff($rencana)->days;
        return $diff * DENDA_PER_HARI;
    }
    return 0;
}

// Cek apakah user bisa dihapus (tidak ada peminjaman aktif / denda belum bayar)
function canDeleteUser($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM peminjaman 
        WHERE user_id = ? AND (status = 'dipinjam' OR status = 'terlambat' OR (denda > 0 AND denda_terbayar = 0))
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() == 0;
}
