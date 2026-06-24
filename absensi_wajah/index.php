<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kios Absensi Biometrik</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #e9ecef; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        
        .kiosk-container { display: flex; width: 100%; max-width: 1200px; background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; }
        
        /* Kiri: Area Scanner */
        .scanner-section { flex: 2; padding: 40px; display: flex; flex-direction: column; align-items: center; border-right: 1px solid #dee2e6; }
        .header-title { color: #2c3e50; font-size: 28px; margin-bottom: 5px; font-weight: 700; }
        .clock-display { font-size: 48px; font-weight: bold; color: #3498db; letter-spacing: 2px; margin-bottom: 20px; }
        .date-display { font-size: 18px; color: #7f8c8d; margin-bottom: 30px; }
        
        /* Frame Kamera dengan Animasi Scanner */
        .video-wrapper { position: relative; width: 480px; height: 360px; border-radius: 12px; overflow: hidden; background-color: #000; box-shadow: 0 8px 16px rgba(0,0,0,0.2); }
        video { width: 100%; height: 100%; object-fit: cover; }
        .scan-line { position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: rgba(46, 204, 113, 0.8); box-shadow: 0 0 15px rgba(46, 204, 113, 1); display: none; animation: scan 2s linear infinite; }
        @keyframes scan { 0% { top: 0; } 50% { top: 100%; } 100% { top: 0; } }
        
        .controls { margin-top: 30px; display: flex; gap: 15px; width: 100%; justify-content: center; flex-wrap: wrap; }
        button { padding: 15px 25px; font-size: 16px; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 10px; }
        .btn-start { background-color: #2c3e50; color: white; }
        .btn-start:hover { background-color: #1a252f; transform: translateY(-2px); }
        .btn-stop { background-color: #e74c3c; color: white; }
        .btn-stop:hover { background-color: #c0392b; transform: translateY(-2px); }
        .btn-scan { background-color: #2ecc71; color: white; }
        .btn-scan:hover { background-color: #27ae60; transform: translateY(-2px); }
        .btn-disabled { background-color: #bdc3c7 !important; cursor: not-allowed; transform: none !important; }
        
        .status-box { margin-top: 25px; padding: 15px 20px; border-radius: 8px; background-color: #f8f9fa; width: 100%; text-align: center; font-size: 16px; color: #2c3e50; font-weight: 500; }

        /* Kanan: Live Log Info */
        .info-section { flex: 1; padding: 40px; background-color: #f8f9fa; }
        .info-title { color: #2c3e50; font-size: 20px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; }
        .log-list { display: flex; flex-direction: column; gap: 15px; }
        .log-item { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; animation: slideIn 0.5s ease-out; }
        @keyframes slideIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
        .log-icon { width: 40px; height: 40px; border-radius: 50%; background-color: #e1f5fe; color: #0288d1; display: flex; justify-content: center; align-items: center; font-size: 18px; }
        .log-details strong { display: block; color: #2c3e50; font-size: 16px; margin-bottom: 3px; }
        .log-details span { color: #7f8c8d; font-size: 13px; }
    </style>
</head>
<body>

    <div class="kiosk-container">
        <div class="scanner-section">
            <h1 class="header-title"><i class="fas fa-fingerprint"></i> Terminal Absensi</h1>
            <div class="clock-display" id="clock">00:00:00</div>
            <div class="date-display" id="date">Memuat tanggal...</div>
            
            <div class="video-wrapper">
                <video id="videoElement" autoplay playsinline></video>
                <div class="scan-line" id="scanLine"></div>
                <canvas id="canvasElement" width="480" height="360" style="display:none;"></canvas>
            </div>
            
            <div class="controls">
                <button id="btnStart" class="btn-start" onclick="mulaiKamera()">
                    <i class="fas fa-video"></i> Aktifkan
                </button>
                <button id="btnStop" class="btn-stop btn-disabled" onclick="matikanKamera()" disabled>
                    <i class="fas fa-video-slash"></i> Matikan
                </button>
                <button id="btnAbsen" class="btn-scan btn-disabled" onclick="verifikasiAbsen()" disabled>
                    <i class="fas fa-expand"></i> Pindai Wajah
                </button>
            </div>
            
            <div class="status-box" id="status-teks">
                <i class="fas fa-info-circle"></i> Silakan aktifkan kamera untuk memulai absensi.
            </div>
        </div>

        <div class="info-section">
            <h2 class="info-title"><i class="fas fa-list-ul"></i> Kehadiran Terbaru</h2>
            <div class="log-list" id="liveLog">
                <div style="text-align:center; color:#7f8c8d; margin-top:20px;">Memuat data...</div>
            </div>
        </div>
    </div>

    <script>
        // --- 1. FUNGSI TEXT-TO-SPEECH (TTS) BROWSER ---
function putarSuara(teks) {
    // Mengecek apakah browser mendukung Web Speech API
    if ('speechSynthesis' in window) {
        // Hentikan suara yang mungkin sedang berjalan agar tidak bertumpuk
        window.speechSynthesis.cancel(); 

        const pesanSuara = new SpeechSynthesisUtterance(teks);
        pesanSuara.lang = 'id-ID'; // Mengatur bahasa ke Indonesia
        pesanSuara.rate = 0.95;    // Kecepatan bicara (sedikit diperlambat agar lebih jelas)
        pesanSuara.pitch = 1.0;    // Nada suara normal
        
        window.speechSynthesis.speak(pesanSuara);
    } else {
        console.warn("Browser Anda tidak mendukung fitur Text-to-Speech.");
    }
}

// --- 2. LOGIKA JAM & TANGGAL REAL-TIME ---
function updateClock() {
    const now = new Date();
    document.getElementById('clock').innerText = now.toLocaleTimeString('id-ID', { hour12: false });
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('date').innerText = now.toLocaleDateString('id-ID', options);
}
setInterval(updateClock, 1000);
updateClock();

// --- 3. LOGIKA PULL LIVE LOG ABSENSI ---
function fetchLiveLog() {
    fetch('api/api_log_terbaru.php')
        .then(response => response.json())
        .then(data => {
            const logContainer = document.getElementById('liveLog');
            logContainer.innerHTML = ''; 
            
            if (data.length === 0) {
                logContainer.innerHTML = '<div style="text-align:center; color:#7f8c8d;">Belum ada absensi hari ini.</div>';
                return;
            }

            data.forEach(item => {
                const waktu = item.waktu_deteksi.split(' ')[1]; 
                const html = `
                    <div class="log-item">
                        <div class="log-icon"><i class="fas fa-user-check"></i></div>
                        <div class="log-details">
                            <strong>${item.nama_lengkap}</strong>
                            <span><i class="far fa-clock"></i> ${waktu} WIB</span>
                        </div>
                    </div>
                `;
                logContainer.insertAdjacentHTML('beforeend', html);
            });
        })
        .catch(error => console.error('Error fetching logs:', error));
}
setInterval(fetchLiveLog, 3000);
fetchLiveLog();

// --- 4. LOGIKA KAMERA & LIVENESS DETECTION ---
const video = document.getElementById('videoElement');
const canvas = document.getElementById('canvasElement');
const ctx = canvas.getContext('2d');
const statusTeks = document.getElementById('status-teks');
const btnAbsen = document.getElementById('btnAbsen');
const btnStart = document.getElementById('btnStart');
const btnStop = document.getElementById('btnStop');
const scanLine = document.getElementById('scanLine');
let stream = null;

async function mulaiKamera() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: true });
        video.srcObject = stream;
        statusTeks.innerHTML = "<i class='fas fa-smile'></i> Kamera siap. Posisikan wajah di tengah bingkai dan klik 'Pindai Wajah'.";
        
        btnAbsen.classList.remove("btn-disabled");
        btnAbsen.disabled = false;
        btnStop.classList.remove("btn-disabled");
        btnStop.disabled = false;
        btnStart.classList.add("btn-disabled");
        btnStart.disabled = true;
    } catch (err) {
        statusTeks.innerHTML = "<i class='fas fa-exclamation-triangle' style='color:red;'></i> Gagal mengakses kamera: " + err.message;
    }
}

function matikanKamera() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        video.srcObject = null;
        stream = null;
        
        statusTeks.innerHTML = "<i class='fas fa-info-circle'></i> Kamera dimatikan untuk menghemat daya.";
        
        btnAbsen.classList.add("btn-disabled");
        btnAbsen.disabled = true;
        btnStop.classList.add("btn-disabled");
        btnStop.disabled = true;
        btnStart.classList.remove("btn-disabled");
        btnStart.disabled = false;
        scanLine.style.display = "none";
    }
}

async function verifikasiAbsen() {
    if (!stream) return;
    
    btnAbsen.disabled = true;
    btnAbsen.classList.add("btn-disabled");
    btnStop.disabled = true;
    btnStop.classList.add("btn-disabled");
    
    scanLine.style.display = "block";
    statusTeks.innerHTML = "<i class='fas fa-spinner fa-spin'></i> Memindai biometrik... Mohon berkedip secara alami. (5 Detik)";

    const numFrames = 10;
    const captureInterval = 500; 
    let imageSequence = [];

    for (let i = 0; i < numFrames; i++) {
        await new Promise(resolve => setTimeout(resolve, captureInterval));
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        imageSequence.push(canvas.toDataURL('image/jpeg', 0.7));
    }

    statusTeks.innerHTML = "<i class='fas fa-sync fa-spin'></i> Memverifikasi data ke server pusat...";

    try {
        let response = await fetch('http://localhost:5000/proses_wajah', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image_sequence: imageSequence })
        });
        let result = await response.json();
        
        if (result.status === 'success') {
            // Putar suara absensi berhasil
            putarSuara("Absensi berhasil. Selamat datang, " + result.nama);
            
            Swal.fire({
                title: 'Autentikasi Berhasil!',
                text: 'Selamat datang, ' + result.nama,
                icon: 'success',
                timer: 3000,
                showConfirmButton: false
            });
            statusTeks.innerHTML = `<i class='fas fa-check-circle' style='color:#2ecc71;'></i> <b>${result.nama}</b> berhasil absen.`;
            fetchLiveLog();
        } 
        else if (result.status === 'liveness_fail') {
            // Putar suara peringatan kedipan
            putarSuara("Autentikasi gagal. Kedipan mata tidak terdeteksi.");
            
            Swal.fire('Autentikasi Gagal', result.pesan, 'warning');
            statusTeks.innerHTML = `<i class='fas fa-times-circle' style='color:#e74c3c;'></i> ${result.pesan}`;
        }
        else if (result.status === 'cooldown') {
            // Putar suara peringatan cooldown
            putarSuara("Anda sudah absen. Silakan tunggu beberapa saat lagi.");
            
            Swal.fire({ title: 'Tunggu Sebentar', text: result.pesan, icon: 'info', timer: 2000, showConfirmButton: false });
            statusTeks.innerHTML = `<i class='fas fa-clock'></i> ${result.pesan}`;
        }
        else {
            putarSuara("Wajah tidak dikenali.");
            statusTeks.innerHTML = `<i class='fas fa-exclamation-circle' style='color:#f39c12;'></i> ${result.pesan}`;
        }
    } catch (error) {
        putarSuara("Terjadi kesalahan jaringan.");
        statusTeks.innerHTML = "<i class='fas fa-wifi' style='color:#e74c3c;'></i> Error: Kehilangan koneksi ke Server AI.";
    } finally {
        scanLine.style.display = "none";
        
        btnAbsen.disabled = false;
        btnAbsen.classList.remove("btn-disabled");
        btnStop.disabled = false;
        btnStop.classList.remove("btn-disabled");
    }
}
    </script>
</body>
</html>