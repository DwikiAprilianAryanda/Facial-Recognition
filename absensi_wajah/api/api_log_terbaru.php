<?php
require 'koneksi.php';

try {
    // Mengambil 5 data absensi paling baru
    $query = "SELECT p.nama_lengkap, a.waktu_deteksi 
              FROM tb_absensi a 
              JOIN tb_pengguna p ON a.id_pengguna = p.id_pengguna 
              ORDER BY a.waktu_deteksi DESC LIMIT 5";
    $stmt = $pdo->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($data);
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>