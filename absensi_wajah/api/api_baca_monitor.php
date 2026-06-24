<?php
require 'koneksi.php';

try {
    $stmt = $pdo->query("SELECT status_pesan FROM tb_monitor WHERE id_monitor = 1");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($data);
} catch (PDOException $e) {
    echo json_encode(["status_pesan" => "Error Database"]);
}
?>