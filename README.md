# Final Project Secure Programming

**Proyek:** Website forum tips kehidupan anak kos

Dokumen ini merangkum konsep proyek, requirement tugas, fitur, pembagian kerja, keamanan, pengujian, dan pengumpulan. Fokus pengerjaan adalah aplikasi yang berfungsi, kode yang mudah ditinjau, serta keamanan yang bisa dibuktikan. Desain cukup rapi, terbaca, dan mudah digunakan.

Ketentuan dari soal dan tambahan wajib upload dibedakan dari usulan teknis. Angka batas upload, struktur tabel, pembagian anggota, dan fitur admin di bawah merupakan rancangan kerja yang masih dapat disesuaikan tim. Belum ada klaim bahwa fitur telah dibuat atau lulus pengujian.

## 1. Konsep dan teknologi

Forum membantu anak kos bertanya dan berbagi pengalaman tentang memasak, merawat barang, kebersihan, dan menghemat pengeluaran. Pengguna membuat thread dalam kategori yang tersedia, membalas diskusi, dan melampirkan file pendukung.

| Komponen         | Rencana                                                                  |
| ---------------- | ------------------------------------------------------------------------ |
| Frontend         | HTML dan CSS; JavaScript sederhana jika diperlukan                       |
| Backend          | PHP native sebagai usulan kerja berdasarkan materi kelas                 |
| Database         | MySQL; catat produk dan versi database aktual dari instalasi XAMPP       |
| Lingkungan lokal | XAMPP dengan Apache, PHP, dan database                                   |
| Kolaborasi       | GitHub untuk source code, branch, pull request, dan penanda versi review |
| Data pengujian   | Akun dan konten dummy, termasuk akun A, akun B, dan admin                |

## 2. Requirement resmi dan penilaian

| Ketentuan                                                      | Penerapan pada proyek                                                                             |
| -------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| Kelompok berisi 3–5 mahasiswa                                  | Tim berisi 4 orang                                                                                |
| Tema dan fitur aplikasi bebas                                  | Forum tips kehidupan anak kos                                                                     |
| Membuat aplikasi web aman sesuai materi perkuliahan            | Kontrol keamanan diterapkan pada setiap fitur                                                     |
| Review source code antarkelompok secara white-box              | Source code dan petunjuk menjalankan aplikasi dibagikan kepada kelompok peninjau                  |
| Pembagian review dilakukan secara melingkar                    | Ikuti pengaturan pasangan/arah review dari dosen; jangan menetapkan sendiri pola yang berbeda     |
| Aplikasi sudah berfungsi saat sesi ke-10                       | Semua fitur wajib dan pengujian internal selesai sebelum review dimulai                           |
| Versi kode saat review menjadi versi penilaian                 | Catat hash commit/tag yang digunakan; perubahan setelah review tidak ikut dievaluasi menurut soal |
| Menyerahkan aplikasi dan dua laporan                           | Application Report serta Code Review Report aplikasi kelompok lain                                |
| Presentasi dan pengumpulan pada minggu terakhir perkuliahan    | Siapkan demo aplikasi dan hasil review kelompok lain                                              |
| Snippet/screenshot pada setiap penjelasan dalam laporan review | Setiap temuan atau kontrol yang dibahas disertai bukti yang relevan                               |

**Tambahan wajib dari instruksi terbaru:** fitur upload file harus ada. Ketentuan ini disampaikan terpisah dari DOCX; karena itu, upload tidak lagi masuk fitur yang ditunda.

## 3. Fitur aplikasi

### 3.1 Lingkup inti yang direncanakan

| ID  | Fitur                      | Cakupan                                                                              |
| --- | -------------------------- | ------------------------------------------------------------------------------------ |
| F01 | Registrasi, login, logout  | Akun anggota, autentikasi, dan pengelolaan session                                   |
| F02 | Kategori dan daftar thread | Kategori awal disiapkan tim; pengguna memilih kategori                               |
| F03 | Pengelolaan thread         | Membuat, membaca, mengedit, dan menghapus thread sendiri                             |
| F04 | Balasan                    | Membuat, mengedit, dan menghapus balasan sendiri                                     |
| F05 | Pencarian dan pagination   | Pencarian judul, filter kategori, serta pembatasan hasil per halaman                 |
| F06 | Upload dan akses lampiran  | Mengunggah, mengunduh, dan menghapus lampiran thread dengan pemeriksaan izin         |
| F07 | Moderasi admin sederhana   | Usulan: admin dapat menghapus konten yang melanggar, tanpa mengubah tulisan pengguna |

Kategori awal dapat berupa Memasak, Kebersihan, Perawatan Barang, dan Hemat Pengeluaran. Konten thread/balasan menggunakan teks biasa; tidak menerima HTML mentah.

### 3.2 Fitur tambahan dan fitur yang ditunda

- **Tambahan jika fitur inti sudah stabil sebelum review:** penanda balasan sebagai solusi dan formulir masalah/hal yang sudah dicoba/hasil yang diharapkan.
- **Ditunda:** komunitas buatan pengguna, pelaporan konten, chat pribadi, notifikasi email, login Google, editor HTML, dan diskusi real-time.
- Upload pada balasan ditunda; lingkup upload awal cukup pada thread.
- Tabel `reports` belum diperlukan jika fitur pelaporan belum dibuat.
- Tidak ada fitur menyembunyikan thread, mengikuti catatan awal.

## 4. Aturan bisnis dan hak akses

### 4.1 Aturan utama

1. Identitas pembuat konten diambil dari session server, bukan `user_id` yang dikirim formulir.
2. Registrasi selalu menghasilkan anggota biasa. Input `role` dari pengguna tidak boleh menjadikan akun admin.
3. Anggota hanya boleh mengedit/menghapus thread, balasan, dan lampiran miliknya sendiri.
4. Pemilik thread tidak boleh mengedit atau menghapus balasan pengguna lain secara terpisah. Namun, thread tetap boleh dihapus meskipun memiliki balasan, mengikuti keputusan awal.
5. Penghapusan thread menghapus seluruh balasan dan lampirannya. Tampilkan konsekuensi tersebut sebelum konfirmasi.
6. Balasan dan lampiran tidak boleh dibuat untuk thread yang sudah dihapus.
7. Admin dapat menghapus konten untuk moderasi; tindakan dicatat dalam log. Admin tidak mengedit tulisan pengguna lain.
8. Jika pengelolaan kategori dibuat kemudian, kategori yang masih digunakan thread tidak boleh langsung dihapus.
9. Jika fitur solusi dibuat: hanya pemilik thread yang memilih, balasan harus berasal dari thread yang sama, dan penanda dikosongkan jika balasan dihapus.

### 4.2 Usulan matriks akses

| Tindakan                                    | Tamu  | Anggota           | Admin                |
| ------------------------------------------- | ----- | ----------------- | -------------------- |
| Melihat kategori/thread/balasan             | Boleh | Boleh             | Boleh                |
| Mencari thread                              | Boleh | Boleh             | Boleh                |
| Membuat thread/balasan                      | Tidak | Boleh             | Boleh                |
| Mengedit thread/balasan                     | Tidak | Milik sendiri     | Milik sendiri        |
| Menghapus thread/balasan                    | Tidak | Milik sendiri     | Boleh untuk moderasi |
| Upload lampiran                             | Tidak | Ke thread sendiri | Ke thread sendiri    |
| Mengunduh lampiran thread yang dapat dibaca | Boleh | Boleh             | Boleh                |
| Menghapus lampiran                          | Tidak | Milik sendiri     | Boleh untuk moderasi |

Matriks ini mengusulkan forum dan lampiran yang bersifat publik. Jika tim memilih lampiran khusus anggota, ubah aturan download dan tesnya secara konsisten. Semua izin diperiksa di server pada setiap permintaan; menyembunyikan tombol bukan kontrol akses.

## 5. Rancangan upload file

### 5.1 Batas awal yang diusulkan

| Aturan           | Nilai awal                                                         |
| ---------------- | ------------------------------------------------------------------ |
| Jenis file       | JPG/JPEG, PNG, PDF                                                 |
| Ukuran           | Maksimal 2 MB per file                                             |
| Jumlah           | Maksimal 3 lampiran aktif per thread                               |
| Tempat upload    | Thread milik pengguna yang sedang login                            |
| Nama penyimpanan | Nama acak dari server; nama asli hanya metadata                    |
| Penyimpanan      | Di luar webroot/folder publik, dengan eksekusi file dinonaktifkan  |
| Akses            | Melalui endpoint berdasarkan ID lampiran, dengan pemeriksaan akses |
| Penyajian awal   | Unduh sebagai lampiran; preview PDF tidak diperlukan               |

Jenis dan batas di atas perlu disepakati tim; soal tidak menetapkan angka tersebut. Tambahkan batas frekuensi upload dan kuota pengguna agar penyimpanan tidak mudah dipenuhi.

### 5.2 Kontrol upload dan download

- Validasi di server: ekstensi yang diizinkan, tipe konten hasil pemeriksaan server, struktur/signature file, ukuran, jumlah, dan status upload. Jangan hanya percaya ekstensi atau `Content-Type` dari browser.
- Pemeriksaan tipe bukan jaminan file bebas malware. Untuk PDF, pertimbangkan pemindai malware bila tersedia dan catat batas pemeriksaan yang benar-benar diterapkan.
- Gunakan nama penyimpanan buatan server; jangan menyusun path dari nama file pengguna.
- Lindungi upload/penghapusan dengan login, pemeriksaan kepemilikan, dan token CSRF.
- Download menerima ID lampiran, mengambil lokasi dari metadata server, dan memeriksa akses; jangan menerima path bebas dari request.
- Sajikan dengan tipe konten yang ditetapkan server, `Content-Disposition: attachment`, dan `X-Content-Type-Options: nosniff`; tangani nama file pada header secara aman.
- Bersihkan file sementara jika validasi atau penyimpanan metadata gagal.
- Penghapusan metadata database tidak otomatis menghapus file fisik. Buat proses pembersihan file dan penanganan jika penghapusan gagal.
- Pastikan file tetap tersedia setelah server dimulai ulang atau aplikasi di-deploy ulang.

## 6. Database dan struktur kode

### 6.1 Tabel awal

| Tabel         | Data utama                                                                 | Relasi/aturan                                                      |
| ------------- | -------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| `users`       | ID, username, password hash, role, waktu pembuatan                         | Username unik; email opsional sesuai keputusan akun                |
| `categories`  | ID, nama kategori                                                          | Satu kategori memiliki banyak thread                               |
| `threads`     | ID, penulis, kategori, judul, isi, waktu pembuatan/perubahan               | Foreign key ke `users` dan `categories`                            |
| `replies`     | ID, thread, penulis, isi, waktu pembuatan/perubahan                        | Foreign key ke `threads` dan `users`                               |
| `attachments` | ID, thread, pengunggah, nama asli, nama simpan, MIME, ukuran, waktu upload | Foreign key ke `threads` dan `users`; file fisik dikelola aplikasi |

Gunakan primary key, foreign key, `NOT NULL`, dan `UNIQUE` sesuai kebutuhan. Aturan penghapusan balasan/metadata lampiran harus konsisten dengan aturan penghapusan thread. Data session, pembatasan percobaan login, dan log juga perlu tempat penyimpanan; tentukan mekanismenya tanpa menganggap lima tabel di atas mencakup seluruh kebutuhan teknis.

### 6.2 Pembagian folder yang disarankan

| Lokasi             | Fungsi                                                                        |
| ------------------ | ----------------------------------------------------------------------------- |
| `public/`          | Halaman/endpoint publik dan aset CSS/JavaScript; menjadi document root Apache |
| `app/`             | Logika akun, thread, balasan, lampiran, dan pemeriksaan izin                  |
| `views/`           | Tampilan HTML/PHP                                                             |
| `config/`          | Contoh konfigurasi dan konfigurasi lokal yang tidak masuk Git                 |
| `database/`        | Skema dan data dummy                                                          |
| `storage/uploads/` | File pengguna, di luar document root                                          |
| `storage/logs/`    | Log server, di luar document root                                             |
| `docs/`            | Rancangan, bukti pengujian, dan bahan laporan                                 |
| `README.md`        | Cara instalasi, konfigurasi, dan menjalankan aplikasi                         |

Keamanan folder bergantung pada konfigurasi Apache: pastikan yang dipublikasikan hanya `public/`, bukan seluruh proyek. Simpan rahasia, upload pengguna, dan log di luar Git.

Komponen bersama yang dibuat di awal: koneksi database, pengecekan login/peran/kepemilikan, token CSRF, validasi, encoding output HTML, dan penanganan error. Hindari tiap anggota membuat versi kontrol keamanan yang berbeda.

## 7. Pembagian tugas 4 orang

Ganti label Anggota 1–4 dengan nama anggota. Pembagian berdasarkan modul agar setiap orang mengerjakan fungsi, keamanan, pengujian, dan penjelasan kodenya sendiri.

| Anggota                      | Modul utama   | Pekerjaan dan hasil yang diserahkan                                                                                                                                  |
| ---------------------------- | ------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **1 — Akun dan session**     | F01           | Registrasi/login/logout, password hash, session dan batas waktunya, pembatasan login, fondasi pengecekan login/CSRF bersama; tes autentikasi; dokumentasi modul akun |
| **2 — Thread dan pencarian** | F02, F03, F05 | Kategori, CRUD thread, pencarian/filter/pagination, koordinasi skema database; tes validasi dan kepemilikan thread; dokumentasi modul diskusi                        |
| **3 — Balasan dan moderasi** | F04, F07      | CRUD balasan, kontrol kepemilikan, tindakan admin dan log moderasi; tes akses admin dan balasan; dokumentasi modul moderasi                                          |
| **4 — Lampiran**             | F06           | Upload/download/hapus lampiran, validasi file, metadata, penyimpanan di luar webroot, kuota dan pembersihan file; tes upload/download; dokumentasi modul lampiran    |

### Tanggung jawab bersama dan koordinasi

| Pekerjaan lintas modul                          | Koordinator | Kontribusi anggota lain                                                  |
| ----------------------------------------------- | ----------- | ------------------------------------------------------------------------ |
| Lingkup, ERD, matriks izin, dan aturan API/form | Anggota 2   | Semua menyepakati kebutuhan modul sebelum coding                         |
| Komponen keamanan bersama                       | Anggota 1   | Semua memakai dan meninjau komponen yang sama                            |
| Integrasi dan uji alur menyeluruh               | Anggota 2   | Semua memperbaiki masalah modulnya; integrasi dilakukan berkala          |
| Percobaan lingkungan/deployment                 | Anggota 4   | Anggota 1 memeriksa session, anggota 2 database, anggota 3 alur moderasi |
| Penyusunan Application Report                   | Anggota 1   | Setiap anggota menulis fitur, mitigasi, dan bukti tes modulnya           |
| Penyusunan Code Review Report                   | Anggota 3   | Semua mereview modul terkait pada aplikasi kelompok lain                 |
| Presentasi                                      | Semua       | Masing-masing menjelaskan kontribusi dan hasil review                    |

Review internal dapat memakai rotasi: anggota 1 memeriksa kode anggota 2, 2 memeriksa 3, 3 memeriksa 4, dan 4 memeriksa 1. Ini terpisah dari pembagian review antarkelompok yang ditentukan dosen. Keamanan dan pengujian bukan tugas satu orang saja.

## 8. Keamanan dan pengujian

| Risiko                          | Kontrol yang direncanakan                                                                                                     |
| ------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| SQL injection                   | Prepared statements untuk nilai input; allowlist untuk pilihan kolom pengurutan                                               |
| XSS                             | Konten teks biasa; encoding output sesuai konteks, termasuk judul dan nama file                                               |
| IDOR/akses tanpa izin           | Periksa session, peran, dan kepemilikan setiap operasi                                                                        |
| CSRF                            | Token pada tindakan yang mengubah data; jangan menghapus melalui GET                                                          |
| Kebocoran password              | `password_hash()` dan `password_verify()`; tidak menyimpan plaintext                                                          |
| Penyalahgunaan session          | Regenerasi ID setelah login; invalidasi saat logout; batas idle/total; cookie `HttpOnly`, `SameSite`, dan `Secure` pada HTTPS |
| Brute force/spam                | Pembatasan di server; tetapkan ambang, jangka waktu, penyimpanan penghitung, dan pemulihan                                    |
| Upload berbahaya/path traversal | Kontrol berlapis pada bagian upload; file tidak dapat dieksekusi                                                              |
| Kebocoran konfigurasi/error     | Rahasia di luar Git/webroot; pesan umum ke pengguna; detail di log server                                                     |
| Penyalahgunaan sumber daya      | Batas panjang input, hasil pencarian, jumlah/ukuran file, kuota, dan frekuensi operasi                                        |

Usulan batas input awal: judul 150 karakter, isi thread 5.000 karakter, dan 20 thread per halaman. Nilai ini keputusan proyek, bukan aturan dari soal atau OWASP.

### Daftar tes minimum

| ID  | Skenario                                                                 | Hasil yang diharapkan                                                           |
| --- | ------------------------------------------------------------------------ | ------------------------------------------------------------------------------- |
| T01 | Registrasi sampai logout dengan input valid                              | Alur berhasil; username duplikat ditolak                                        |
| T02 | Akun B mengganti ID edit/hapus konten akun A                             | Ditolak dan data A tidak berubah                                                |
| T03 | Anggota membuka endpoint admin langsung                                  | Ditolak                                                                         |
| T04 | Tindakan perubahan data tanpa token CSRF valid                           | Ditolak tanpa perubahan data                                                    |
| T05 | Script/markup pada judul, isi, pencarian, dan nama file                  | Tidak dieksekusi                                                                |
| T06 | Input SQL pada login/pencarian                                           | Tidak melewati autentikasi atau mengubah struktur query                         |
| T07 | Session lama dipakai setelah logout atau timeout                         | Tidak memberi akses fungsi login                                                |
| T08 | Login/posting/upload melewati batas                                      | Pembatasan server aktif sesuai kebijakan                                        |
| T09 | Upload file valid lalu download                                          | Berhasil sesuai aturan akses; isi file utuh                                     |
| T10 | Tipe palsu/script diganti nama, file terlalu besar, atau jumlah berlebih | Ditolak tanpa file sisa                                                         |
| T11 | Manipulasi ID lampiran, thread tujuan, atau path                         | Tidak memberi hak upload/hapus atau akses file di luar izin                     |
| T12 | Thread dengan balasan dan lampiran dihapus                               | Seluruh data terkait dibersihkan; file tak lagi dapat diakses                   |
| T13 | Aplikasi dijalankan dari setup bersih                                    | README, skema, dan data dummy cukup untuk menjalankan aplikasi                  |
| T14 | Server dimulai ulang/deployment diperbarui                               | Data dan file tersimpan tetap tersedia; tidak ada kehilangan data tak terencana |

Catatan setiap tes: **ID, prasyarat, langkah, hasil yang diharapkan, hasil aktual, bukti, status, penanggung jawab**. Pemeriksaan otomatis dapat membantu, tetapi tidak menggantikan review kode dan pengujian hak akses manual. OWASP ASVS yang relevan dapat dipakai sebagai acuan tambahan tanpa mengklaim pemenuhan seluruh standar.

## 9. Jadwal dan alur kolaborasi

| Tahap                  | Target relatif                          | Hasil                                                                           |
| ---------------------- | --------------------------------------- | ------------------------------------------------------------------------------- |
| Persiapan              | Segera                                  | Lingkup, nama anggota, matriks izin, ERD, repo, dan versi lingkungan disepakati |
| Fondasi                | Sebelum pengembangan modul penuh        | Setup bersih berhasil; login, database, dan penyimpanan file dicoba             |
| Implementasi           | Sebelum sesi ke-10                      | Fitur inti dibuat dan digabung bertahap; dokumentasi/bukti tes dikumpulkan      |
| Pengujian internal     | Sisakan waktu khusus sebelum sesi ke-10 | Alur menyeluruh dan kontrol keamanan diuji; temuan diperbaiki                   |
| Pembekuan versi        | Awal kegiatan review sesi ke-10         | Commit/tag penilaian, source code, schema, README, dan data dummy siap          |
| Review antarkelompok   | Mulai sesi ke-10 sesuai jadwal dosen    | Temuan/kontrol, bukti, serta rekomendasi untuk aplikasi kelompok lain           |
| Pengumpulan/presentasi | Minggu terakhir perkuliahan             | Aplikasi versi penilaian, dua laporan, dan demo                                 |

Gunakan branch per fitur dan pull request yang diperiksa anggota lain. Catat hash commit/tag versi review, misalnya `review-submission`, pada kedua laporan sesuai aplikasi yang dibahas. Jangan memindahkan tag tersebut ke kode baru. Menurut soal, perubahan setelah kegiatan review tidak ikut dinilai; jangan mengganti versi penilaian secara diam-diam.

**Syarat fitur dianggap selesai:** alur normal berjalan, input tidak valid ditangani, akses tanpa izin ditolak, kontrol keamanan diuji, bukti dicatat, kode ditinjau, dan fitur berfungsi setelah integrasi.

## 10. Deliverable dan isi laporan

### 10.1 Aplikasi

- Source code sesuai commit/tag penilaian.
- Skema database dan data dummy.
- README berisi kebutuhan software/versi, konfigurasi, setup database, menjalankan aplikasi, serta cara menyiapkan akun uji.
- Contoh konfigurasi tanpa password/secret asli.
- Penjelasan folder upload, hak akses folder, dan konfigurasi document root.

### 10.2 Application Report

1. Identitas kelompok, tujuan, dan masalah yang diselesaikan.
2. Lingkungan teknologi dan alasan pemilihannya.
3. Fitur, alur pengguna, ERD, dan matriks hak akses.
4. Penjelasan implementasi dan mitigasi keamanan per fitur, termasuk upload/download.
5. Skenario serta hasil pengujian nyata dengan bukti.
6. Pembagian kontribusi anggota, versi kode, serta keterbatasan yang masih ada.

### 10.3 Code Review Report aplikasi kelompok lain

1. Identitas aplikasi/kelompok yang direview, versi kode, dan lingkungan pemeriksaan.
2. Cakupan dan metode white-box review.
3. Daftar temuan kerentanan atau countermeasure yang teridentifikasi.
4. Snippet/screenshot pada setiap penjelasan, lokasi file/fungsi, penyebab, dan dampak.
5. Rekomendasi perbaikan yang mengatasi akar masalah serta cara memverifikasinya.

Format per temuan: **ID → fitur → lokasi kode → bukti → masalah/kontrol → dampak → rekomendasi → cara verifikasi**. Jika tidak menemukan kerentanan, jelaskan countermeasure dan buktinya; jangan menyimpulkan aplikasi pasti bebas celah hanya karena tes tidak menemukan masalah.

### 10.4 Penggunaan AI

DOCX menyertakan formulir deklarasi AI, tetapi bagian penggunaan yang diperbolehkan/dilarang masih berupa placeholder. Ikuti ketentuan yang dijelaskan dosen, catat alat dan penggunaan sebenarnya, serta isi deklarasi secara jujur. Jangan mengarang persentase kontribusi atau mengklaim hasil tes yang belum dilakukan.

## 11. Keputusan yang masih perlu ditetapkan

- [ ] Nama aplikasi dan nama pemilik setiap tugas. : kosTips
- [ ] PHP native dan versi lingkungan yang digunakan bersama. : benar
- [ ] Persetujuan kategori tetap, admin sederhana, dan matriks akses publik. : benar
- [ ] Jenis file, ukuran/jumlah maksimal, kuota, dan batas frekuensi upload. : sesuai dengan md diatas
- [ ] Ambang pembatasan login/posting dan batas waktu session. : 7 hari session
- [ ] Apakah wajib online; jika ya, pilih lingkungan yang mendukung PHP, database, session, dan penyimpanan file persisten. : kamu yang nentuin cocoknya pake apa
- [ ] Kelompok yang direview/meninjau dan cakupan pengujian yang disepakati.

GitHub digunakan untuk kolaborasi kode; pilihan layanan hosting belum final. Rencana Vercel dari catatan awal perlu dinilai kembali berdasarkan kebutuhan PHP, database, session, dan file persisten. Sebelum memilih hosting, buktikan alur login → buat thread → upload → download → logout pada lingkungan target.

## 12. Sumber penyusunan

- Instruksi terbaru: upload file menjadi fitur wajib.
- Diskusi perbaikan proyek: aturan bisnis, matriks akses, pembagian kerja, keamanan, dan pengujian.
- Referensi teknis tambahan: [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html), [Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html), dan [Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html).

Ketentuan dosen menjadi acuan penilaian; referensi OWASP membantu merancang dan memeriksa kontrol keamanan. Dokumen ini diperbarui ketika tim menyepakati keputusan dan memiliki hasil implementasi/pengujian nyata.
