<?php
require '../api/koneksi.php';

// Menarik Data Master & Absensi
$stmtPengguna = $pdo->query("SELECT * FROM tb_pengguna ORDER BY id_pengguna DESC");
$pengguna = $stmtPengguna->fetchAll(PDO::FETCH_ASSOC);

$queryAbsensi = "SELECT a.id_absensi, p.nama_lengkap, a.waktu_deteksi 
                 FROM tb_absensi a 
                 JOIN tb_pengguna p ON a.id_pengguna = p.id_pengguna 
                 ORDER BY a.waktu_deteksi DESC LIMIT 20";
$absensi = $pdo->query($queryAbsensi)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Biometric System</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { display: flex; height: 100vh; background-color: #f4f7f6; overflow: hidden; }
        
        /* Sidebar Styles */
        .sidebar { width: 250px; background-color: #2c3e50; color: white; display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; font-size: 20px; font-weight: bold; text-align: center; background-color: #1a252f; border-bottom: 1px solid #34495e; }
        .nav-item { padding: 15px 20px; cursor: pointer; transition: 0.3s; border-bottom: 1px solid #34495e; display: flex; align-items: center; }
        .nav-item i { margin-right: 15px; width: 20px; text-align: center; }
        .nav-item:hover, .nav-item.active { background-color: #34495e; border-left: 4px solid #3498db; }
        
        /* Main Content Styles */
        .main-content { flex: 1; padding: 30px; overflow-y: auto; }
        .tab-content { display: none; animation: fadeIn 0.5s; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Card & UI Elements */
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-bottom: 20px; }
        h2 { color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #ecf0f1; }
        
        /* Table Styles */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background-color: #f8f9fa; color: #333; font-weight: 600; }
        .btn-delete { background-color: #e74c3c; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 13px; }
        .btn-delete:hover { background-color: #c82333; }
        
        /* Form & Button Styles */
        .form-control { padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        .btn-primary { background-color: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: bold; }
        .btn-primary:hover { background-color: #2980b9; }
        .btn-success { background-color: #27ae60; color: white; border: none; padding: 11px 20px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: bold; }
        .btn-success:hover { background-color: #219653; }
        .btn-primary:disabled { background-color: #bdc3c7; cursor: not-allowed; }

        /* Camera Enrollment Styles */
        .camera-container { display: flex; gap: 20px; align-items: flex-start; }
        .camera-box { flex: 1; background: #000; border-radius: 8px; overflow: hidden; position: relative; }
        #enrollVideo { width: 100%; height: auto; display: block; }
        .form-box { flex: 1; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e9ecef; }
        .input-full { width: 100%; margin: 10px 0 20px; font-size: 16px; }
        .btn-full { width: 100%; padding: 12px 20px; font-size: 16px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-fingerprint"></i> BioAdmin
        </div>
        <div class="nav-item active" onclick="switchTab('dashboard', this)">
            <i class="fas fa-chart-pie"></i> Analitik Absensi
        </div>
        <div class="nav-item" onclick="switchTab('users', this)">
            <i class="fas fa-users"></i> Data Pengguna
        </div>
        <div class="nav-item" onclick="switchTab('enroll', this)">
            <i class="fas fa-user-plus"></i> Enrollment Baru
        </div>
    </div>

    <div class="main-content">
        
        <div id="dashboard" class="tab-content active">
            
            <div class="card">
                <h2><i class="fas fa-project-diagram"></i> Clustering Volatilitas Kehadiran</h2>
                <p style="color: #7f8c8d; margin-bottom: 20px;">Pemetaan AI (K-Means) berdasarkan Rata-rata Kedatangan vs Volatilitas Keterlambatan.</p>
                <canvas id="clusterChart" height="100"></canvas>
            </div>

            <div class="card">
                <h2><i class="fas fa-file-export"></i> Ekspor Dokumen Kehadiran</h2>
                <p style="color: #7f8c8d; margin-bottom: 15px;">Pilih rentang tanggal untuk mencetak log kehadiran pengguna ke dalam format spreadsheet Excel.</p>
                <form action="export_excel.php" method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div>
                        <label style="font-size: 14px; font-weight: 600; color: #2c3e50;">Tanggal Mulai:</label><br>
                        <input type="date" name="tgl_mulai" class="form-control" style="margin-top: 5px; width: 220px;" required>
                    </div>
                    <div>
                        <label style="font-size: 14px; font-weight: 600; color: #2c3e50;">Tanggal Selesai:</label><br>
                        <input type="date" name="tgl_selesai" class="form-control" style="margin-top: 5px; width: 220px;" required>
                    </div>
                    <div>
                        <button type="submit" class="btn-success">
                            <i class="fas fa-file-excel"></i> Unduh Berkas .XLS
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <h2><i class="fas fa-history"></i> Riwayat Absensi Real-Time</h2>
                <table>
                    <thead>
                        <tr><th>ID Log</th><th>Nama Lengkap</th><th>Waktu Deteksi</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($absensi as $a): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($a['id_absensi']) ?></td>
                            <td><strong><?= htmlspecialchars($a['nama_lengkap']) ?></strong></td>
                            <td><?= htmlspecialchars($a['waktu_deteksi']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="users" class="tab-content">
            <div class="card">
                <h2><i class="fas fa-database"></i> Manajemen Master Data</h2>
                <table>
                    <thead>
                        <tr><th>ID</th><th>Nama Lengkap</th><th>Status Encoding</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($pengguna as $p): ?>
                        <tr>
                            <td>USR-<?= htmlspecialchars($p['id_pengguna']) ?></td>
                            <td><?= htmlspecialchars($p['nama_lengkap']) ?></td>
                            <td><span style="color: #27ae60;"><i class="fas fa-check-circle"></i> Tersimpan (128-D)</span></td>
                            <td>
                                <a href="hapus_pengguna.php?id=<?= $p['id_pengguna'] ?>" class="btn-delete" onclick="return confirm('Hapus data ini? Semua riwayat absennya juga akan terhapus.');"><i class="fas fa-trash"></i> Hapus</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="enroll" class="tab-content">
            <div class="card">
                <h2><i class="fas fa-camera"></i> Registrasi Biometrik Wajah</h2>
                <div class="camera-container">
                    <div class="camera-box">
                        <video id="enrollVideo" autoplay playsinline></video>
                        <canvas id="enrollCanvas" width="640" height="480" style="display:none;"></canvas>
                    </div>
                    <div class="form-box">
                        <p style="color: #7f8c8d; margin-bottom: 15px;">Pastikan wajah menghadap lurus ke kamera dan pencahayaan cukup sebelum mendaftar.</p>
                        <label><strong>Nama Lengkap:</strong></label>
                        <input type="text" id="inputNama" class="form-control input-full" placeholder="Masukkan nama pengguna baru" required>
                        
                        <button id="btnDaftar" class="btn-primary btn-full" onclick="daftarkanWajah()">
                            <i class="fas fa-scan"></i> Ekstrak & Simpan
                        </button>
                        <p id="enrollStatus" style="margin-top: 15px; font-weight: bold; color: #34495e;"></p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // --- 1. LOGIKA TAB NAVIGATION & CAMERA CONTROL ---
        function switchTab(tabId, element) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            element.classList.add('active');

            // Nyalakan kamera HANYA saat tab enroll dibuka, matikan jika pindah tab lain
            if(tabId === 'enroll') {
                startEnrollCamera();
            } else {
                stopEnrollCamera();
            }
        }

        // --- 2. LOGIKA GRAFIK CLUSTERING REAL-TIME (Chart.js) ---
        let myChart = null;

        function renderLiveClustering() {
            const canvasEl = document.getElementById('clusterChart');
            if (!canvasEl) return; // Mencegah fatal error jika canvas tidak ditemukan
            const ctx = canvasEl.getContext('2d');

            fetch('http://localhost:5000/get_analytics')
                .then(response => response.json())
                .then(res => {
                    if (res.status !== 'success') return;
                    
                    const clusterGroups = {};
                    const warnaCluster = ['#2ecc71', '#e74c3c', '#f1c40f'];

                    res.data.forEach(item => {
                        if (!clusterGroups[item.cluster]) {
                            clusterGroups[item.cluster] = {
                                label: `Kelompok Perilaku ${item.cluster + 1}`,
                                data: [],
                                backgroundColor: warnaCluster[item.cluster] || '#3498db',
                                pointRadius: 8,
                                hoverRadius: 10
                            };
                        }
                        clusterGroups[item.cluster].data.push({ x: item.x, y: item.y, namaUser: item.nama });
                    });

                    if (myChart) myChart.destroy();

                    myChart = new Chart(ctx, {
                        type: 'scatter',
                        data: { datasets: Object.values(clusterGroups) },
                        options: {
                            scales: {
                                x: { title: { display: true, text: 'Rata-rata Kedatangan (Jam Desimal)' }, min: 0, max: 24 },
                                y: { title: { display: true, text: 'Volatilitas Keterlambatan (Satuan Jam)' }, min: 0 }
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const p = context.raw;
                                            return `${p.namaUser} (Rata-rata: ${p.x.toFixed(2)}, Volatilitas: ${p.y.toFixed(2)} jam)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(err => console.error("Gagal sinkronisasi data analitik:", err));
        }

        // Eksekusi otomatis saat halaman dimuat pertama kali
        renderLiveClustering();

        // --- 3. LOGIKA WEB ENROLLMENT ---
        const video = document.getElementById('enrollVideo');
        const canvas = document.getElementById('enrollCanvas');
        const context = canvas.getContext('2d');
        let enrollStream = null;

        async function startEnrollCamera() {
            try {
                // Meminta akses kamera ke browser
                enrollStream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 } });
                video.srcObject = enrollStream;
            } catch (err) {
                Swal.fire('Error', 'Gagal mengakses kamera. Pastikan izin kamera aktif.', 'error');
            }
        }

        function stopEnrollCamera() {
            if (enrollStream) {
                enrollStream.getTracks().forEach(track => track.stop());
                video.srcObject = null;
                enrollStream = null;
            }
        }

        async function daftarkanWajah() {
            const nama = document.getElementById('inputNama').value;
            if (!nama) { Swal.fire('Peringatan', 'Nama tidak boleh kosong!', 'warning'); return; }
            if (!enrollStream) { Swal.fire('Peringatan', 'Kamera belum aktif!', 'warning'); return; }

            document.getElementById('btnDaftar').disabled = true;
            document.getElementById('enrollStatus').innerText = "Mengekstrak biometrik...";

            // Potret gambar dari video ke canvas
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageBase64 = canvas.toDataURL('image/jpeg', 0.9);

            try {
                const response = await fetch('http://localhost:5000/daftar_wajah', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nama: nama, image: imageBase64 })
                });

                const result = await response.json();

                if (result.status === 'success') {
                    Swal.fire('Berhasil!', result.pesan, 'success').then(() => {
                        window.location.reload(); 
                    });
                } else {
                    Swal.fire('Gagal', result.pesan, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'Gagal menghubungi Server AI Python. Pastikan server flask berjalan.', 'error');
            } finally {
                document.getElementById('btnDaftar').disabled = false;
                document.getElementById('enrollStatus').innerText = "";
            }
        }
    </script>
</body>
</html>