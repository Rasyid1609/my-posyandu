# Posyandu Jeruk

Aplikasi Laravel + React/TypeScript/Inertia untuk satu Posyandu, dengan tiga menu bisnis dan empat jabatan. Pembinaan: Kota Madiun, Puskesmas Manguharjo Kota Madiun. Alamat Posyandu tidak disimpulkan dari alamat Puskesmas.

## Fitur aplikasi

- Login session, akun aktif, pembatasan percobaan login, logout, akun/izin oleh Ketua, dan perlindungan Ketua aktif terakhir.
- **Data Masyarakat:** identitas tunggal, keluarga/anggota/wali, NIK/KK/kontak opsional terenkripsi dan tersamar sesuai izin, riwayat status, episode hamil/nifas/menyusui bertanggal, pencarian/filter/pagination, grafik kelompok usia dari database.
- **Data Sasaran:** periode, kegiatan, pembentukan sasaran tanpa duplikasi, snapshot usia/gender/maternal, kehadiran, pelayanan sesuai kelompok, pengukuran mentah, hasil petugas, edukasi, rujukan dan tindak lanjut.
- Kunjungan rumah dengan beberapa warga dan petugas; keluarga, warga unik dan kejadian dihitung terpisah.
- Draft/finalisasi administratif; pembukaan koreksi menyimpan catatan final sebelumnya terenkripsi. Mutasi sumber melewati transaksi, row lock dan revision.
- **Data Rekapitulasi:** lima rekap aplikasi, kualitas data, perbandingan periode, pengajuan/pengembalian/penguncian, arsip versi, serta XLSX/PDF agregat sesuai sumber/versi/izin.
- Import CSV/XLSX dan foto privat, editor draft, verifikasi/commit, deteksi duplikasi, provenance dan rekonsiliasi sumber historis. Foto agregat tidak dibuat menjadi warga fiktif.
- Ringkasan lokal deterministik berlabel bukan AI. Adapter OpenAI dan OCR menggunakan queue, schema, validasi bukti, review, sumber/stale, batas permintaan dan privasi.
- Audit mutasi/akses; history Inertia terenkripsi; UI desktop/ponsel, mode gelap, keyboard, draft guard dan tabel bergulir.
- Dashboard shadcn/ui Nova: base Taupe, tema/grafik Orange, font isi dan heading Geist lokal, ikon Lucide, radius default, menu solid dengan accent subtle. Konfigurasi pada components.json dan resources/css/theme.css.

## Aturan sementara dan konfigurasi

Pengguna meminta program dilengkapi dengan batas usia sementara. Default:
bayi 0–11 bulan; balita 12–59 bulan; prasekolah 60–71 bulan; sekolah 6–14 tahun; remaja 15–18 tahun; dewasa 19–59 tahun; lansia 60 tahun ke atas. Ini konfigurasi operasional sementara, **bukan klaim SOP Dinkes**. Usia tidak diketahui/estimasi tetap terpisah. Pembagian 0–5 bulan dan ≥6 bulan pada rekap anak adalah subkelompok laporan, bukan identitas baru.

Ketua dapat membuat versi aturan baru di utilitas **Aturan usia**, dengan sumber dan batas bulan berurutan. Versi lama immutable. Periode baru memakai versi terbaru; periode/snapshot lama tetap memakai versinya. Saat SOP pasti tersedia, cocokkan definisi dan sumber sebelum membuat versi baru.

Aplikasi tidak mendiagnosis atau menafsirkan cutoff klinis yang belum ditetapkan. Hasil klinis/warna dicatat dari petugas dengan nama dan sumber penilaian. Hasil sensitif memerlukan grant tambahan. Finalisasi/penguncian adalah administratif.

Provider AI/OCR default nonaktif. Isi `.env` lokal: `OPENAI_API_KEY`, `OPENAI_MODEL`, `OPENAI_OCR_MODEL`, flag provider dan persetujuan pemrosesan pada `.env.example`. Jangan menaruh key di frontend atau chat. Nama model ditentukan operator sesuai akun; tidak ditebak oleh kode. Tanpa provider, pencatatan, rekap, ekspor, import manual dan ringkasan lokal tetap berjalan. Tidak ada panggilan berbayar nyata selama pengujian implementasi.

Penggunaan data nyata/produksi tetap memerlukan penetapan pengelola; `POSYANDU_REAL_DATA_APPROVED=false` dipertahankan. Tidak ada deployment publik dilakukan.

## Menjalankan lokal — Windows/Laragon

Lingkungan: PHP 8.5.5, Composer 2.10.3, Laravel 13.36.0, Node 22.22.0, PostgreSQL 17.6. Dependensi dikunci. Inertia Laravel 3.5.1/React adapter 3.9.1, React 19.3.1, Vite 8, Tailwind 4, Spatie Permission 8.

Database pengembangan yang diotorisasi: `127.0.0.1:5432/posyandu_jeruk`. Migrasi tambahan diterapkan tanpa menghapus data atau menimpa akun/`.env`/`APP_KEY`. Demo tidak di-seed ke database pengguna.

~~~powershell
$env:Path = 'C:\laragon\bin\nodejs\node-v22;' + $env:Path
composer install
npm.cmd ci
php artisan migrate --no-interaction
php artisan db:seed --class=AccessControlSeeder --no-interaction
php artisan permission:cache-reset
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8000
~~~

Untuk instalasi baru saja: salin `.env.example` jika `.env` belum ada, isi kredensial lokal, lalu `php artisan key:generate`. Jangan mengganti APP_KEY pada instalasi existing. Akun Ketua pertama dibuat lewat `php artisan posyandu:create-superadmin ketua@contoh.test --name="Nama Ketua"` dengan kata sandi melalui prompt privat. Contoh bukan akun bawaan produksi.

Untuk frontend development: `npm.cmd run dev`. Untuk job AI/OCR:
~~~powershell
php artisan queue:work --queue=default --tries=2 --timeout=60
~~~
Retry-after database 90 detik harus tetap lebih lama dari timeout worker/job. Muat ulang status job pada halaman analisis/import. Menyalakan worker tidak otomatis mengirim data; provider dan permintaan pengguna tetap diperlukan.

## Pengujian

~~~powershell
php artisan test --compact
php vendor/bin/phpunit --configuration phpunit.pgsql.xml
npm.cmd run typecheck
npm.cmd run lint
npm.cmd run build
npm.cmd run test:browser
php vendor/bin/pint --format agent
composer audit
npm.cmd audit
~~~

Suite default menggunakan SQLite in-memory. Suite PostgreSQL menggunakan transaksi rollback tanpa RefreshDatabase/migrate:fresh/truncate; jalankan hanya pada database pengembangan yang telah dimigrasikan. Browser memakai Edge headless, port 8013, SQLite sintetis baru di `storage/framework/testing`, dan server dihentikan setelah pengujian. Screenshot/unduhan sintetis berada di folder hasil uji itu. Hasil terbaru dan batas pengujian tercatat di [progress.md](docs/progress.md).

## Menjalankan via Docker

Sebagai alternatif pengembangan lokal, Docker Compose menjalankan PHP 8.5-FPM, PostgreSQL, Nginx, dan Vite. Source code dipasang ke container; perubahan frontend dipantau oleh Vite.

### Prasyarat

- Docker Desktop (macOS/Windows) atau Docker Engine dan Docker Compose plugin v2 (Linux).
- Git (jika belum clone repo).

Di macOS, instal dan jalankan Docker Desktop (contoh: `brew install --cask docker`). Pastikan perintah `docker compose version` berhasil.

### 1. Siapkan environment

```bash
cp .env.example .env
```

Isi `DB_PASSWORD` di `.env` dengan password lokal yang kuat dan jangan gunakan password produksi. Compose memakai nilai ini untuk user PostgreSQL `posyandu_user`; `DB_HOST` di container otomatis diarahkan ke service `db`.

### 2. Build image dan buat APP_KEY (instalasi baru)

```bash
docker compose build app
docker compose run --rm --no-deps app php artisan key:generate
```

Jalankan `key:generate` hanya untuk instalasi baru yang `APP_KEY`-nya masih kosong. Jangan mengganti `APP_KEY` pada instalasi yang sudah ada.

### 3. Jalankan layanan dan migrasi

```bash
docker compose up -d
docker compose exec app php artisan migrate --no-interaction
docker compose exec app php artisan db:seed --class=AccessControlSeeder --no-interaction
docker compose exec app php artisan permission:cache-reset
```

Jangan jalankan seeder demo pada database yang berisi data pengguna. Buat akun Ketua pertama secara interaktif agar password dimasukkan melalui prompt privat:

```bash
docker compose exec app php artisan posyandu:create-superadmin ketua@contoh.test --name="Nama Ketua"
```

Compose menjalankan layanan berikut:

- **app** – PHP-FPM; hanya dapat diakses dari jaringan Docker.
- **db** – PostgreSQL; port lokal opsional `5433` untuk alat database.
- **nginx** – aplikasi web di port **8000**.
- **frontend** – Vite dev server di port **5173**; `npm ci` berjalan di container.

### Perintah Docker sehari-hari

```bash
docker compose ps
docker compose logs -f app frontend
docker compose exec app sh
docker compose exec app php artisan migrate --no-interaction
docker compose exec app php artisan queue:work --queue=default --tries=2 --timeout=60
docker compose down
```

`docker compose down` menghentikan container tanpa menghapus volume database dan file. Jangan gunakan `docker compose down -v` kecuali memang ingin menghapus semua data Docker project ini.

### Akses aplikasi dan mode lokal

Buka **http://localhost:8000**. Untuk menjalankan tanpa Docker, ikuti bagian **Menjalankan lokal — Windows/Laragon**. Hindari menjalankan kedua mode sekaligus jika port 8000 atau 5173 sedang digunakan.

---

## Dokumentasi

- [Kebutuhan, keputusan, matriks izin](docs/requirements.md)
- [Arsitektur dan kamus data](docs/architecture.md)
- [Mapping dan rumus lima rekap](docs/report-mapping.md)
- [Panduan pengurus](docs/user-guide.md)
- [Operasional, provider, backup dan produksi](docs/operations.md)
- [Sumber SOP resmi yang ditelusuri](docs/sop-references.md)
