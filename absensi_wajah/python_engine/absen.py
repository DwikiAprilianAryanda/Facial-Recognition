import cv2
import face_recognition
import requests
import json
import numpy as np
import time
import threading # Tambahan library bawaan Python untuk menjalankan proses di background

# --- KONFIGURASI URL LARAGON ---
BASE_URL = "http://localhost/absensi_wajah"
API_GET = f"{BASE_URL}/api_get_data.php"
API_STATUS = f"{BASE_URL}/api_status.php"
API_ABSEN = f"{BASE_URL}/api_catat_absen.php"

# --- 1. TARIK DATA DARI DATABASE ---
print("Menghubungkan ke database...")
try:
    response = requests.get(API_GET)
    users_db = response.json()
except Exception as e:
    print("Gagal menghubungi server API:", e)
    exit()

known_encodings = []
known_ids = []
known_names = []

for user in users_db:
    encoding_array = np.array(json.loads(user['encoding_wajah']))
    known_encodings.append(encoding_array)
    known_ids.append(user['id_pengguna'])
    known_names.append(user['nama_lengkap'])

print(f"Berhasil memuat {len(known_names)} data wajah.")

# --- FUNGSI MULTITHREADING UNTUK API ---
def tembak_api_background(url, data):
    """Fungsi ini akan berjalan di luar jalur utama kamera agar tidak lag"""
    try:
        requests.post(url, data=data)
    except:
        pass

def kirim_status(pesan):
    threading.Thread(target=tembak_api_background, args=(API_STATUS, {"status_pesan": pesan})).start()

# --- 2. PERSIAPAN KAMERA & VARIABEL ---
cap = cv2.VideoCapture(0)
log_cooldown = {} 
COOLDOWN_TIME = 60 

# Variabel optimasi frame
process_this_frame = True
face_locations = []

print("Sistem Absensi Aktif. Tekan 'q' pada jendela kamera untuk keluar.")
kirim_status("Siap")

# --- 3. LOOPING DETEKSI REAL-TIME ---
while True:
    ret, frame = cap.read()
    if not ret:
        break
    
    # OPTIMASI: Hanya memproses wajah pada setiap frame yang berselang-seling
    if process_this_frame:
        small_frame = cv2.resize(frame, (0, 0), fx=0.25, fy=0.25)
        face_locations = face_recognition.face_locations(small_frame)
        
        if len(face_locations) == 1:
            # Pengecekan Jarak (Luas Wajah)
            top, right, bottom, left = face_locations[0]
            luas_wajah = (bottom - top) * (right - left)
            luas_frame = small_frame.shape[0] * small_frame.shape[1]
            
            if (luas_wajah / luas_frame) < 0.05:
                kirim_status("Mohon maju sedikit ke arah kamera.")
            else:
                face_encodings = face_recognition.face_encodings(small_frame, face_locations)
                
                if face_encodings:
                    encoding_kamera = face_encodings[0]
                    matches = face_recognition.compare_faces(known_encodings, encoding_kamera, tolerance=0.55)
                    face_distances = face_recognition.face_distance(known_encodings, encoding_kamera)
                    
                    best_match_index = np.argmin(face_distances) if len(face_distances) > 0 else -1
                    
                    if best_match_index != -1 and matches[best_match_index]:
                        id_match = known_ids[best_match_index]
                        nama_match = known_names[best_match_index]
                        waktu_sekarang = time.time()
                        
                        # PERBAIKAN LOGIKA COOLDOWN
                        bisa_absen = True
                        if id_match in log_cooldown:
                            if (waktu_sekarang - log_cooldown[id_match]) < COOLDOWN_TIME:
                                bisa_absen = False # Jangan gunakan 'continue', cukup ubah flag
                        
                        if bisa_absen:
                            print(f"✅ {nama_match} berhasil absen!")
                            log_cooldown[id_match] = waktu_sekarang
                            
                            # Jalankan HTTP Request di background menggunakan Threading
                            threading.Thread(
                                target=tembak_api_background, 
                                args=(API_ABSEN, {"id_pengguna": id_match, "nama_lengkap": nama_match})
                            ).start()
                            
                    else:
                        kirim_status("Wajah tidak dikenali.")
                        
        elif len(face_locations) > 1:
            kirim_status("Terdeteksi lebih dari satu orang.")

    # Balikkan status flag agar frame berikutnya dilewati (tidak diproses komputasinya)
    process_this_frame = not process_this_frame

    # Menggambar kotak di layar (berjalan setiap frame agar pergerakan terlihat mulus)
    for (top, right, bottom, left) in face_locations:
        top *= 4; right *= 4; bottom *= 4; left *= 4
        cv2.rectangle(frame, (left, top), (right, bottom), (0, 255, 0), 2)

    cv2.imshow('Sistem Absensi', frame)

    if cv2.waitKey(1) & 0xFF == ord('q'):
        kirim_status("Offline")
        break

cap.release()
cv2.destroyAllWindows()