<?php
// Ubah dari require 'api/koneksi.php'; menjadi:
require '../api/koneksi.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM tb_pengguna WHERE id_pengguna = :id");
        $stmt->execute([':id' => $id]);
        
        // Redirect kembali ke halaman admin setelah berhasil
        echo "<script>alert('Data pengguna berhasil dihapus!'); window.location.href='admin.php';</script>";
    } catch (PDOException $e) {
        echo "Gagal menghapus data: " . $e->getMessage();
    }
} else {
    header("Location: admin.php");
    exit;
}
?>