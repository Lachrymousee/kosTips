# Hasil pengujian versi PHP tanpa JavaScript

Tanggal: 22 Oktober 2026.

| Pemeriksaan | Hasil |
| --- | --- |
| Syntax PHP | 29 file lolos |
| Layanan PHP | 30 pemeriksaan lolos |
| Integrasi HTTP + HTML formulir | 60 pemeriksaan lolos |
| JavaScript pada HTML | Tidak ada tag script/inline event handler; CSP script-src none |
| Skema MySQL/MariaDB dan konfigurasi XAMPP | Disediakan; belum dijalankan pada server MySQL/Apache nyata di lingkungan pengerjaan |
| Rendering browser visual | Belum diverifikasi pada browser Windows/XAMPP |

**90 pemeriksaan perilaku otomatis** lolos. Pengujian memakai PHP 8.5.10 melalui runtime PHP.wasm, SQLite terisolasi, dan klien HTTP Python. Kode PHP yang diuji adalah kode aplikasi sebenarnya. Pengujian HTML/form tidak menggunakan JavaScript atau browser automation.

Cakupan: render lima thread per halaman; POST/redirect/GET; cookie session; token CSRF semua formulir; penolakan CSRF/metode salah; akun anggota/admin; pemalsuan identitas/peran; kepemilikan; edit/hapus balasan; konfirmasi alasan; escaped HTML pada balasan/log; multipart upload PNG/PDF nyata; penolakan file PHP/MIME palsu/kosong/terlalu besar/jumlah berlebih; direktori privat per judul; unduhan dan header; mempertahankan lampiran saat edit; bookmark; pencarian; redirect lokal; penghapusan file; cascade; log yang bertahan; rollback log gagal; rate limit; penghapusan identitas demo ketika produksi aktif.

## Menjalankan ulang tes (opsional untuk pengembang)

Perintah berikut hanya untuk menjalankan tes otomatis, **bukan syarat menjalankan website**. Setup website cukup melalui XAMPP dan phpMyAdmin seperti README.

```bash
php tests/service.php
python tests/http_test.py --php php
```

Butuh PHP dengan `pdo_sqlite` dan Python 3. Suite layanan menggunakan SQLite in-memory. Suite HTTP menyalin proyek ke direktori sementara, membuat database SQLite tersendiri, dan menjalankan server pengujian. Tidak memakai database MySQL utama atau file upload pengguna.

Di lingkungan pengerjaan, CLI/adapter PHP.wasm dipakai untuk menjalankan PHP karena PHP native tidak tersedia. Jalur `php -S` pada skrip uji belum dijalankan dengan PHP native di sini.

## Pemeriksaan yang perlu dilakukan tim pada XAMPP

1. Impor `database/kostips.sql` melalui phpMyAdmin; buka beranda dan pastikan data contoh tersedia.
2. Matikan JavaScript di pengaturan browser dan coba buat/edit thread, balasan, pencarian, pagination, simpan, serta moderasi.
3. Uji preview gambar, input file, menu akun, sidebar desktop/mobile, dan navigasi keyboard.
4. Cek `http://localhost/kostips-php/database/kostips.sql` dan `http://localhost/kostips-php/config.php` ditolak Apache (403), bukan menampilkan isi.
5. Cek folder upload di luar htdocs dan ulangi penghapusan melalui admin. Pastikan izin disk memungkinkan pembersihan.
6. Uji dua sesi yang mengubah thread yang sama untuk memeriksa penguncian/transaksi MySQL nyata.
7. Sebelum deployment publik: integrasikan autentikasi asli, matikan demo, gunakan HTTPS, verifikasi cookie/proxy, backup, dan pengelolaan file yatim.

Tidak ada klaim audit keamanan menyeluruh atau deployment produksi selesai.
