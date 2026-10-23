# kosTips

Versi percobaan forum anak kos menggunakan **PHP native, HTML, CSS, dan MySQL/MariaDB**. Tampilan halaman dibuat di server. Tidak ada file JavaScript, `fetch`, npm, Composer, atau langkah CMD/PowerShell untuk menjalankan website.

Versi ini menggunakan folder `kostips-php`, database **`kostips_php`**, dan penyimpanan file terpisah. Folder dan database versi sebelumnya tidak perlu dihapus atau ditimpa.

## Jalankan dengan XAMPP, tanpa terminal

1. Ekstrak arsip. Pindahkan folder **`kostips-php`** ke **`C:\xampp\htdocs\`**. Pastikan file ini ada langsung pada lokasi berikut:

   `C:\xampp\htdocs\kostips-php\index.php`

   Hindari folder ganda seperti `kostips-php\kostips-php\index.php`.

2. Buka XAMPP Control Panel. Klik **Start** pada **Apache** dan **MySQL**, sampai keduanya berjalan.
3. Buka **http://localhost/phpmyadmin/**. Dari halaman utama phpMyAdmin, pilih **Import → Choose File**, pilih **`kostips-php\database\kostips.sql`**, lalu klik **Import/Go**. File SQL ini membuat database `kostips_php`, tabel, dan data contoh. Tidak perlu menjalankan `bin/setup.php` atau mengetik perintah terminal.
4. Buka **http://localhost/kostips-php/**.

Jika Apache menggunakan port selain 80, tambahkan port tersebut pada alamat, misalnya `http://localhost:8080/kostips-php/` dan `http://localhost:8080/phpmyadmin/`.

**Jika pengaturan MySQL masih bawaan XAMPP, tidak perlu mengubah konfigurasi:** host `127.0.0.1`, port `3306`, username `root`, password kosong. Aplikasi memuat nilai ini dari `config.php`.

Jika port atau password berbeda, salin `config.local.example.php` menjadi `config.local.php`, buka di VS Code, lalu ubah bagian `database`. Gunakan nama database `kostips_php` untuk percobaan ini. File lokal ini diabaikan Git. Port MySQL berbeda dari port Apache yang digunakan browser.

## Cara mencoba fitur

- Menu akun kanan atas menyediakan **P · Anggota A**, **Rani · Anggota B**, dan **Admin kosTips** pada localhost.
- Buat thread, pilih kategori, tulis isi, dan unggah JPG/PNG/PDF bila diperlukan.
- Buka thread dan kirim balasan. Edit/hapus hanya tersedia bagi pemilik konten, sedangkan admin dapat menghapus konten dengan alasan.
- Simpan thread untuk menampilkannya di menu **Tersimpan**. Gunakan pencarian, kategori, urutan, dan nomor halaman untuk menelusuri diskusi.
- Akun admin memiliki menu **Panel admin** dengan tab Thread, Balasan, Lampiran, dan Log moderasi. Menu koleksi anggota tidak ditampilkan pada admin.
- Saat mengedit thread, centang lampiran yang ingin dipertahankan. Lampiran yang dilepas centangnya dihapus setelah perubahan berhasil disimpan.

Setiap perubahan dikirim melalui formulir HTML `POST`. Setelah berhasil, PHP mengarahkan browser ke halaman hasil, sehingga refresh halaman hasil tidak mengirim formulir lagi. Pencarian, kategori, pagination, dan navigasi memakai link/form `GET`.

Menu akun menggunakan elemen HTML `<details>`. Sidebar dapat dibuka/diciutkan memakai checkbox dan CSS. Preview gambar tampil sesudah upload berhasil. Untuk membagikan thread, salin tautan yang tersedia pada detail thread. Tidak ada drag-and-drop khusus, preview sebelum upload, salin clipboard otomatis, atau pembaruan tanpa reload.

## Lokasi file upload

Pada instalasi XAMPP standar, PHP otomatis membuat folder berikut saat aplikasi digunakan:

`C:\xampp\kostips-php-data\uploads\`

Setiap thread yang disimpan memiliki subfolder **`<id-thread>-<slug-judul-awal>`**, misalnya:

`C:\xampp\kostips-php-data\uploads\t<id-unik>-menu-hemat-anak-kos\`

Nama file di disk diacak. Nama asli disimpan di database untuk tampilan dan unduhan. Judul yang sama memperoleh folder berbeda karena ID thread berbeda. Mengedit judul tidak mengganti nama folder yang sudah digunakan.

Folder penyimpanan berada **di luar `htdocs`**. Browser mengambil file melalui `download.php`, bukan melalui URL direktori upload. Lokasinya mengikuti induk DocumentRoot Apache; jika XAMPP berada di `D:\xampp`, lokasi default menjadi `D:\xampp\kostips-php-data`.

Batas: **3 lampiran/thread**, **2 MiB/file**, **20 MiB/pengguna**. JPG/JPEG, PNG, dan PDF saja. PHP memeriksa ekstensi, MIME dari isi file, signature/dimensi, ukuran, jumlah, dan kuota. Gambar boleh tampil inline; PDF dikirim sebagai unduhan. Lampiran mengikuti kebijakan forum publik, sehingga pembaca dapat mengunduhnya.

Jika folder tidak dapat dibuat, buat folder `kostips-php-data` melalui File Explorer di luar `htdocs`, lalu beri akun proses PHP izin tulis. Lokasi alternatif dapat diisi pada `storage_path` dalam `config.local.php`. Jangan mengarahkannya ke dalam `htdocs`.

## Hak akses dan moderasi

| Tindakan                        | Anggota                                | Admin               |
| ------------------------------- | -------------------------------------- | ------------------- |
| Buat thread/balasan             | Ya                                     | Ya                  |
| Edit thread/balasan             | Milik sendiri                          | Milik sendiri       |
| Hapus balasan secara individual | Milik sendiri                          | Semua, alasan wajib |
| Hapus thread                    | Milik sendiri, termasuk seluruh isinya | Semua, alasan wajib |
| Hapus lampiran                  | Milik sendiri                          | Semua, alasan wajib |
| Baca log moderasi               | Tidak                                  | Ya                  |

Pemilik thread tidak boleh menghapus balasan pengguna lain secara individual. Menghapus seluruh thread miliknya juga menghapus balasan yang menjadi bagian dari thread itu.

Penghapusan moderasi mencatat admin, waktu, jenis/ID target, pemilik, ID thread, ringkasan konten, dan alasan 5–500 karakter. Log tetap ada setelah konten dihapus. Kegagalan menyimpan log membatalkan penghapusan. Panel menampilkan hingga 200 log terbaru, dengan pagination.

Penghapusan file fisik dikerjakan sesudah transaksi database commit. Jika belum selesai, job tetap berada di tabel `file_cleanup`; admin dapat menekan **Coba bersihkan lagi** pada panel yang menampilkan pekerjaan tertunda. Tidak memerlukan terminal.

## Membuktikan backend berjalan

1. Kirim balasan unik, misalnya `Uji PHP tanpa JavaScript 001`.
2. Buka phpMyAdmin → `kostips_php` → `replies` → Browse. Cocokkan isi balasan dengan website.
3. Pada DevTools → Network, aksi formulir terlihat sebagai request **Document** ke `aksi.php` dengan metode POST, kemudian redirect 303 ke halaman hasil. Tidak memakai Fetch/XHR.
4. Unggah gambar dan cocokkan file fisik di folder upload dengan `relative_path` pada tabel `attachments`.
5. Hapus balasan uji memakai admin; balasan hilang, sementara catatan pada `moderation_logs` tetap tersimpan.

## Struktur source untuk pembagian tugas

| Lokasi                                                          | Tanggung jawab                                                    |
| --------------------------------------------------------------- | ----------------------------------------------------------------- |
| `index.php`, `thread.php`, `buat-thread.php`, `edit-thread.php` | Pintu masuk halaman forum dan editor                              |
| `koleksi.php`, `balasan.php`, `admin.php`, `hapus.php`          | Pintu masuk koleksi, balasan, moderasi, konfirmasi                |
| `app/pages/*.php`                                               | **HTML dan PHP digabung di sini** untuk isi masing-masing halaman |
| `app/layout-top.php`, `app/layout-bottom.php`                   | Header, sidebar, footer bersama                                   |
| `app/web.php`                                                   | Helper tampilan, URL, rendering, dan penanganan error             |
| `aksi.php`                                                      | Memproses formulir POST, CSRF, dan redirect                       |
| `app/bootstrap.php`                                             | Koneksi PDO, session, validasi dasar                              |
| `app/ForumService.php`                                          | CRUD, kepemilikan, transaksi, moderasi dan log                    |
| `app/UploadStore.php`, `download.php`                           | Validasi, penyimpanan, dan penyajian file                         |
| `style.css`, `assets/`                                          | CSS, font Noto Sans lokal, lisensi font                           |
| `config.php`, `config.local.example.php`                        | Default XAMPP dan contoh override opsional                        |
| `database/kostips.sql`                                          | **Satu file yang perlu diimpor** melalui phpMyAdmin               |
| `tests/`, `docs/`                                               | Tes terisolasi dan catatan integrasi                              |

File halaman utama memanggil helper bersama, kemudian merender file `app/pages/` terkait. Ini menghindari menyalin koneksi database/header/CSRF ke setiap halaman, dan tetap menggunakan PHP native tanpa framework.

## Batas versi percobaan

- Login/register asli belum diintegrasikan. Pergantian akun hanya diizinkan pada mode development, alamat klien loopback, dan Host localhost/127.0.0.1/[::1]. Jangan mengekspos mode demo melalui tunnel/proxy yang membuat klien luar terlihat lokal.
- Untuk deployment, matikan `demo_auth`, gunakan `environment=production`, dan integrasikan login tim. Tamu hanya dapat membaca sampai login tersedia.
- Minimum PHP 8.1+ dengan PDO MySQL, fileinfo, mbstring, iconv. Gunakan XAMPP dengan Apache 2.4. Perlindungan direktori sumber/SQL di dalam `htdocs` menggunakan `.htaccess` yang disertakan; Apache harus mengizinkan aturan tersebut.
- Pastikan `php.ini`: `file_uploads=On`, `upload_max_filesize` minimal 2M, `post_max_size` minimal 8M, `max_file_uploads` minimal 3. Jika mengubah php.ini, restart Apache melalui XAMPP.
- Tidak ada pemindaian antivirus. Proses yang mati mendadak saat upload masih dapat meninggalkan file staging/yatim; audit disk dan backup dibutuhkan untuk produksi.
- Pencarian dan pagination dilakukan di PHP atas dataset forum. Belum dioptimalkan untuk forum berskala besar.
- File upload dan data database versi lama tidak otomatis dimigrasikan. Gunakan versi ini untuk mencoba alur tanpa JavaScript terlebih dahulu.

Hasil verifikasi ada di `docs/HASIL-PENGUJIAN.md`. Pengujian otomatis memakai PHP dengan SQLite terisolasi; pengujian XAMPP/Apache/MySQL nyata tetap perlu dijalankan di komputer tim. Tidak ada deployment publik yang dilakukan untuk versi ini.
>>>>>>> e2ffb36 (folder structure, frontend, anggota 3)
