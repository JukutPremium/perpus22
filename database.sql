-- ============================================================
-- DATABASE: Perpustakaan Sekolah Digital
-- ============================================================

CREATE DATABASE IF NOT EXISTS perpustakaan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE perpustakaan;

-- ============================================================
-- TABLE: users (admin & siswa)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'siswa') NOT NULL DEFAULT 'siswa',
    no_anggota VARCHAR(20) UNIQUE,
    kelas VARCHAR(20),
    telepon VARCHAR(20),
    alamat TEXT,
    foto VARCHAR(255),
    status ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: buku
-- ============================================================
CREATE TABLE IF NOT EXISTS buku (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_buku VARCHAR(30) NOT NULL UNIQUE,
    judul VARCHAR(200) NOT NULL,
    pengarang VARCHAR(100) NOT NULL,
    penerbit VARCHAR(100),
    tahun_terbit YEAR,
    isbn VARCHAR(20),
    kategori VARCHAR(50),
    stok INT NOT NULL DEFAULT 0,
    stok_tersedia INT NOT NULL DEFAULT 0,
    deskripsi TEXT,
    cover VARCHAR(255),
    lokasi_rak VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: peminjaman
-- ============================================================
CREATE TABLE IF NOT EXISTS peminjaman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_pinjam VARCHAR(30) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    buku_id INT NOT NULL,
    tanggal_pinjam DATE NOT NULL,
    tanggal_kembali_rencana DATE NOT NULL,
    tanggal_kembali_aktual DATE,
    status ENUM('dipinjam', 'dikembalikan', 'terlambat') NOT NULL DEFAULT 'dipinjam',
    denda DECIMAL(10,2) DEFAULT 0.00,
    denda_terbayar TINYINT(1) DEFAULT 0,
    catatan TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (buku_id) REFERENCES buku(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- TRIGGER: Kurangi stok saat peminjaman
-- ============================================================
DELIMITER $$
CREATE TRIGGER after_peminjaman_insert
AFTER INSERT ON peminjaman
FOR EACH ROW
BEGIN
    UPDATE buku SET stok_tersedia = stok_tersedia - 1 WHERE id = NEW.buku_id;
END$$
DELIMITER ;

-- ============================================================
-- TRIGGER: Kembalikan stok saat pengembalian
-- ============================================================
DELIMITER $$
CREATE TRIGGER after_peminjaman_update
AFTER UPDATE ON peminjaman
FOR EACH ROW
BEGIN
    IF NEW.status = 'dikembalikan' AND OLD.status != 'dikembalikan' THEN
        UPDATE buku SET stok_tersedia = stok_tersedia + 1 WHERE id = NEW.buku_id;
    END IF;
END$$
DELIMITER ;

-- ============================================================
-- DATA AWAL: Admin
-- ============================================================
INSERT INTO users (nama, email, password, role, no_anggota, status) VALUES
('Administrator', 'admin@perpustakaan.sch.id', '$2a$10$eeLbbbCkw9YHc/oGy1U0dORGK715vBSxye0xCGYI5I2yvoTMJpJzO', 'admin', 'ADM-001', 'aktif'),
('Budi Santoso', '  ', '$2a$10$eeLbbbCkw9YHc/oGy1U0dORGK715vBSxye0xCGYI5I2yvoTMJpJzO', 'siswa', 'SIS-001', 'aktif'),
('Siti Rahma', 'siti@siswa.sch.id', '$2a$10$eeLbbbCkw9YHc/oGy1U0dORGK715vBSxye0xCGYI5I2yvoTMJpJzO', 'siswa', 'SIS-002', 'aktif');

-- UPDATE: Set kelas untuk siswa
UPDATE users SET kelas = 'XII RPL 1' WHERE email = 'budi@siswa.sch.id';
UPDATE users SET kelas = 'XI TKJ 2' WHERE email = 'siti@siswa.sch.id';

-- ============================================================
-- DATA AWAL: Buku
-- ============================================================
INSERT INTO buku (kode_buku, judul, pengarang, penerbit, tahun_terbit, isbn, kategori, stok, stok_tersedia, lokasi_rak) VALUES
('BK-001', 'Pemrograman Web dengan PHP', 'Ahmad Fauzi', 'Andi Publisher', 2022, '978-979-29-5123-4', 'Teknologi', 5, 5, 'A-01'),
('BK-002', 'Basis Data Relasional', 'Budi Raharjo', 'Informatika', 2021, '978-602-7514-12-3', 'Teknologi', 3, 3, 'A-02'),
('BK-003', 'Algoritma dan Struktur Data', 'Rinaldi Munir', 'Informatika', 2020, '978-602-7514-08-6', 'Teknologi', 4, 4, 'A-03'),
('BK-004', 'Matematika Diskrit', 'Rinaldi Munir', 'Informatika', 2019, '978-602-7514-05-5', 'Matematika', 2, 2, 'B-01'),
('BK-005', 'Bahasa Indonesia untuk SMK', 'Dewi Anggraini', 'Erlangga', 2021, '978-602-298-765-2', 'Bahasa', 6, 6, 'C-01');




USE perpustakaan;

CREATE TABLE IF NOT EXISTS pembayaran_denda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_bayar VARCHAR(30) NOT NULL UNIQUE,
    peminjaman_id INT NOT NULL,
    user_id INT NOT NULL,
    jumlah_denda DECIMAL(10,2) NOT NULL,
    jumlah_bayar DECIMAL(10,2) NOT NULL,
    kembalian DECIMAL(10,2) DEFAULT 0.00,
    metode ENUM('tunai','transfer') NOT NULL DEFAULT 'tunai',
    catatan TEXT,
    dibayar_oleh INT NOT NULL COMMENT 'admin_id yang memproses',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (peminjaman_id) REFERENCES peminjaman(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (dibayar_oleh) REFERENCES users(id)
) ENGINE=InnoDB;

