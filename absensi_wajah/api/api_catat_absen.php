<?php
require 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pengguna = $_POST['id_pengguna'] ?? '';
    $nama = $_POST['nama_lengkap'] ?? '';

    if (!empty($id_pengguna)) {
        try {
            // Catat ke log absensi
            $stmt = $pdo->prepare("INSERT INTO tb_absensi (id_pengguna) VALUES (:id)");
            $stmt->execute([':id' => $id_pengguna]);

            // Update monitor agar dashboard tahu siapa yang baru saja absen
            $pesan = "Berhasil Absen: " . $nama;
            $stmtMonitor = $pdo->prepare("UPDATE tb_monitor SET status_pesan = :pesan WHERE id_monitor = 1");
            $stmtMonitor->execute([':pesan' => $pesan]);
            
            echo "Absen sukses.";
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}
?>