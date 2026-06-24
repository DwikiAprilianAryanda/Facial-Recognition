import cv2
import face_recognition
import requests
import json

# Mengakses kamera laptop (index 0)
cap = cv2.VideoCapture(0)
print("Sistem Siap! Tekan 's' untuk memotret wajah, atau 'q' untuk keluar.")

while True:
    ret, frame = cap.read()
    cv2.imshow('Enrollment Wajah', frame)

    key = cv2.waitKey(1) & 0xFF
    
    # Jika tombol 's' ditekan
    if key == ord('s'):
        # Cari lokasi wajah di frame saat ini
        face_locations = face_recognition.face_locations(frame)
        
        # Pastikan hanya ada 1 wajah yang terdeteksi
        if len(face_locations) == 1:
            # Ekstrak 128 titik biometrik wajah
            face_encoding = face_recognition.face_encodings(frame, face_locations)[0]
            
            # Konversi numpy array ke list, lalu ubah jadi string JSON
            encoding_list = face_encoding.tolist()
            encoding_json = json.dumps(encoding_list)
            
            # Minta input nama di console
            nama = input("\nKamera ditahan. Masukkan Nama Pengguna: ")
            
            # Kirim data ke API PHP via HTTP POST
            url = "http://localhost/absensi_wajah/api/api_pendaftaran.php"
            payload = {"nama_lengkap": nama, "encoding": encoding_json}
            
            print("Mengirim data ke database...")
            response = requests.post(url, data=payload)
            print("Response Server:", response.text)
            
            break # Selesai, keluar dari loop
            
        elif len(face_locations) == 0:
            print("Wajah tidak terdeteksi. Coba geser posisi Anda.")
        else:
            print("Terdeteksi lebih dari satu wajah! Pastikan hanya Anda di frame.")
            
    # Jika tombol 'q' ditekan
    elif key == ord('q'):
        break

# Matikan kamera dan tutup jendela
cap.release()
cv2.destroyAllWindows()