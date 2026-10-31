# Pemetaan rekap aktif

Versi operasional: `operational-v1`. Versi lima rekap: `service-recap-v1`. Kelima foto sudah diperiksa secara visual sebagai referensi kolom, bukan sumber diagnosis. Angka tulisan tangan tidak disalin otomatis ke transaksi. Pengguna mengizinkan pengelompokan sementara agar aplikasi dapat dipakai sekarang dan diperbarui setelah dokumen pasti tersedia.

## Kelompok dan sumber

| Rekap | Transaksi yang dipakai | Referensi foto |
| --- | --- | --- |
| Bayi, balita, prasekolah | Sasaran kategori infant/toddler/preschool dan kunjungan individu final | 63073.jpg |
| Sekolah dan remaja | Sasaran school/adolescent dan kunjungan individu final | 63074.jpg |
| Dewasa dan lansia | Sasaran adult/elderly dan kunjungan individu final | 63075.jpg |
| Hamil, nifas, menyusui | Flag episode maternal pada snapshot sasaran dan kunjungan individu final | 63072.jpg |
| Kunjungan rumah | Sasaran keluarga, kunjungan rumah final, anggota dan petugas yang dicatat | 63071.jpg |

Aturan awal `temporary-2026-v1`: bayi 0–11 bulan, balita 12–59 bulan, prasekolah 60–71 bulan, sekolah 6–14 tahun, remaja 15–18 tahun, dewasa 19–59 tahun, lansia 60+. Batas eksklusif bulan penuh: [12,60,72,180,228,720]. Ini konfigurasi sementara, bukan klaim batas resmi Dinkes. Subkelompok anak pada rekap memakai 0–5 bulan dan ≥6 bulan dalam kelompok anak untuk menghindari tumpang tindih foto.

Usia dihitung pada tanggal acuan periode. Tanggal lahir kosong/estimasi menghasilkan general dan dicatat sebagai sasaran belum terklasifikasi, tidak ditebak masuk kelompok usia. General tidak ikut empat rekap kelompok usia/maternal kecuali mempunyai episode maternal yang sesuai. Maternal dapat tumpang tindih dengan usia; jumlah populasi memakai identitas unik, tidak menjumlahkan kategori tersebut.

Versi usia melekat pada periode baru. Snapshot menyimpan usia, gender dan flag maternal; perubahan data warga tidak diam-diam mengganti snapshot lama. Pada periode terbuka, pengurus dapat memperbarui snapshot sasaran secara eksplisit dengan alasan/audit. Periode submitted/locked menolak perubahan sumber.

## Aturan penghitungan transaksi

| Kode/pola | Nilai | Penyebut |
| --- | --- | --- |
| targets | Warga sasaran unik yang aktif dalam target dan tidak dikecualikan | Total sasaran untuk persentase total |
| present / absent / unconfirmed | Kehadiran unik sesuai status terkonfirmasi; belum dicatat tetap unconfirmed | targets |
| targets_<kelompok> / present_<kelompok> | Sasaran/hadir unik dalam snapshot kelompok | Sasaran subkelompok untuk present |
| <indikator>.recorded | Warga dengan hasil diketahui terakhir dari kunjungan final | Tidak ditetapkan |
| <indikator>.<hasil> | Warga yang hasil diketahui terakhirnya sama dengan hasil tersebut | <indikator>.recorded |
| <pengukuran>.measured | Warga pernah diukur pada kunjungan final | Hadir unik |
| household_targets | Keluarga sasaran periode | Tidak ditetapkan |
| visited_households | Keluarga unik dengan kunjungan rumah final | Keluarga sasaran |
| home_visits | Jumlah kejadian kunjungan rumah final | Tidak ditetapkan |
| visited_residents / <kelompok>.visited | Warga unik yang dikunjungi | Tidak ditetapkan |
| <kelompok>.<temuan rumah> | Warga unik dengan minimal satu jawaban Ya | Warga unik yang mempunyai jawaban Ya/Tidak untuk temuan itu |

Kunjungan individu diurutkan visited_at lalu id. Pemilihan hasil dilakukan per indikator: hasil kosong/tidak diperiksa/belum diketahui/tidak berlaku/menolak pada kunjungan berikutnya tidak menghapus hasil diketahui sebelumnya. Ini aturan pemetaan sementara yang terlihat pada aplikasi, bukan penetapan hasil klinis terbaru oleh tenaga kesehatan.

Hasil pelayanan disimpan dalam kolom bertipe pada `clinical_examinations`; katalog kode, pilihan dan eligibility ada di `App\Services\ClinicalFields`. Termasuk perkembangan/gizi anak, skrining sekolah/remaja/dewasa/lansia, hasil maternal, pemberian/konsumsi layanan, edukasi dan rujukan. Nilai diketahui wajib mempunyai nama petugas dan sumber. Pengukuran mentah tidak otomatis menjadi kategori normal/abnormal. Klasifikasi sensitif disaring berdasarkan sensitive.view pada halaman, arsip, ekspor dan bukti analisis; sensitive.record diperlukan untuk pencatatan.

Temuan rumah menghitung Ya jika ada minimal satu temuan Ya pada periode, sekalipun kunjungan lain Tidak. Anggota, keluarga dan kejadian tetap unit berbeda. Maternal pada rumah dapat tumpang tindih dengan umur. Banyak petugas tidak menggandakan kejadian.

Persentase dibulatkan dua desimal. Penyebut nol/null menghasilkan null, bukan 0%. Nilai transaksi nol berarti tidak ada kejadian yang memenuhi aturan; tidak menyatakan semua orang normal. Hasil belum diperiksa dibedakan dari hasil negatif.

## Operasional dan penutupan

Ringkasan operasional tetap bersumber transaksi: targets, present, absent, unconfirmed, attendance_coverage, finalized_visits, draft_visits, weight_measured, blood_sugar_measured, draft_home_visits dan unclassified_targets. Cakupan pengukuran operasional memakai hadir unik, bukan eligibility klinis.

Pengajuan menolak kehadiran belum terkonfirmasi dan draft kunjungan individu/rumah. Persetujuan membuat snapshot immutable berisi angka operasional, lima rekap, versi usia, mapping dan pilihan sumber. Buka kembali beralasan memungkinkan perbaikan dan versi baru tanpa menimpa arsip. Halaman dan ekspor memakai pembaca ReportData yang sama. Perbandingan tidak memberi selisih untuk usia/mapping/sumber yang tidak sebanding.

## Import historis

Satu sumber dipilih per periode/jenis: transaksi ATAU batch agregat historis committed. Keduanya tidak dijumlahkan. Batch menyimpan kode, nilai dan source_cell; tidak membuat identitas atau kunjungan individu.

Baris yang tidak diisi pada import tetap null (Belum diketahui), bukan nol. Penyebut hadir menggunakan targets jika diimpor; hasil kategori menggunakan <indikator>.recorded jika tersedia. Penyebut lain yang tidak dapat ditelusuri tidak dibuat. Metadata menyimpan source=legacy_import dan import_batch_id. Ringkasan operasional tetap transaksi. Mengganti sumber memerlukan izin persetujuan dan import, alasan, serta periode terbuka.

## Hal yang menunggu dokumen pasti

| Referensi | Rincian yang perlu dicocokkan |
| --- | --- |
| 63073 | Foto bayi 0–6 vs rujukan 0–5 bulan; eligibility, definisi pertumbuhan dan layanan |
| 63074 | Cutoff lingkar perut, anemia, gejala TBC, butir HEEADSSS dan kewenangan |
| 63075 | Skor jiwa, celah skor PUMA=6, AKS dan enam SKILAS, cutoff klinis |
| 63072 | Arti warna BB/LILA/TD, KEK, periode episode, eligibility layanan |
| 63071 | Definisi akses/tanda bahaya/obat, eligibility dan tindak lanjut |

Aplikasi mencatat hasil petugas tanpa mengeksekusi cutoff yang belum ditetapkan. Dokumen resmi nanti ditelusuri nomor/versi/halaman, diuji pada batas inklusif dan missing, lalu diterapkan melalui perubahan berversi. Lihat [acuan SOP](sop-references.md).
