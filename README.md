# Facial-Recognition

## Query Database
```
CREATE DATABASE db_absensi_wajah;
USE db_absensi_wajah;

CREATE TABLE tb_pengguna (
    id_pengguna INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(100) NOT NULL,
    encoding_wajah TEXT NOT NULL
);

CREATE TABLE tb_absensi (
    id_absensi INT AUTO_INCREMENT PRIMARY KEY,
    id_pengguna INT NOT NULL,
    waktu_deteksi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pengguna) REFERENCES tb_pengguna(id_pengguna) ON DELETE CASCADE
);

CREATE TABLE tb_monitor (
    id_monitor INT PRIMARY KEY,
    status_pesan VARCHAR(100) NOT NULL
);

-- Memasukkan baris data awal untuk dipantau oleh AJAX
INSERT INTO tb_monitor (id_monitor, status_pesan) VALUES (1, 'Siap');tb_absensi
```
