<?php
require 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pesan = $_POST['status_pesan'] ?? 'Siap';
    
    try {
        $stmt = $pdo->prepare("UPDATE tb_monitor SET status_pesan = :pesan WHERE id_monitor = 1");
        $stmt->execute([':pesan' => $pesan]);
        echo "Status diupdate.";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>