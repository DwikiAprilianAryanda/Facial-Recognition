<?php
require 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama_lengkap'] ?? '';
    $encoding = $_POST['encoding'] ?? '';

    if (!empty($nama) && !empty($encoding)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tb_pengguna (nama_lengkap, encoding_wajah) VALUES (:nama, :encoding)");
            $stmt->execute([
                ':nama' => $nama, 
                ':encoding' => $encoding
            ]);
            echo "Berhasil mendaftarkan wajah untuk: " . $nama;
        } catch (PDOException $e) {
            echo "Error Database: " . $e->getMessage();
        }
    } else {
        echo "Data tidak lengkap dari Python.";
    }
}
?>