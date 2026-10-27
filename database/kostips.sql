-- Import file ini melalui tab Import phpMyAdmin. Tidak ada DROP/TRUNCATE.
CREATE DATABASE IF NOT EXISTS kostips_php CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
USE kostips_php;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
 id VARCHAR(64) PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 initials VARCHAR(8) NOT NULL,
 color CHAR(7) NOT NULL,
 role VARCHAR(16) NOT NULL DEFAULT 'member',
 CONSTRAINT valid_role CHECK (role IN ('member','admin'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
CREATE TABLE IF NOT EXISTS threads (
 id VARCHAR(64) PRIMARY KEY,
 user_id VARCHAR(64) NOT NULL,
 category VARCHAR(50) NOT NULL,
 title VARCHAR(150) NOT NULL,
 body TEXT NOT NULL,
 upload_dir VARCHAR(160) NOT NULL UNIQUE,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 FOREIGN KEY(user_id) REFERENCES users(id),
 INDEX thread_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
CREATE TABLE IF NOT EXISTS replies (
 id VARCHAR(64) PRIMARY KEY,
 thread_id VARCHAR(64) NOT NULL,
 user_id VARCHAR(64) NOT NULL,
 body TEXT NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 FOREIGN KEY(thread_id) REFERENCES threads(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id),
 INDEX reply_thread(thread_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
CREATE TABLE IF NOT EXISTS attachments (
 id VARCHAR(64) PRIMARY KEY,
 thread_id VARCHAR(64) NOT NULL,
 user_id VARCHAR(64) NOT NULL,
 original_name VARCHAR(200) NOT NULL,
 mime_type VARCHAR(50) NOT NULL,
 size_bytes INTEGER NOT NULL,
 relative_path VARCHAR(255) NOT NULL UNIQUE,
 created_at DATETIME NOT NULL,
 FOREIGN KEY(thread_id) REFERENCES threads(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
CREATE TABLE IF NOT EXISTS bookmarks (
 user_id VARCHAR(64) NOT NULL,
 thread_id VARCHAR(64) NOT NULL,
 PRIMARY KEY(user_id,thread_id),
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(thread_id) REFERENCES threads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
CREATE TABLE IF NOT EXISTS moderation_logs (
 id VARCHAR(64) PRIMARY KEY,
 actor_id VARCHAR(64) NOT NULL,
 actor_name VARCHAR(100) NOT NULL,
 action VARCHAR(50) NOT NULL,
 target_type VARCHAR(20) NOT NULL,
 target_id VARCHAR(64) NOT NULL,
 thread_id VARCHAR(64) NOT NULL,
 target_owner_id VARCHAR(64) NOT NULL,
 title_snapshot VARCHAR(500) NOT NULL,
 reason VARCHAR(500) NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX log_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
CREATE TABLE IF NOT EXISTS file_cleanup (
 id VARCHAR(64) PRIMARY KEY,
 relative_path VARCHAR(255) NOT NULL,
 kind VARCHAR(10) NOT NULL,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
CREATE TABLE IF NOT EXISTS write_limits (
 user_id VARCHAR(64) PRIMARY KEY,
 window_started INTEGER NOT NULL,
 request_count INTEGER NOT NULL,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- Data contoh; ID yang sudah ada tidak ditimpa.
START TRANSACTION;
INSERT IGNORE INTO users (id,name,initials,color,role) VALUES ('me','P','P','#eaf4e4','member');
INSERT IGNORE INTO users (id,name,initials,color,role) VALUES ('rani','Rani Putri','RP','#f5eede','member');
INSERT IGNORE INTO users (id,name,initials,color,role) VALUES ('adit','Aditya R.','AR','#e9f0f2','member');
INSERT IGNORE INTO users (id,name,initials,color,role) VALUES ('nisa','Nisa Aulia','NA','#eeeaf4','member');
INSERT IGNORE INTO users (id,name,initials,color,role) VALUES ('dimas','Dimas','DM','#eaf1e1','member');
INSERT IGNORE INTO users (id,name,initials,color,role) VALUES ('salsa','Salsa','SA','#f6ebe4','member');
INSERT IGNORE INTO users (id,name,initials,color,role) VALUES ('admin','Admin kosTips','AD','#eaf4e4','admin');
INSERT IGNORE INTO threads (id,user_id,category,title,body,upload_dir,created_at) VALUES ('t1','rani','Memasak','Menu seminggu dengan satu rice cooker, masak apa saja?','Baru pindah kos dan alat masak yang ada cuma rice cooker. Lagi cari ide menu yang bahannya bisa dipakai beberapa kali supaya nggak banyak sisa.

Sudah coba nasi telur dan nasi ayam sederhana. Tapi kalau menunya itu terus, lama-lama bosan juga.

Teman-teman yang biasa masak pakai rice cooker, biasanya bikin apa? Kalau bisa bahannya mudah dicari di warung dekat kos dan nggak perlu disimpan di kulkas.

Boleh berbagi ide menu atau pengalaman kalian, ya.','t1-menu-seminggu-dengan-satu-rice-cooker-masak-apa-saja','2026-10-21 03:00:00');
INSERT IGNORE INTO replies (id,thread_id,user_id,body,created_at) VALUES ('r1','t1','nisa','Aku biasanya pilih satu bahan utama lalu ganti pelengkapnya. Telur bisa jadi nasi telur, telur kukus, atau teman sayur. Jadi belanjanya nggak terlalu banyak.','2026-10-21 03:10:00');
INSERT IGNORE INTO replies (id,thread_id,user_id,body,created_at) VALUES ('r2','t1','me','Aku juga baru coba masak pakai rice cooker. Ikut menyimak, terutama menu yang nggak perlu kulkas.','2026-10-21 03:20:00');
INSERT IGNORE INTO threads (id,user_id,category,title,body,upload_dir,created_at) VALUES ('t2','adit','Hemat Pengeluaran','Cara kalian mencatat pengeluaran kecil supaya nggak kebablasan?','Kopi, laundry, dan jajan sore ternyata kalau ditotal lumayan juga. Kalian lebih nyaman pakai aplikasi, spreadsheet, atau catatan biasa?

Aku lagi coba mencatat pengeluaran harian. Masalahnya, sering lupa memasukkan pengeluaran yang kecil-kecil.

Pengen punya cara yang simpel dan nggak makan waktu lama. Ada yang sudah menemukan kebiasaan mencatat yang cocok?','t2-cara-kalian-mencatat-pengeluaran-kecil-supaya-nggak-kebablasan','2026-10-21 02:00:00');
INSERT IGNORE INTO replies (id,thread_id,user_id,body,created_at) VALUES ('r3','t2','dimas','Aku pakai catatan di ponsel dulu setiap habis belanja. Baru dipindahkan ke spreadsheet kalau sudah di kos.','2026-10-21 02:20:00');
INSERT IGNORE INTO threads (id,user_id,category,title,body,upload_dir,created_at) VALUES ('t3','nisa','Kebersihan','Kamar tetap terasa pengap meski sudah dibersihkan','Jendela kamar menghadap lorong, jadi anginnya memang sedikit. Ada cara sederhana supaya kamar terasa lebih segar?

Sprei sudah diganti dan lantai rutin dibersihkan. Barang juga sudah dikurangi, tapi kamar tetap terasa pengap terutama sore hari.

Ada yang punya kondisi kamar serupa? Aku ingin mencoba memperbaiki sirkulasi udara dulu sebelum membeli barang tambahan.','t3-kamar-tetap-terasa-pengap-meski-sudah-dibersihkan','2026-10-21 01:00:00');
INSERT IGNORE INTO replies (id,thread_id,user_id,body,created_at) VALUES ('r4','t3','salsa','Di kosku, barang yang menutupi jendela ternyata cukup berpengaruh. Setelah rak digeser, aliran udaranya terasa lebih baik.','2026-10-21 01:20:00');
INSERT IGNORE INTO threads (id,user_id,category,title,body,upload_dir,created_at) VALUES ('t4','dimas','Perawatan Barang','Cara merawat sepatu yang sering kehujanan','Belakangan hampir setiap pulang kuliah sepatu basah. Biasanya kalian mengeringkannya di kamar kos dengan cara apa?

Tidak ada tempat jemur yang kena matahari langsung. Aku sudah melepas insole dan mengangin-anginkan sepatu, tapi kadang masih lembap.

Boleh berbagi pengalaman merawat sepatu sehari-hari di kos? Terutama supaya bentuknya tetap bagus.','t4-cara-merawat-sepatu-yang-sering-kehujanan','2026-10-21 00:00:00');
INSERT IGNORE INTO threads (id,user_id,category,title,body,upload_dir,created_at) VALUES ('t5','salsa','Hemat Pengeluaran','Belanja kebutuhan kos bareng teman, lebih praktis nggak?','Lagi kepikiran patungan sabun cuci dan perlengkapan bersih-bersih dengan teman satu lantai. Penasaran cara membaginya supaya jelas.

Kami biasanya belanja sendiri-sendiri. Padahal beberapa kebutuhan bisa dipakai bersama.

Kalau kalian pernah belanja bareng, pembagian biaya dan penyimpanannya bagaimana? Apa saja yang lebih cocok tetap dibeli sendiri?','t5-belanja-kebutuhan-kos-bareng-teman-lebih-praktis-nggak','2026-10-20 23:00:00');
INSERT IGNORE INTO threads (id,user_id,category,title,body,upload_dir,created_at) VALUES ('t6','me','Perawatan Barang','Menata meja belajar kecil supaya tetap nyaman','Meja di kamar cuma cukup untuk laptop dan beberapa buku. Aku sedang mencoba menata ulang supaya bisa dipakai belajar dan makan bergantian.

Saat ini kabel charger masih berantakan dan buku menumpuk di samping laptop.

Rencananya ingin pakai kotak kecil untuk alat tulis dan tempat kabel. Ada ide penataan yang bisa dilakukan dengan barang yang sudah ada?','t6-menata-meja-belajar-kecil-supaya-tetap-nyaman','2026-10-20 22:00:00');
INSERT IGNORE INTO threads (id,user_id,category,title,body,upload_dir,created_at) VALUES ('t7','nisa','Memasak','Bahan makanan apa yang selalu kalian stok di kos?','Mau mulai masak lebih rutin, tapi belum tahu bahan dasar apa saja yang benar-benar sering terpakai.

Di kamar ada rak kecil untuk bahan makanan. Aku berencana belanja sedikit dulu supaya bisa tahu kebutuhannya.

Kalian biasanya menyediakan bahan apa? Dan bagaimana mengatur jumlah belanjanya?','t7-bahan-makanan-apa-yang-selalu-kalian-stok-di-kos','2026-10-20 21:00:00');
INSERT IGNORE INTO threads (id,user_id,category,title,body,upload_dir,created_at) VALUES ('t8','rani','Kebersihan','Jadwal bersih-bersih kamar yang realistis saat kuliah padat','Sering menunda sampai akhir pekan, lalu ternyata tugas sudah menumpuk. Mau coba membagi pekerjaan sedikit setiap hari.

Aku ingin membuat jadwal ringan, seperti merapikan meja setelah belajar dan menyapu sebentar sebelum berangkat.

Ada kebiasaan kecil yang membuat kamar kalian tetap rapi tanpa perlu bersih-bersih besar setiap minggu?','t8-jadwal-bersih-bersih-kamar-yang-realistis-saat-kuliah-padat','2026-10-20 20:00:00');
INSERT IGNORE INTO threads (id,user_id,category,title,body,upload_dir,created_at) VALUES ('t9','adit','Perawatan Barang','Menyimpan kabel dan charger tanpa jadi satu gulungan besar','Setiap mau mengambil satu kabel, yang lain ikut tertarik. Bagaimana kalian mengatur kabel di laci kecil?

Ada kabel laptop, ponsel, dan beberapa kabel cadangan. Mau merapikan semuanya dengan pemisah sederhana.

Lebih enak pakai kantong terpisah atau pengikat kabel? Ceritakan cara kalian, ya.','t9-menyimpan-kabel-dan-charger-tanpa-jadi-satu-gulungan-besar','2026-10-20 19:00:00');
INSERT IGNORE INTO threads (id,user_id,category,title,body,upload_dir,created_at) VALUES ('t10','salsa','Hemat Pengeluaran','Membagi uang belanja mingguan dan kebutuhan mendadak','Lagi belajar menyusun anggaran sendiri. Kadang kebutuhan seperti isi ulang air atau beli alat tulis muncul bersamaan.

Aku mencoba memisahkan uang makan, transportasi, dan kebutuhan kos. Namun masih suka mengambil dari bagian lain kalau ada pengeluaran mendadak.

Bagaimana cara kalian membuat pembagian yang tetap fleksibel?','t10-membagi-uang-belanja-mingguan-dan-kebutuhan-mendadak','2026-10-20 18:00:00');
INSERT IGNORE INTO threads (id,user_id,category,title,body,upload_dir,created_at) VALUES ('t11','me','Kebersihan','Biar cucian nggak menumpuk di sudut kamar','Mau mencari sistem sederhana untuk memisahkan pakaian yang perlu dicuci dan yang masih bisa dipakai.

Saat ini semuanya masuk keranjang yang sama, jadi sering lupa kapan terakhir mencuci.

Apakah kalian punya hari khusus untuk mencuci atau menyesuaikan jumlah pakaian?','t11-biar-cucian-nggak-menumpuk-di-sudut-kamar','2026-10-20 17:00:00');
INSERT IGNORE INTO threads (id,user_id,category,title,body,upload_dir,created_at) VALUES ('t12','dimas','Memasak','Ide bekal sederhana yang bisa disiapkan sebelum kuliah','Pagi biasanya mepet, jadi butuh menu yang persiapannya singkat dan alatnya tidak banyak.

Aku ingin mulai membawa bekal beberapa hari dalam seminggu. Pagi hari kira-kira ada waktu dua puluh menit sebelum berangkat.

Apa menu andalan kalian dan bagian mana yang biasa disiapkan sebelumnya?','t12-ide-bekal-sederhana-yang-bisa-disiapkan-sebelum-kuliah','2026-10-20 16:00:00');
COMMIT;
