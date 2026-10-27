# Integrasi anggota kelompok

Versi ini meneruskan modul balasan, hak akses, moderasi, log, dan upload dari versi PHP sebelumnya. Perubahan utama berada pada penyajian HTML dan transport formulir, bukan perpindahan penyimpanan kembali ke browser.

## Alur permintaan

Browser membuka file halaman `.php`. `app/web.php` menyiapkan session dan membaca database. HTML di `app/pages/` dirender pada server menggunakan data tersebut. Setiap nilai pengguna di HTML dilewatkan ke `h()` (`htmlspecialchars`). Tidak ada script di browser; Content-Security-Policy memakai `script-src 'none'`.

Formulir perubahan menggunakan `method="post"`, `action="aksi.php"`, hidden field `action`, dan hidden field `csrf`. Formulir upload menambahkan `enctype="multipart/form-data"`. Setelah sukses, server merespons redirect 303 ke halaman hasil. Tindakan GET tidak mengubah konten.

## Pemetaan action

| Action POST                                          | Input                                                                   |
| ---------------------------------------------------- | ----------------------------------------------------------------------- |
| `thread.save`                                        | `title`, `category`, `body`, `id` untuk edit, `retain_ids[]`, `files[]` |
| `reply.create`                                       | `thread_id`, `body`                                                     |
| `reply.update`                                       | `id`, `body`                                                            |
| `thread.delete`, `reply.delete`, `attachment.delete` | `id`, `reason` wajib untuk admin                                        |
| `bookmark.toggle`                                    | `id`, opsional `return_to` ke halaman lokal yang diizinkan              |
| `session.demo`                                       | `account`: `me`, `rani`, atau `admin`, hanya lokal                      |
| `cleanup`                                            | Tidak ada input tambahan; hanya admin                                   |

Semua action wajib membawa CSRF. Identitas berasal dari session, peran berasal dari database. Payload `user_id`/`role` tidak memberi hak akses. Perubahan biasa dibatasi 30 sukses per pengguna per jendela 60 detik melalui layanan forum.

## Integrasi login anggota 1

Skema pengguna saat ini berisi profil dan peran akun contoh. Tambahkan kolom kredensial/migrasi sesuai modul autentikasi. Setelah kredensial benar-benar diverifikasi, gunakan bootstrap/session yang sama, regenerasi session ID, set `$_SESSION['user_id']` dari hasil database, set `auth_mode='password'`, rotasi token CSRF, dan perbarui waktu aktivitas. Logout harus membersihkan session dan cookie. Jangan menerima peran admin dari form registrasi.

Session memakai nama `KOSTIPS_PHP_SESSION`, HttpOnly, SameSite=Lax, Secure ketika request HTTPS, dan timeout aktivitas 30 menit. Peran diperiksa kembali saat transaksi. Saat produksi, konfigurasi demo harus dimatikan; session demo lama otomatis kehilangan identitas.

## Kontribusi anggota 3

Balasan CRUD, pengecekan kepemilikan, konfirmasi hapus, moderasi admin, audit log, dan pengujian terkait berada pada `aksi.php`, `app/ForumService.php`, serta halaman balasan/detail/admin. Anggota 3 tetap berkontribusi pada aplikasi kelompok sendiri; review kelompok lain merupakan kegiatan penilaian terpisah.

Anggota pengelola thread/upload dapat memanfaatkan `thread.save`, validasi `UploadStore`, dan `download.php`. Konfigurasi database dibuat sederhana agar integrasi dapat dilakukan melalui phpMyAdmin dan editor.

## Transaksi dan log

MySQL memakai InnoDB, prepared statements, READ COMMITTED, dan urutan lock pengguna lalu thread. Penghapusan admin dan INSERT log berada dalam transaksi yang sama. Log tidak mempunyai FK ke target yang dihapus. File fisik dibersihkan setelah commit menggunakan outbox `file_cleanup`.

Log bersifat append-only melalui aplikasi, bukan kebal perubahan oleh administrator database. Penghapusan thread mencatat satu tindakan tingkat thread; balasan/lampiran yang ikut cascade tidak dicatat sebagai tindakan admin terpisah. Penghapusan lampiran individual melalui konfirmasi moderasi mempunyai log sendiri.

`database/kostips.sql` membuat skema dan memasukkan seed menggunakan `INSERT IGNORE`; menjalankan ulang tidak menimpa baris yang ID-nya sudah ada. File ini bukan sistem migrasi perubahan skema. Database runtime dan `config.local.php` tidak boleh dimasukkan ke GitHub.

## Batas penyimpanan file

Folder default dibentuk dari induk DocumentRoot Apache, sehingga berada di luar htdocs. Jangan mengganti lokasi ke dalam htdocs. Staging dan uploads adalah direktori privat. Database hanya menyimpan metadata; nama folder memakai ID thread + judul awal, nama file fisik diacak. Memindahkan aplikasi ke hosting lain membutuhkan pengaturan storage_path yang dapat ditulis dan berada di luar area publik.
