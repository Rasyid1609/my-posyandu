# Status implementasi — 16 Oktober 2026

## Dashboard Nova / Taupe / Orange

Permintaan tampilan diterapkan: shadcn/ui radix-nova, base Taupe, tema/grafik Orange, heading dan font Geist, ikon Lucide, radius default 0.625rem, menu default/solid dengan accent subtle. components.json mencatat konfigurasi registry/menu; resources/css/theme.css memusatkan token terang/gelap. Lima komponen resmi (Button, Card, Badge, Input, DropdownMenu) diadaptasi ke utilitas cn lokal dan Radix/CVA yang sudah terpasang. Font Geist variable dibundel lokal beserta OFL; dependensi aplikasi tidak berubah.

Kartu statistik, grafik, sidebar, breadcrumb, tombol, tabel/filter dan formulir berbagi token yang sama. Menu Kolom memakai Radix dengan checkbox dan dukungan keyboard. Sidebar aktif menggunakan warna netral halus, menu solid tanpa blur/transparansi. Filter kelompok pada ponsel dapat digeser mendatar.

Typecheck, ESLint dan production build lulus. Empat skenario browser pelayanan/keluarga/rumah/import/ekspor/analisis/izin lulus pada desktop dan ponsel. Dua skenario inti diulang setelah perbaikan pemeriksaan tabel di luar modal: **2/2 lulus**, termasuk pemuatan font Geist aktual, checkbox Kolom, Escape/fokus, pencatatan sampai review/kunci, mode gelap, dan tidak ada overflow halaman. Screenshot dashboard terang/gelap serta ponsel diperiksa. Database uji tetap terisolasi; tidak ada perubahan schema/data operasional pada pekerjaan tema ini. Setelah QA, pemisah baris pada kalimat kartu diganti teks biasa untuk mempertahankan spasi ketika tampilan menyempit.

---

## Pembaruan NIK, KK dan kontak opsional

Pengguna mempertanyakan tulisan “NIK/KK/kontak belum diperlukan”. Dokumen awal memang meminta ketiganya sebagai opsional; penafsiran sebelumnya terlalu membatasi. Form warga kini menerima NIK/kontak, dan keluarga menerima nomor KK untuk dipakai bersama melalui hubungan anggota. Bayi tetap boleh tanpa NIK; ID internal/nomor registrasi tetap dipakai. Nomor 16 digit disimpan sebagai teks, termasuk nol di depan. Nilai kosong pada edit mempertahankan data; penghapusan eksplisit mengirim null.

Penyimpanan terenkripsi pada kolom TEXT tersembunyi. NIK/KK memakai keyed HMAC lookup dan unique constraint termasuk arsip; nomor duplikat ditolak. Matriks operational-v3: 17 permission, Ketua 15, Sekretaris 11, Bendahara 1, Anggota 6. identity.reveal diwariskan Ketua; akun residents.view lain melihat nomor tersamar kecuali mendapat grant tambahan. Semua penulisan tetap perlu izin kelola identitas. Akses nomor lengkap diaudit tanpa nilai nomor. Validasi tidak menyimpan nomor dalam old input; tidak masuk rekap/ekspor agregat/AI.

Migrasi aditif 2026_10_16_020449 dan seeder matriks telah diterapkan ke PostgreSQL pengembangan setelah backup/restore dasar terverifikasi. Nilai legacy dipertahankan sebagai ciphertext dan kolom plaintext dikosongkan. Duplikat legacy menghentikan migrasi sebelum perubahan. Akun/.env/APP_KEY dipertahankan.

Verifikasi akhir: suite backend penuh **88 test / 691 assertion lulus**, termasuk dua uji migrasi legacy; PostgreSQL dengan transaksi rollback **3 test / 27 assertion lulus**; typecheck, lint, build dan Pint lulus. Uji migrasi memeriksa pemeliharaan nilai lama/nomor nol depan dan penghentian sebelum perubahan saat duplikat. Browser **6/6 lulus** pada desktop dan ponsel: input NIK/kontak, mempertahankan nilai saat edit, penghapusan eksplisit, nomor KK keluarga dan alur inti. Screenshot detail identitas desktop/ponsel telah diperiksa, tanpa overflow halaman. Suite browser memakai aset build terisolasi dari hot reload server Vite pengguna; server development existing dipertahankan.

---
## Status sebelum pembaruan identitas opsional

Program operasional lokal sudah diimplementasikan dengan database, bukan tampilan statis. Kebutuhan terkonfirmasi, konfigurasi sementara dan pengesahan yang masih terbuka dipisahkan pada requirements.md dan sop-references.md. Bagian checkpoint di bawah merupakan riwayat; status lama tentang fitur belum tersedia tidak mengalahkan status terbaru ini.

Fitur aktif meliputi login/empat jabatan/matriks operational-v2; warga/keluarga/wali/riwayat/episode maternal; aturan usia berversi; periode/kegiatan/sasaran otomatis/manual/kehadiran; pengukuran dan hasil petugas/edukasi/rujukan; kunjungan rumah multi-warga/petugas; draft/finalisasi/koreksi; lima rekap/komparasi/arsip immutable/XLSX/PDF; import CSV/XLSX/foto privat dengan review/commit/rekonsiliasi; analisis lokal dan adapter AI/OCR antrean. Snapshot sasaran dapat diperbarui eksplisit dengan alasan pada periode terbuka. Analisis mempunyai lingkup kelompok dan pembanding periode; perubahan salah satu sumber menandai stale.

## Verifikasi yang selesai

- Suite backend penuh: **81 test / 571 assertion lulus**.
- PostgreSQL pengembangan: **2 test / 18 assertion lulus**, transaksi rollback. Perintah langsung php vendor/bin/phpunit --configuration phpunit.pgsql.xml selesai exit 0.
- Browser Edge: **6/6 lulus**, desktop 1440×1000 dan ponsel 390×844. Alur inti, matriks izin, keluarga/maternal/kegiatan/pelayanan/rujukan/rumah/lima rekap/ekspor/analisis lokal/import/sumber historis berjalan menggunakan SQLite sintetis terpisah. Server uji ditutup setelah selesai.
- TypeScript, ESLint, production build dan Pint lulus. Browser tidak mencatat error aplikasi; screenshot desktop/ponsel telah diperiksa dan overflow ponsel diperbaiki.
- XLSX dibaca kembali memakai PhpSpreadsheet: angka numerik, sumber snapshot, tidak membocorkan nama warga, teks formula dinetralkan. Unduhan XLSX diuji lewat klik browser. PDF diuji melalui HTTP dengan sesi pengguna, header dan magic bytes; bukan klaim native PDF-download Edge headless. PDF hasil aplikasi dirender dan diperiksa secara visual; laporan rumah sintetis kini dua halaman tanpa baris terpotong. Sesudah perapian jarak tabel pada 16 Oktober, ExportTest kembali lulus **2 test / 15 assertion**, dan Pint lulus.
- AI dan OCR diuji dengan HTTP fake, termasuk respons tidak valid/instruksi pada data, hasil draft tetap perlu review, eligibility/izin/sumber/stale, deduplikasi dan komparasi. Tidak ada panggilan provider berbayar nyata.
- Backup PostgreSQL format custom dipulihkan ke database uji baru, dibandingkan jumlah enam tabel dan keberadaan aturan usia, lalu database uji dihapus. Sumber tidak ditimpa. Salinan privat terbaru tercatat pada operations.md; ini verifikasi dasar schema/data, bukan drill produksi penuh.

Empat migrasi aditif 065339, 071725, 073737 dan 082438 sudah diterapkan pada PostgreSQL pengembangan. AccessControlSeeder operational-v2 diterapkan. Tidak seed demo/warga pada PostgreSQL pengguna, tidak mengganti akun/password/.env/APP_KEY.

## Konfigurasi sementara dan batas penyelesaian

Batas awal temporary-2026-v1: bayi 0–11 bulan, balita 12–59, prasekolah 60–71, sekolah 6–14 tahun, remaja 15–18, dewasa 19–59, lansia 60+. Versi baru dapat dibuat melalui Aturan usia dan berlaku pada periode baru. Arsip tetap memakai versinya. Pengguna mengizinkan batas sementara ini; bukan pengesahan SOP Dinkes.

Seluruh alur lokal di atas tersedia. AI/OCR eksternal perlu key/model, konfigurasi provider, kebijakan pemrosesan dan worker; default nonaktif. SOP klinis/eligibility/cutoff resmi, penugasan klinis/sensitif, izin data nyata dan deployment publik tetap memerlukan penetapan ketika dokumen tersedia. Aplikasi mencatat hasil petugas dengan provenance tanpa menebak diagnosis. Tidak ada klaim pengesahan klinis atau kesiapan produksi.

---
# Riwayat checkpoint — 15 Oktober 2026

Pengguna mengizinkan batas kelompok usia sementara agar program dilengkapi sekarang dan diperbarui setelah SOP pasti tersedia. Implementasi aktif baru: keluarga/wali, episode maternal, age_rule_versions immutable dengan default batas bulan [12,60,72,180,228,720], sasaran otomatis idempotent dan snapshot usia/gender/maternal; formulir hasil petugas lintas kelompok, edukasi, rujukan/tindak lanjut; koreksi final terenkripsi; kunjungan rumah multi-warga/petugas; lima rekap service-recap-v1; snapshot mencakup rekap; pilih arsip/komparasi; ekspor XLSX/PDF; analisis lokal dan adapter AI antrean; import CSV/XLSX/foto privat, review/commit, legacy source selection; OCR antrean opsional; kegiatan periode.

Batas default: bayi 0–11 bulan, balita 12–59, prasekolah 60–71, sekolah 6–14 tahun, remaja 15–18, dewasa 19–59, lansia 60+. Sementara, bukan SOP Dinkes. Tidak ada diagnosis otomatis; hasil klinis diinput dari petugas dengan provenance. Matriks operational-v2: Ketua 14 permission, Sekretaris 11, Bendahara 1, Anggota 6; 16 permission terdaftar. sensitive.view/sensitive.record hanya grant tambahan, tidak diwariskan otomatis.

Verifikasi terakhir: 75 tests / 509 assertions lulus, typecheck/lint/build lulus. Tiga migrasi aditif 065339, 071725, 073737 sudah diterapkan pada PostgreSQL pengembangan dan AccessControlSeeder operational-v2 dijalankan. Tidak seed demo/warga atau mengganti akun/password/.env/APP_KEY. API nyata tidak dipanggil; AI/OCR memerlukan konfigurasi dan kebijakan pemrosesan di config/analysis.php, default nonaktif. Dua job diuji via HTTP fake untuk analisis; OCR live belum diuji.

Daftar pekerjaan pada checkpoint ini telah diselesaikan sebagaimana status terbaru di atas. Angka pengujian dan pernyataan implementasi pada bagian riwayat berikut dipertahankan sebagai catatan tahap sebelumnya.

---
# Checkpoint proyek — 15 Oktober 2026

## Arahan SOP terbaru

Pengguna mengonfirmasi **Kota Madiun, Puskesmas Manguharjo Kota Madiun**. Hasil penelusuran resmi dan pemetaan kebutuhan diperbarui pada docs/sop-references.md. Lampiran SK 440-401.103.3/20/2025 berhasil diperoleh: halaman PDF 1–4, 12–32 dan 75 dibaca; halaman 4, 13, 27 dan 30 diperiksa secara visual. Bukan klaim seluruh 75 halaman ditelaah. Alur fasilitas dan kewenangan dipetakan terhadap fitur existing/backlog.

Katalog resmi mencantumkan dokumen Puskesmas pada Mei 2026; isinya belum diperoleh dan belum diasumsikan menggantikan SK 2025. SOP/formulir Posyandu rinci, batas usia, eligibility, ambang/skor, aturan hitung dan penugasan klinis masih perlu diverifikasi. Konfirmasi kota/Puskesmas tidak menetapkan RT/RW, kelurahan atau alamat Posyandu. Matriks administratif tetap aktif. Langkah ini hanya memperbarui dokumentasi: kode, database dan flag klinis/data nyata tidak berubah; pengujian aplikasi tidak diulang. Kota/Puskesmas sudah terjawab dan tidak perlu ditanyakan ulang.

## Pembaruan terakhir: aktivasi matriks izin

Pengguna meminta aktivasi SOP dan matriks izin. Setelah pertanyaan tentang SOP, pengguna memilih akan memberikan SOP klinis yang disahkan. Karena itu SOP/klasifikasi klinis tetap nonaktif; tidak ada ambang atau kategori umur yang dibuat sendiri. Alur administratif existing tetap berjalan, tanpa diklaim sebagai SOP klinis yang disahkan.

Matriks administratif operational-v1 dari dokumen telah diaktifkan pada config/posyandu.php dan tabel role/permission PostgreSQL pengembangan. Ketua mendapat 10 permission administratif, Sekretaris 8, Bendahara 1 (agregat), Anggota 6 (pencatatan tanpa finalisasi/pengajuan/persetujuan). Tidak ada role baru. Data nyata dan izin klinis tidak diaktifkan. Akun existing tidak diganti atau dibuat ulang.

AccessControlSeeder menyinkronkan baseline role dalam transaksi, mempertahankan izin langsung akun, mencatat perubahan sebagai permission_matrix.applied, dan tidak menggandakan audit ketika konfigurasi yang sama diterapkan ulang. Cache permission telah direset. Pemeriksaan permission:show pada PostgreSQL sesuai matriks. Halaman Akun & izin menampilkan tabel matriks aktif dan membedakan izin bawaan jabatan dari tambahan akun. Izin bawaan tidak dapat dicabut melalui checkbox akun; pengurangan baseline dilakukan dengan pergantian jabatan/nonaktif akun.

Verifikasi terbaru: suite backend **47 test / 321 assertion lulus**; PostgreSQL integration **2 test / 18 assertion lulus** dengan rollback; typecheck, lint, build, dan Pint lulus. Uji browser **4/4 lulus** untuk matriks/pewarisan izin akun dan alur inti pada desktop serta ponsel. Screenshot halaman matriks telah diperiksa. Browser memakai database sintetis terpisah dan test server ditutup otomatis. Tidak ada dependensi baru atau perubahan schema pada aktivasi ini.

Bagian di bawah adalah checkpoint awal, disimpan sebagai riwayat. Pernyataan lama tentang matriks yang belum diterima sudah digantikan oleh aktivasi di atas. Langkah berikutnya adalah membaca SOP klinis yang akan diberikan, memverifikasi sumber/versi dan kewenangan, baru mengimplementasikan aturan serta pengujiannya.

## Objective dan keputusan

Dokumen kebutuhan dibaca sampai akhir dan lima foto diperiksa. User meminta audit existing, pemisahan kebutuhan/usulan/pertanyaan dan implementasi bertahap dengan pengujian, dimulai fondasi + satu alur aman. Tidak ditetapkan bahwa keseluruhan aplikasi harus dianggap selesai dalam tahap pertama.

Keputusan user: SOP belum ada, lanjutkan fondasi aman; matriks izin akan diberikan; database pengembangan adalah 127.0.0.1:5432/posyandu_jeruk. Matriks belum dikirim. Non-Ketua tidak diberi permission otomatis. Data nyata belum diaktifkan.

## Sudah diimplementasikan

Tahap 1 discovery dan dokumentasi; tahap 2 fondasi; bagian identitas tahap 3; satu alur operasional umum tahap 4 dengan awal review/snapshot tahap 6. Bukan lima formulir klinis lengkap.

Alur: login session → warga tambah/edit/arsip → periode → sasaran manual beserta umur snapshot/alasan → kehadiran → pengukuran mentah draft/edit/final administratif → rekap agregat → diajukan/dikembalikan/disetujui-kunci → buka kembali beralasan → versi baru. Seluruhnya memakai database.

Empat role tanpa Wakil; tiga menu bisnis; akun/izin pada utilitas. Permission eksplisit dicek backend, policy identitas, akun aktif, rate limiting login, last-superadmin guard. NIK/KK/kontak tidak diinput/dikirim frontend. Audit mutasi dan akses detail tanpa menyalin pengukuran/password. History Inertia terenkripsi dan dibersihkan saat logout, DevTools props nonaktif. Form warga/layanan/akun memiliki perlindungan draft; warga dan periode memakai revision dengan respons konflik 409.

Null, tidak diperiksa, belum diketahui, tidak berlaku, menolak dan diperiksa dibedakan; nilai diharuskan hanya jika diperiksa. Tidak ada klasifikasi klinis otomatis. Kunjungan final dihitung terpisah dari peserta unik. Penyebut nol menghasilkan null. Source mutation menolak periode submitted/locked melalui service transaksi yang dipakai endpoint. Snapshot tidak punya endpoint edit/delete dan model menolak update/delete. Perubahan source setelah reopen menggunakan revision baru dan tidak menimpa snapshot lama.

Frontend responsive, light/dark, angka padanan chart, pencarian/filter/pagination server, toggle kolom, sticky header/scroll tabel, label/error field, flash, empty state dan navigasi ponsel dengan fokus/Escape.

## Database dan lingkungan

Laravel 13.36.0, PHP 8.5.5, Composer 2.10.3, Node 22.22.0 (path Laragon di README), PostgreSQL 17.6. React 19.3.1, Inertia client 3.9.1 / Laravel adapter 3.5.1. Dependensi dikunci. Laravel Boost 2.10.3 diinstal sesuai AGENTS.md, panduan dihasilkan dan dibaca ulang. Boost MCP belum tersedia sebagai callable tool dalam sesi; memakai Artisan, source terpasang dan dokumentasi resmi.

Database PostgreSQL awal memiliki 0 tabel; enam migration dan seed konfigurasi berhasil. 25 tabel tersedia, empat role, sepuluh permission, satu profil bernama Posyandu Jeruk. Data warga, akun, target, kunjungan, audit dan snapshot tetap 0 setelah integrasi rollback. Tidak menimpa .env, tidak mencetak rahasia, tidak menjalankan migrate:fresh/truncate, tidak seed demo ke PostgreSQL.

Default alamat/jumlah keluarga awal dinetralkan lewat migration aditif; nilai existing tidak ditimpa. Migration awal hanya dirapikan Pint (termasuk BOM), semantik dipertahankan. Folder awal tidak mempunyai Git. Pint --dirty tidak tersedia; formatter penuh dipakai. Composer classmap awal sangat lambat: autoload sempat dibuat tanpa optimasi untuk menyelesaikan setup; konfigurasi optimize-autoloader dikembalikan true.

## Sudah diuji

- Suite default SQLite in-memory: **37 test, 270 assertion lulus**.
- PostgreSQL integration: **1 test, 12 assertion lulus**, fixture dalam transaksi rollback tanpa migrasi ulang/drop data. Meliputi decimal, relasi, permission, pencatatan, finalisasi, review dan snapshot JSON.
- Root redirect test dijalankan kembali setelah penamaan diperbaiki: 1 test lulus.
- TypeScript: npm run typecheck lulus.
- ESLint: npm run lint lulus.
- Production asset build: npm run build lulus.
- Pint: php vendor/bin/pint --format agent lulus (tidak tersedia mode dirty tanpa Git).
- Composer validate lulus.
- Composer audit: tidak ada security vulnerability advisory.
- npm audit: 0 vulnerabilities.
- Browser Edge headless: desktop 1440×1000 dan viewport ponsel 390×844 sebelumnya **2 test lulus**, termasuk alur end-to-end, edit draft, dark mode, error JavaScript dan halaman tidak overflow horizontal. Screenshot desktop/ponsel telah diperiksa secara visual.
- Pemeriksaan browser akhir: **2/2 lulus** (desktop dan ponsel), termasuk keyboard Tab, Escape/fokus menu, logout dan back-navigation. Tidak ada error JavaScript. Proses uji selesai; server fixture ditutup otomatis.

Cakupan backend meliputi role tanpa izin, hak eksplisit, pemisahan agregat/identitas, larangan probe nama, IDOR parent-child, akun nonaktif, rate limiting, superadmin terakhir, duplikat calon warga, arsip tanpa hapus riwayat, snapshot umur, target idempotent, null vs nol/status, edit draft/final immutable, batas periode/tanggal WIB, denominator nol, 10 hadir/6 diperiksa, 11 kunjungan/10 peserta, konflik revision, source locked, reopen/versi laporan dan snapshot immutable.

Browser menggunakan SQLite baru di storage/framework/testing, bukan PostgreSQL pengguna. Test server dimatikan otomatis Playwright. Screenshot berada dalam storage/framework/testing/browser-results. Semua data screenshot sintetis.

## File penting

- routes/web.php; config/posyandu.php; config/inertia.php.
- app/Services/ResidentRegistry.php, ServiceRecording.php, PeriodWorkflow.php, OperationalReport.php, AuditTrail.php.
- app/Http/Requests; app/Policies/ResidentPolicy.php; app/Http/Middleware.
- Migration hardening 2026_10_15_042857; Models; AccessControlSeeder/DatabaseSeeder/DemoSeeder.
- resources/js/Pages; components/Layout.tsx, ui.tsx, useDraftGuard.ts; resources/css/app.css.
- tests/Feature/CoreWorkflowTest.php, AccessControlTest.php, ReportingTest.php.
- tests/Integration/PostgreSqlWorkflowTest.php; phpunit.pgsql.xml.
- tests/Browser; playwright.config.ts.
- README.md; requirements.md; architecture.md; report-mapping.md; user-guide.md; operations.md.

## Masih perlu konfigurasi / keputusan material

1. Matriks izin organisasi dan pemegang izin melihat/mengelola data nyata; penanggung jawab klinis.
2. SOP pembagian usia termasuk konflik header bayi 0–6/0–5 bulan, definisi eligibility, ambang dan versi sumber.
3. SOP layanan, koreksi final, lima laporan resmi, denominator, pengesahan dan data pemrosesan/retensi.
4. Akun Ketua pertama di PostgreSQL dibuat pengguna lewat posyandu:create-superadmin dengan kata sandi prompt privat.
5. Produksi, backup/restore drill, load/concurrency lintas proses dan review perangkat nyata belum diuji.

## Backlog tahap selanjutnya

Keluarga/wali dan riwayat maternal; definisi kategori berversi; kegiatan/generate sasaran sesuai SOP; perluasan kategori pelayanan; edukasi/suplemen/imunisasi/rujukan/follow-up; kunjungan rumah keluarga-warga-petugas terpisah; lima registry rekap resmi dan eligibility klinis; koreksi layanan final berversi; ekspor XLSX/PDF privat dan formula injection guard; perbandingan periode; AI adapter/queue/privacy/evidence/review/stale; import CSV/XLSX/legacy/OCR draft dan rekonsiliasi; hardening/backup/restore/handoff produksi.

Deteksi duplikat identitas saat ini heuristik nama/tanggal lahir/jenis kelamin; belum memakai NIK atau workflow resolusi false positive. Status riwayat sudah dicatat tetapi penentuan sasaran retroaktif dari status historis belum diotomatisasi. Tidak ada API import/job mutasi sumber; tahap berikutnya wajib menggunakan PeriodWorkflow dan authorization yang sama. Jangan menambahkan penulisan Eloquent/bulk SQL yang melewati guard service. Pengujian konkurensi benar-benar simultan belum dilakukan.

Tidak ada hasil AI/OCR fiktif, pengiriman ke provider, publikasi, klaim laporan klinis resmi, atau klaim kepatuhan hukum. Lanjutkan dari checkpoint ini; jangan membangun ulang skeleton.
