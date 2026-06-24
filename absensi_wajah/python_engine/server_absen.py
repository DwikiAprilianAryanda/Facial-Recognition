from flask import Flask, request, jsonify
from flask_cors import CORS
import face_recognition
import numpy as np
import base64
import cv2
import time
import requests
import json
from scipy.spatial import distance as dist
from sklearn.cluster import KMeans
import datetime

app = Flask(__name__)
CORS(app)

# --- KONFIGURASI LARAGON ---
BASE_URL = "http://localhost/absensi_wajah/api"
API_GET = f"{BASE_URL}/api_get_data.php"
API_ABSEN = f"{BASE_URL}/api_catat_absen.php"

# --- AMBIL DATA DARI DATABASE (Sama) ---
print("Menarik data wajah dari database...")
try:
    response = requests.get(API_GET)
    users_db = response.json()
except Exception as e:
    print("Gagal menghubungi Laragon:", e)
    exit()

known_encodings = []
known_ids = []
known_names = []

for user in users_db:
    encoding_array = np.array(json.loads(user['encoding_wajah']))
    known_encodings.append(encoding_array)
    known_ids.append(user['id_pengguna'])
    known_names.append(user['nama_lengkap'])

log_cooldown = {}
COOLDOWN_TIME = 60 # 1 Menit

# --- AMBANG BATAS & KONFIGURASI LIVENESS (LIVENESS SETTINGS) ---
EAR_THRESHOLD = 0.22  # Di bawah ini dianggap mata tertutup
BLINK_CONSEC_FRAMES = 1 # Minimal satu frame dalam rangkaian dianggap tertutup

def muat_data_database():
    """Fungsi untuk menarik ulang data dari MySQL"""
    global known_encodings, known_ids, known_names
    known_encodings.clear()
    known_ids.clear()
    known_names.clear()
    
    try:
        response = requests.get(API_GET)
        users_db = response.json()
        for user in users_db:
            encoding_array = np.array(json.loads(user['encoding_wajah']))
            known_encodings.append(encoding_array)
            known_ids.append(user['id_pengguna'])
            known_names.append(user['nama_lengkap'])
        print(f"Database di-reload: {len(known_names)} wajah aktif.")
    except Exception as e:
        print("Gagal memuat database:", e)

# Panggil saat server pertama kali menyala
muat_data_database()

def calculate_ear(eye):
    """Fungsi menghitung Eye Aspect Ratio (EAR) menggunakan Euclidean distance"""
    # Jarak vertikal (Vertical distances)
    A = dist.euclidean(eye[1], eye[5])
    B = dist.euclidean(eye[2], eye[4])
    # Jarak horizontal (Horizontal distance)
    C = dist.euclidean(eye[0], eye[3])
    # Hitung rasio (EAR formula)
    ear = (A + B) / (2.0 * C)
    return ear

# --- ENDPOINT UTAMA DIUBAH MENERIMA RANGKAIAN GAMBAR (SEQEUNCE) ---
@app.route('/proses_wajah', methods=['POST'])
def proses_wajah():
    data = request.json
    if 'image_sequence' not in data:
        return jsonify({"status": "error", "pesan": "Tidak ada rangkaian gambar"})

    # Menerima rangkaian gambar Base64 dari JS
    image_sequence = data['image_sequence']
    
    current_encoding = None
    liveness_confirmed = False
    ear_sequence = []

    # Indeks mata untuk landmarks 'face_recognition'
    # 0:left, 1:top-left, 2:top-right, 3:right, 4:bottom-right, 5:bottom-left
    left_eye_indices = [36, 37, 38, 39, 40, 41]
    right_eye_indices = [42, 43, 44, 45, 46, 47]

    # --- MEMPROSES RANGKAIAN GAMBAR (LOOP FRAME) ---
    frames_processed = 0
    for frame_base64 in image_sequence:
        # Decode gambar (Sama seperti sebelumnya)
        image_b64 = frame_base64.split(',')[1]
        image_data = base64.b64decode(image_b64)
        np_arr = np.frombuffer(image_data, np.uint8)
        frame = cv2.imdecode(np_arr, cv2.IMREAD_COLOR)

        # Optimasi: Kecilkan gambar (Sama seperti sebelumnya)
        rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
        small_frame = cv2.resize(rgb_frame, (0, 0), fx=0.25, fy=0.25)
        
        # Ekstrak Facial Landmarks (Fitur Baru)
        # Model 'large' diperlukan untuk 68 landmark penuh
        face_landmarks_list = face_recognition.face_landmarks(small_frame, model="large")
        
        if len(face_landmarks_list) == 1:
            landmarks = face_landmarks_list[0]
            
            # Hitung EAR untuk kedua mata
            left_eye_coords = landmarks['left_eye']
            right_eye_coords = landmarks['right_eye']
            
            left_ear = calculate_ear(left_eye_coords)
            right_ear = calculate_ear(right_eye_coords)
            
            # EAR Rata-rata
            avg_ear = (left_ear + right_ear) / 2.0
            ear_sequence.append(avg_ear)
            
            # Ekstrak Encoding Wajah pada frame pertama yang valid untuk kecocokan ID
            if current_encoding is None:
                face_locations = face_recognition.face_locations(small_frame)
                if len(face_locations) == 1:
                    encodings = face_recognition.face_encodings(small_frame, face_locations)
                    if encodings:
                        current_encoding = encodings[0]
            
            frames_processed += 1
        
        elif len(face_landmarks_list) > 1:
             return jsonify({"status": "error", "pesan": "Lebih dari satu wajah terdeteksi"})

    # --- VERIFIKASI LIVENESS & IDENTITY ---
    if frames_processed < len(image_sequence):
        return jsonify({"status": "error", "pesan": "Wajah tidak selalu terlihat di setiap frame"})

    # Analisis Pola Kedipan (Liveness Verification)
    # Tanda hidup adalah EAR yang turun drastis dalam rangkaian
    min_ear_in_seq = min(ear_sequence) if ear_sequence else 1.0
    
    print(f"DEBUG: EAR Seq: {[f'{x:.3f}' for x in ear_sequence]} -> Min: {min_ear_in_seq:.3f}")
    
    if min_ear_in_seq < EAR_THRESHOLD:
        liveness_confirmed = True
    else:
        return jsonify({"status": "liveness_fail", "pesan": "Liveness gagal: Kedipan mata tidak terdeteksi"})

    # Cocokkan Identitas (Jika liveness lolos)
    if liveness_confirmed and current_encoding is not None:
        matches = face_recognition.compare_faces(known_encodings, current_encoding, tolerance=0.55)
        face_distances = face_recognition.face_distance(known_encodings, current_encoding)
        best_match_index = np.argmin(face_distances) if len(face_distances) > 0 else -1

        if best_match_index != -1 and matches[best_match_index]:
            id_match = known_ids[best_match_index]
            nama_match = known_names[best_match_index]
            waktu_sekarang = time.time()

            # Cek Cooldown (Sama)
            if id_match in log_cooldown:
                if (waktu_sekarang - log_cooldown[id_match]) < COOLDOWN_TIME:
                    return jsonify({"status": "cooldown", "pesan": "Masih dalam jeda absensi"})

            # Lolos cooldown, catat waktu sekarang
            log_cooldown[id_match] = waktu_sekarang
            
            # Tembak Laragon (Sama)
            requests.post(API_ABSEN, data={"id_pengguna": id_match, "nama_lengkap": nama_match})
            
            print(f"Berhasil: {nama_match}")
            return jsonify({"status": "success", "nama": nama_match})
        else:
            return jsonify({"status": "unknown", "pesan": "Identitas wajah tidak dikenali"})

    return jsonify({"status": "error", "pesan": "Gagal memproses data"})

# --- ENDPOINT BARU UNTUK WEB ENROLLMENT ---
@app.route('/daftar_wajah', methods=['POST'])
def daftar_wajah():
    data = request.json
    if 'image' not in data or 'nama' not in data:
        return jsonify({"status": "error", "pesan": "Data tidak lengkap"})

    nama = data['nama']
    image_b64 = data['image'].split(',')[1]
    image_data = base64.b64decode(image_b64)
    np_arr = np.frombuffer(image_data, np.uint8)
    frame = cv2.imdecode(np_arr, cv2.IMREAD_COLOR)
    rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)

    # Deteksi wajah
    face_locations = face_recognition.face_locations(rgb_frame)
    if len(face_locations) == 1:
        # Ekstrak encoding
        encoding = face_recognition.face_encodings(rgb_frame, face_locations)[0]
        encoding_list = encoding.tolist()
        encoding_json = json.dumps(encoding_list)

        # Tembak ke API PHP yang sudah kita buat sebelumnya
        API_DAFTAR = f"{BASE_URL}/api_pendaftaran.php"
        requests.post(API_DAFTAR, data={"nama_lengkap": nama, "encoding": encoding_json})
        
        # Reload database di memori Python agar wajah baru langsung bisa absen
        muat_data_database()

        return jsonify({"status": "success", "pesan": f"{nama} berhasil didaftarkan!"})
    elif len(face_locations) == 0:
        return jsonify({"status": "error", "pesan": "Wajah tidak terdeteksi di kamera."})
    else:
        return jsonify({"status": "error", "pesan": "Lebih dari satu wajah terdeteksi."})

# --- ENDPOINT BARU: REAL DATA CLUSTERING ---
@app.route('/get_analytics', methods=['GET'])
def get_analytics():
    try:
        # 1. Tarik seluruh log dari database Laragon via API PHP
        res = requests.get(f"{BASE_URL}/api_get_all_absensi.php")
        logs = res.json()
        
        if not logs or len(logs) == 0:
            return jsonify({"status": "empty", "data": []})
        
        # 2. Kelompokkan jam deteksi berdasarkan nama pengguna
        user_data = {}
        for log in logs:
            nama = log['nama_lengkap']
            waktu_str = log['waktu_deteksi'].split(' ')[1] # Ambil bagian HH:MM:SS
            
            # Konversi waktu string ke jam desimal (Misal 07:30 -> 7.5)
            t = datetime.datetime.strptime(waktu_str, "%H:%M:%S")
            decimal_hour = t.hour + t.minute/60.0 + t.second/3600.0
            
            if nama not in user_data:
                user_data[nama] = []
            user_data[nama].append(decimal_hour)
        
        # 3. Hitung Expected Arrival (Mean) & Volatility (Standard Deviation)
        features = []
        user_names = []
        for nama, hours in user_data.items():
            mean_hour = np.mean(hours)
            std_hour = np.std(hours) if len(hours) > 1 else 0.0 # 0 jika baru absen 1 kali
            
            features.append([mean_hour, std_hour])
            user_names.append(nama)
            
        X = np.array(features)
        
        # 4. Jalankan Algoritma K-Means jika jumlah pengguna mencukupi (>= 2)
        cluster_labels = [0] * len(user_names)
        if len(user_names) >= 2:
            n_clusters = min(3, len(user_names)) # Maksimal membagi menjadi 3 kelompok
            kmeans = KMeans(n_clusters=n_clusters, random_state=42, n_init='auto')
            cluster_labels = kmeans.fit_predict(X).tolist()
            
        # 5. Susun data JSON untuk dikembalikan ke Chart.js di Web Admin
        result_data = []
        for i in range(len(user_names)):
            result_data.append({
                "nama": user_names[i],
                "x": float(X[i][0]),
                "y": float(X[i][1]),
                "cluster": cluster_labels[i]
            })
            
        return jsonify({"status": "success", "data": result_data})
    except Exception as e:
        return jsonify({"status": "error", "pesan": str(e)})

if __name__ == '__main__':
    print("Server API Python berjalan di http://localhost:5000")
    app.run(port=5000, debug=False)