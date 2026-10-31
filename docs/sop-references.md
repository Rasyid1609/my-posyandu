# Acuan SOP kesehatan dan konfigurasi sementara

## Keputusan implementasi terbaru

Pengguna meminta program dilengkapi sekarang dengan batas usia sementara dan diperbarui setelah dokumen pasti tersedia. Implementasi kini aktif: versi usia immutable, pengelompokan otomatis/snapshot, pencatatan hasil petugas, lima rekap service-recap-v1 dan matriks administratif operational-v2. Aturan awal temporary-2026-v1 memakai batas eksklusif bulan [12,60,72,180,228,720]; lihat [pemetaan aktif](report-mapping.md). Persetujuan pengguna ini adalah dasar konfigurasi sementara, bukan pengesahan klinis Dinkes/Puskesmas.

Hasil klinis tetap berasal dari petugas dengan nama/sumber; aplikasi tidak mengeksekusi cutoff diagnosis yang belum ditetapkan. Izin sensitif merupakan grant tambahan. Data nyata/produksi dan pemrosesan provider eksternal belum diaktifkan. Catatan sumber di bawah mempertahankan batas telaah dokumen, sedangkan status implementasi lama digantikan oleh keputusan ini.

Tanggal penelusuran: 15 Oktober 2026. Pengguna mengonfirmasi pembinaan **Kota Madiun, Puskesmas Manguharjo Kota Madiun**. RT/RW, kelurahan dan alamat Posyandu belum dikonfirmasi. Alamat fasilitas Puskesmas dan default migration tidak dipakai sebagai alamat Posyandu.

## Sumber resmi yang ditemukan

| ID | Sumber | Pemeriksaan yang dilakukan | Status untuk aplikasi |
| --- | --- | --- | --- |
| national-ilp-2015-2023 | [KMK HK.01.07/MENKES/2015/2023 tentang Petunjuk Teknis Integrasi Pelayanan Kesehatan Primer](https://kesprimkom.kemkes.go.id/konten/126/176/0/nomor-hk-01-07-menkes-2015-2023) | Identitas dokumen dan publikasi resmi Kemenkes diverifikasi. Bagian Posyandu pada PDF ditelusuri sebagai konteks pelayanan siklus hidup dan jejaring pembinaan; seluruh 264 halaman belum ditelaah. | Acuan nasional awal, bukan bukti bahwa aturan lokal atau seluruh cutoff sudah terverifikasi. Perlu memeriksa status/perubahan yang berlaku sebelum aktivasi aturan. |
| national-posyandu-management | [Buku Panduan Pengelolaan Posyandu Bidang Kesehatan](https://ayosehat.kemkes.go.id/buku-panduan-pengelolaan-posyandu-bidang-kesehatan) | Halaman publikasi resmi tersedia. Tanggal halaman 1 Januari 2026 tidak diasumsikan sebagai tahun edisi isi buku. | Referensi untuk telaah alur dan pencatatan; isi lengkap dan versi dokumen yang dipakai Puskesmas perlu dicocokkan. |
| national-cadre-skills | [Buku Panduan Keterampilan Dasar Kader Posyandu Bidang Kesehatan](https://ayosehat.kemkes.go.id/buku-panduan-keterampilan-dasar-kader-posyandu-bidang-kesehatan) | Halaman publikasi resmi tersedia. Isi lengkap belum ditelaah. | Referensi calon untuk pembagian kegiatan kader dan tenaga kesehatan; belum dijadikan kewenangan klinis otomatis. |
| local-dinkes-2025 | [SK standart Pelayanan 2025 — Dinkes PPKB Kota Madiun](https://dinkes.madiunkota.go.id/sk-standart-pelayanan-2025/) | Halaman resmi dan tautan PDF ditemukan; lampiran belum ditelaah. | Standar pelayanan Dinas tidak langsung menjadi SOP klinis Posyandu. |
| local-puskesmas-20-2025 | [Standar Pelayanan Publik UPTD Puskesmas Manguharjo](https://puskesmasmanguharjo.madiunkota.go.id/?p=3010), [PDF resmi](https://puskesmasmanguharjo.madiunkota.go.id/wp-content/uploads/2024/06/SK-STANDAR-PELAYANAN-PUBLIK-Manguharjo-2025-1.pdf) | PDF 75 halaman berhasil diperoleh. Halaman 1–4, 12–32 dan 75 dibaca; halaman 4, 13, 27 dan 30 juga diperiksa secara visual. Bukan telaah seluruh lampiran. | Acuan lokal untuk pemetaan alur fasilitas dan kewenangan; belum cukup untuk mengaktifkan semua indikator klinis Posyandu. |
| local-puskesmas-ilp | [Posyandu ILP di Kelurahan Manguharjo](https://puskesmasmanguharjo.madiunkota.go.id/?p=2828) | Publikasi resmi 18 Oktober 2024 menjelaskan lima kelompok siklus hidup. | Konteks kegiatan setempat, tanpa batas usia numerik atau penetapan lokasi Posyandu Jeruk. |
| local-catalog-2026 | [Katalog Open Data Kota Madiun](https://opendata.madiunkota.go.id/dokumen) | Entri standar Puskesmas Manguharjo bertanggal 13 Mei 2026 ditemukan. Isinya belum diperoleh; unduhan memerlukan formulir kontak yang tidak dikirim. | Tanggal katalog bukan bukti versi/tanggal berlaku. Perlu dicocokkan dengan SK 2025; belum mengklaim SK 2025 pasti paling mutakhir. |

Lima foto merupakan referensi formulir yang harus dicocokkan terhadap sumber resmi. Tidak menganggap header foto, tag kelompok usia pada situs, tanggal unggah, maupun hasil pencarian sebagai aturan klinis yang siap dieksekusi.

## Hasil telaah lokal dan pemetaan kebutuhan

SK Kepala UPTD Puskesmas Manguharjo **440-401.103.3/20/2025**, ditetapkan **31 Januari 2025**, memuat standar pelayanan publik. Halaman PDF 4 menyatakan berlaku sejak ditetapkan dan mencabut SK 440-401.103.3/73/2024. Status perubahan setelah penerbitannya masih perlu dicocokkan dengan dokumen 2026. Folder unggahan URL `2024/06` bukan tanggal berlaku dokumen. SHA-256 berkas yang diperiksa: `e0f973238a8f838b3ba1c8dcbf3a2389f6bf1a639ab5b7e23ab3f752bee6a80a`.

| Bagian sumber (urutan halaman PDF mulai 1) | Temuan | Dampak pada aplikasi |
| --- | --- | --- |
| Ibu hamil/bersalin/nifas, hlm. 12–14; bayi/balita, hlm. 15–17 | Pencocokan identitas, pemeriksaan oleh bidan, layanan penunjang dan konsultasi/rujukan. | Rencana episode maternal/anak perlu menyimpan pelaksana dan penugasan; jabatan pengurus tidak disamakan dengan profesi bidan. |
| Prasekolah/sekolah/remaja, hlm. 18–26; dewasa/lansia, hlm. 27–32 | Kajian awal perawat dibedakan dari pemeriksaan, diagnosis dan resep oleh dokter; rujukan bila diperlukan. | Pengukuran mentah dan finalisasi administratif tetap terpisah dari validasi klinis. Calon alur rujukan menyimpan keputusan petugas berwenang, tujuan dan tindak lanjut; tanpa pemicu otomatis yang kriterianya belum ditetapkan. |
| Persyaratan, tarif dan waktu pelayanan pada bagian yang sama | Ketentuan pelayanan di fasilitas Puskesmas. | Tidak otomatis mewajibkan KTP/KK/jaminan, tarif atau waktu fasilitas pada pencatatan warga Posyandu. Pembayaran/farmasi tetap di luar lingkup saat ini. |

**Terkonfirmasi:** wilayah pembinaan dari pengguna; alur fasilitas pada bagian sumber yang dibaca. **Aktif atas permintaan pengguna:** matriks administratif operational-v2, registry usia sementara, pelayanan/tindak lanjut dan lima rekap berversi. **Usulan untuk penetapan resmi:** cocokkan parameter sementara dengan SOP rinci. **Masih terbuka:** SOP/formulir Posyandu yang berlaku, eligibility, ambang/skor/rujukan, pengesahan pemetaan hitung, status versi 2026 dan penanggung jawab klinis. Lima foto belum menyelesaikan rincian ini. Telaah sumber bukan pengesahan SOP aplikasi oleh Dinkes/Puskesmas.

## Penyesuaian lanjutan setelah SOP rinci diperoleh

1. Cocokkan dokumen lokal yang berlaku, termasuk entri katalog 2026, kemudian catat nomor, versi, tanggal berlaku, penerbit dan penanggung jawab setiap SOP rinci.
2. Cocokkan kelompok usia/episode, eligibility setiap pemeriksaan, metode dan satuan, serta pembagian tugas kader dan tenaga kesehatan.
3. Telusuri aturan hasil/rujukan ke bagian dan halaman sumber yang spesifik. Jangan mengisi parameter yang tidak jelas dengan tebakan atau menyamakan hasil skrining dengan diagnosis.
4. Cocokkan kelima mapping laporan: sumber individu, pembilang/penyebut, deduplikasi kunjungan berulang, missing data, dan pemilihan hasil. Pertahankan catatan konflik pada report-mapping.md sampai diselesaikan oleh sumber yang berwenang.
5. Catat perbedaan antara pedoman nasional, dokumen lokal dan foto. Perbedaan diteruskan untuk penetapan pengelola/Puskesmas; tidak memilih aturan yang bertentangan secara diam-diam.
6. Implementasikan aturan yang sudah dapat diverifikasi beserta unit/feature test pada batas inklusif, nilai kosong, kelompok yang tidak eligible dan riwayat versi. Perubahan aturan tidak menghitung ulang snapshot terkunci.

## Metadata minimum aturan yang akan diaktifkan

Kode dan versi aturan, penerbit/sumber, nomor dokumen, halaman/bagian, kelompok eligible, satuan dan metode, parameter yang disahkan, tanggal berlaku, peninjau berwenang, status review, serta versi pemetaan laporan. Registry usia dan sumber hasil petugas sudah tersedia. Registry cutoff klinis umum belum diaktifkan; pengesahan detail tersebut masih memerlukan dokumen dan perubahan teruji.

## Status saat ini

Wilayah pembinaan sudah dikonfirmasi dan sumber lokal awal diperiksa. Program operasional telah diperluas dan diuji menggunakan data sintetis; lihat [kemajuan dan verifikasi](progress.md). Kategori usia dan pemetaan rekap sementara aktif sesuai arahan terbaru pengguna. SOP rinci, status versi 2026 dan penugasan klinis tetap perlu dicocokkan saat tersedia; lokasi kota/Puskesmas tidak perlu ditanyakan ulang.
