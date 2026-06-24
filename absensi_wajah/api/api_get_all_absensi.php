<?php
require 'koneksi.php';

try {
    $query = "SELECT p.nama_lengkap, a.waktu_deteksi 
              FROM tb_absensi a 
              JOIN tb_pengguna p ON a.id_pengguna = p.id_pengguna";
    $stmt = $pdo->query($query);
    
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo json_encode([]);
}
?>