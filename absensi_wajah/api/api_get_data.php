<?php
require 'koneksi.php';

try {
    $stmt = $pdo->query("SELECT id_pengguna, nama_lengkap, encoding_wajah FROM tb_pengguna");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kirim data dalam format JSON ke Python
    header('Content-Type: application/json');
    echo json_encode($data);
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>