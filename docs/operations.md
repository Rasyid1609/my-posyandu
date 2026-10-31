# Operasional dan konfigurasi

## Lingkungan

Aplikasi operasional lokal memakai PostgreSQL pengembangan 127.0.0.1:5432/posyandu_jeruk. Akun existing, password dan APP_KEY dipertahankan. Lima migrasi perluasan layanan/import/AI/kegiatan/komparasi/identitas terenkripsi telah diterapkan secara aditif; tidak memakai migrate:fresh/truncate dan tidak seed data demo pada database pengguna.

Pengujian browser memakai VITE_HOT_FILE terpisah yang tidak ada, sehingga memuat aset build dan tidak tersambung ke hot reload Vite pengguna. Server development existing tetap berjalan dengan public/hot default; .env pengguna tidak diubah. Jalankan build sebelum suite browser.

POSYANDU_REAL_DATA_APPROVED=false tetap menandai pengembangan. APP_ENV=production menolak penggunaan sampai pengelola menetapkan izin. Flag bukan bukti kepatuhan atau pengganti SOP/penugasan. Belum ada deployment publik, HTTPS produksi, load test produksi atau review hukum/prosedural.

## Permission dan pemulihan

AccessControlSeeder menyinkronkan operational-v3 dalam transaksi, mempertahankan grant langsung dan mencatat perubahan tanpa menggandakan audit identik. Sesudah perubahan config izin: config:clear, db:seed --class=AccessControlSeeder, permission:cache-reset. Izin identity.reveal diwariskan Ketua; tambahan akun lain ditetapkan lewat Akun & izin. Tidak ada registrasi publik/reset email tanpa jalur pemulihan yang tersedia. Ketua aktif terakhir dilindungi.

sensitive.view dan sensitive.record diberikan terpisah bila ada penugasan. Jangan menjadikan Ketua/sekretaris sebagai bukti profesi klinis. Audit memuat aksi/ID/pelaku/waktu/alasan administratif; hindari menulis temuan kesehatan pada alasan audit.

## AI dan OCR opsional

Konfigurasi backend ada pada config/analysis.php; contoh nama environment pada .env.example. Default AI/OCR dan persetujuan pemrosesan nonaktif. Tidak ada key/model di frontend, log atau dokumentasi. Pilih model yang tersedia pada akun dan mendukung schema/vision yang dibutuhkan; kode tidak menganggap suatu model tersedia.

- POSYANDU_AI_ENABLED: mengizinkan antrean analisis setelah key/model/persetujuan pemrosesan lengkap.
- POSYANDU_OCR_ENABLED: mengizinkan antrean OCR setelah key/model vision/persetujuan lengkap.
- POSYANDU_PROVIDER_PROCESSING_APPROVED: keputusan operator tentang pemrosesan eksternal, terpisah dari izin data nyata.
- OPENAI_API_KEY, OPENAI_MODEL, OPENAI_OCR_MODEL: disimpan hanya di .env/pengelola rahasia.
- POSYANDU_AI_MINIMUM_CELL_SIZE: minimum lima; konfigurasi tidak dapat menurunkan batas di bawah lima.
- POSYANDU_AI_DAILY_LIMIT: default lima, dibatasi maksimum dua puluh.

Analisis dikirim hanya melalui tindakan pengguna dan evidence agregat allowlist. Bila populasi/evidence positif terlalu kecil, seluruh permintaan ditolak agar tidak memberi total pembanding yang dapat mengungkap kelompok kecil. Tidak mengirim nama, tanggal lahir, alamat, NIK/KK/kontak atau catatan bebas. Agregat bukan otomatis anonim; review kebijakan organisasi tetap diperlukan.

OCR berbeda: pengguna memilih foto dan mencentang persetujuan pengiriman. Foto mungkin mengandung identitas/nama kader; operator harus memastikan data yang diproses memang diizinkan dan meminimalkan informasi yang tidak diperlukan. Prompt menganggap gambar sebagai data, mengabaikan instruksi di dalamnya, membatasi kode indikator, dan mempertahankan null. Hasil tetap draft, tidak auto-commit.

Responses API menggunakan schema ketat, store=false, tanpa tools/akses SQL, max output dan timeout. store=false tidak berarti jaminan Zero Data Retention; kebijakan pemrosesan/retensi akun harus diperiksa. Referensi: [Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs), [Data controls](https://developers.openai.com/api/docs/guides/your-data), [Images and vision](https://developers.openai.com/api/docs/guides/images-vision).

Worker:
~~~powershell
php artisan queue:work --queue=default --tries=2 --timeout=60
~~~

Database retry_after 90 detik melebihi timeout job 60 detik. Setiap job memakai lock/deduplication key, memeriksa lagi akun/izin/sumber, dan mencatat status. Retry otomatis provider dibatasi pada HTTP 429; kegagalan koneksi/timeout ambigu tidak diulang otomatis untuk mengurangi risiko panggilan berbayar ganda. Jangan menganggap header idempotency sebagai jaminan billing provider. Periksa failed_jobs sebelum retry manual.

Provider tidak dipanggil setiap halaman dimuat. Analisis dapat memilih operasional, ringkasan semua kelompok atau satu kelompok, serta pembanding periode dengan versi usia/mapping/sumber yang sebanding. Evidence maksimum 250 baris/60 KB. Analisis identik memakai ulang run yang sama. Perubahan periode utama atau pembanding menandai stale; review hasil lama ditolak. Hasil asli/revisi peninjau dipisahkan. Narasi provider dengan bukti asing atau angka digit yang tidak berasal dari renderer backend ditolak. Ini tidak menjamin semua interpretasi benar; review manusia tetap diperlukan.

Pengujian provider menggunakan HTTP fake dan tidak membuat panggilan berbayar nyata. Kredensial, kuota, perilaku model aktual, jaringan eksternal dan kebijakan retensi akun belum diuji secara live. Tanpa provider, semua pencatatan, rekap/ekspor, import manual dan ringkasan lokal tetap berfungsi.

## File, impor dan ekspor

Private disk lokal: storage/app/private. File tidak masuk public/storage. Sumber import memerlukan auth/izin/parent binding dan signature 15 menit; response private/no-store/nosniff. JPG/PNG/WebP divalidasi tipe/ekstensi/ukuran/piksel. XLSX dibatasi isi ZIP, jumlah berkas/sheet/baris/kolom, tidak menjalankan formula atau membaca URL arbitrer. CSV memakai header template dan maksimum 500 baris.

Draft import terenkripsi dengan APP_KEY. Commit satu transaksi, checksum/keys mencegah penggandaan dan calon warga duplikat ditolak. Legacy agregat dipisahkan dari transaksi individu; hanya satu sumber periode/jenis dipilih dengan alasan. Submitted/locked menolak commit/rekonsiliasi.

Ekspor agregat memakai ReportData yang sama dengan tampilan dan snapshot. Izin ekspor terpisah, indikator sensitif disaring, teks spreadsheet berawalan formula dinetralkan, PDF tidak memuat resource remote. Tidak ada ekspor identitas yang dibuka diam-diam.

Koreksi final individu/rumah menyimpan versi sebelumnya terenkripsi. Arsip warga/keluarga bukan penghapusan permanen. Retensi berkas, backup/audit, penghapusan terkontrol dan insiden perlu kebijakan pengelola; belum ada penjadwalan penghapusan otomatis yang diasumsikan.

NIK/KK/kontak opsional memakai encrypted cast dan hidden attributes; nomor identitas memakai keyed HMAC terpisah untuk lookup duplikasi dengan unique constraint. Jangan mengganti APP_KEY tanpa prosedur rotasi yang juga menghitung ulang lookup hash. Migrasi 2026_10_16_020449 memindahkan nilai plaintext legacy ke ciphertext, mempertahankan data dan mengosongkan kolom legacy. Duplicate legacy menghentikan migrasi sebelum perubahan schema/data; periksa secara privat. down() sengaja menolak pembalikan otomatis agar tidak menghapus ciphertext atau mengembalikan nomor ke plaintext. Gunakan forward fix/restore terkontrol.

## Backup dan restore

Uji dasar backup/restore PostgreSQL telah dijalankan 15 Oktober 2026: pg_dump format custom dari database pengembangan, restore ke database baru acak dengan prefix posyandu_restore_test_, pembandingan jumlah migrations/users/residents/report_snapshots/home_visits/import_batches serta keberadaan aturan usia. pg_restore selesai tanpa error. Database restore sementara dihapus sesudah verifikasi; database sumber tidak ditimpa.

Salinan lokal privat setelah migrasi komparasi: backups/development-20261015-090134.dump, SHA-256 516ce8d0868b461ea5b85f3532314e126f7ce3af2ac5a0facc8e30c5c4226934. Nama berkas mencatat timestamp UTC dari proses backup. Folder backups dikecualikan dari source. Tidak dilampirkan ke chat karena memuat data akun. Ini verifikasi dasar schema/data, bukan drill pemulihan produksi lengkap atau pengujian login dengan kata sandi pengguna.

Sebelum migrasi identitas 16 Oktober 2026, backup/restore dasar diulang: backups/development-20261016-021742.dump, SHA-256 9f71862c9294201ec717031ea8e607f92c2410e0e230a1f0ffadfe74d3c2b0fa. Salinan ini merupakan keadaan sebelum schema identitas terenkripsi. Database restore acak dihapus setelah verifikasi tanpa menimpa sumber. Tetap perlukan backup APP_KEY dan private uploads secara terpisah.

Untuk backup manual gunakan pg_dump dan prompt privat:
~~~powershell
New-Item -ItemType Directory -Path backups -Force
& 'C:\Program Files\PostgreSQL\17\bin\pg_dump.exe' -h 127.0.0.1 -p 5432 -U postgres -Fc -f backups/posyandu_jeruk.dump posyandu_jeruk
~~~
Jangan menaruh kata sandi pada argumen/chat. Simpan backup di lokasi terkontrol/terenkripsi. Cadangkan juga private uploads dan simpan APP_KEY secara terpisah; kehilangan/mengganti key dapat membuat koreksi/draft import tidak terbaca. Backup DB saja tidak mencadangkan file sumber.

Restore hanya ke database baru khusus restore, tanpa --clean pada database sumber. Setelahnya periksa schema/relasi/jumlah record, dekripsi sampel yang diizinkan, dan alur aplikasi pada lingkungan pemulihan. Uji RPO/RTO, backup off-device, pemulihan akun dan insiden produksi masih perlu pengelola.

## Deployment dan perubahan SOP

Untuk instalasi/update: dependency sesuai lockfile, build aset, backup, migrasi aditif, seeder izin, reset cache, kemudian uji alur. Jangan menimpa .env atau mengganti APP_KEY. Gunakan akun DB berizin minimum, HTTPS, debug=false dan secure cookies untuk produksi.

down() migrasi perluasan menghapus data/kolom modul baru dan bersifat destruktif. Gunakan forward fix atau restore yang telah diverifikasi, bukan rollback schema pada data operasional. Folder awal belum mempunyai metadata Git; version control/review rilis perlu disiapkan.

SOP usia baru dibuat sebagai versi immutable di utilitas Aturan usia. SOP klinis/cutoff/formulir baru memerlukan mapping, pengujian batas, penugasan dan metadata sumber. Tidak menghitung ulang arsip terkunci secara diam-diam.

Kesiapan yang masih memerlukan penetapan/uji lingkungan: data nyata/retensi, SOP klinis rinci, kredensial/provider live, produksi/HTTPS, load/konkurensi lintas proses, pembaca layar/perangkat fisik, dan drill pemulihan produksi lengkap. Hasil test aktual ada di progress.md.
