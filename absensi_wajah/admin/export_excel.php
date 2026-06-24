<?php
require '../api/koneksi.php';

$tgl_mulai = $_GET['tgl_mulai'] ?? '';
$tgl_selesai = $_GET['tgl_selesai'] ?? '';

if (!empty($tgl_mulai) && !empty($tgl_selesai)) {
    // Memaksa browser mengunduh berkas sebagai dokumen Excel asli
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Laporan_Absensi_Wajah_(" . $tgl_mulai . "_s.d_" . $tgl_selesai . ").xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    try {
        $query = "SELECT a.id_absensi, p.nama_lengkap, a.waktu_deteksi 
                  FROM tb_absensi a 
                  JOIN tb_pengguna p ON a.id_pengguna = p.id_pengguna 
                  WHERE DATE(a.waktu_deteksi) BETWEEN :mulai AND :selesai
                  ORDER BY a.waktu_deteksi ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':mulai' => $tgl_mulai, ':selesai' => $tgl_selesai]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>LAPORAN KEHADIRAN TERMINAL BIOMETRIK</h2>";
        echo "<p>Periode Laporan: <b>$tgl_mulai</b> sampai <b>$tgl_selesai</b></p>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr style='background-color:#007BFF; color:white; font-weight:bold;'>
                <th>ID Log</th>
                <th>Nama Lengkap</th>
                <th>Waktu Kehadiran (WIB)</th>
              </tr>";
        
        foreach ($results as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id_absensi']) . "</td>";
            echo "<td>" . htmlspecialchars($row['nama_lengkap']) . "</td>";
            echo "<td>" . htmlspecialchars($row['waktu_deteksi']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (PDOException $e) {
        echo "Gagal mengambil data laporan: " . $e->getMessage();
    }
} else {
    echo "Parameter tanggal ekspor laporan tidak valid.";
}
?>