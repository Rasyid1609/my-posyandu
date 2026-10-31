# Kebutuhan dan keputusan — Posyandu Jeruk

Sumber kebutuhan: seluruh prompt-posyandu-jeruk.md, lima foto 63071–63075 yang telah diperiksa, dan arahan pengguna. Angka tulisan tangan tidak dijadikan seed atau transaksi individu.

## Terkonfirmasi

- Satu Posyandu Jeruk; tiga menu bisnis Data Masyarakat, Data Sasaran, Data Rekapitulasi; empat jabatan tanpa Wakil.
- Identitas warga tunggal; pelayanan individu di Data Sasaran; rekap otomatis dan berjejak audit.
- Pembinaan Kota Madiun/Puskesmas Manguharjo Kota Madiun. RT/RW, kelurahan dan alamat Posyandu belum ditetapkan dari informasi terbaru.
- PostgreSQL pengembangan 127.0.0.1:5432/posyandu_jeruk. Rahasia disimpan lokal, akun existing dipertahankan.
- Pengguna meminta program dilengkapi sekarang dengan batas usia sementara dan diperbarui setelah dokumen pasti tersedia.
- Matriks administratif diaktifkan atas permintaan pengguna. Pengembangan memakai data sintetis; izin data nyata belum diberikan.

## Aturan usia sementara yang diimplementasikan

| Kelompok tampilan/sasaran | Bulan penuh | Usia tahun |
| --- | --- | --- |
| Bayi | 0–11 | <1 |
| Balita | 12–59 | 1–4 |
| Prasekolah | 60–71 | 5 |
| Sekolah | 72–179 | 6–14 |
| Remaja | 180–227 | 15–18 |
| Dewasa | 228–719 | 19–59 |
| Lansia | ≥720 | ≥60 |
| Belum diketahui | tanggal kosong/estimasi/tidak valid pada acuan | tidak ditebak |

Versi awal temporary-2026-v1, dengan batas akhir eksklusif [12,60,72,180,228,720] bulan. Ini keputusan implementasi sementara atas permintaan pengguna, bukan kutipan standar Dinkes. Kelompok maternal adalah episode bertanggal yang dapat tumpang tindih dengan usia. Subkelompok rekap anak 0–5 bulan dan ≥6 bulan dipisahkan tanpa menambah identitas/kehadiran.

Ketua membuat versi baru beserta sumber lewat Aturan usia. Versi immutable; periode baru memilih versi terakhir. Snapshot lama tidak dikategorikan ulang. Mengubah cutoff/eligibility klinis memerlukan pemetaan dan pengujian tersendiri.

## Matriks administratif operational-v3

| Kemampuan | Ketua | Sekretaris | Bendahara | Anggota |
| --- | --- | --- | --- | --- |
| Agregat | Ya | Ya | Ya | Ya |
| Identitas/keluarga/wali/episode | Ya | Ya | Tidak | Ya |
| Sasaran/kegiatan/kehadiran | Ya | Ya | Tidak | Ya |
| Catat pelayanan dan rumah | Ya | Ya | Tidak | Ya |
| Finalisasi administratif | Ya | Ya | Tidak | Tidak |
| Ajukan rekap | Ya | Ya | Tidak | Tidak |
| Setujui/kunci/buka ulang/pilih sumber | Ya | Tidak | Tidak | Tidak |
| Buka koreksi final beralasan | Ya | Tidak | Tidak | Tidak |
| Ekspor agregat, import, analisis | Ya | Ya | Tidak | Tidak |
| Akun/pengaturan usia | Ya | Tidak | Tidak | Tidak |
| NIK/KK/kontak lengkap | Ya | Grant tambahan | Tidak secara default | Grant tambahan |
| Hasil sensitif | Grant tambahan terpisah | Grant tambahan terpisah | Tidak secara default | Grant tambahan terpisah |
| Validasi klinis/diagnosis otomatis | Tidak tersedia | Tidak tersedia | Tidak tersedia | Tidak tersedia |

Total 17 permission: Ketua mewarisi 15, Sekretaris 11, Bendahara 1, Anggota 6. identity.reveal memberi akses nomor identitas/kontak lengkap; akun dengan residents.view tanpa izin tersebut melihat nomor tersamar. Pencatatan tetap memerlukan residents.manage dan residents.view. sensitive.view dan sensitive.record tidak diwariskan otomatis, termasuk pada Ketua. Tambahan dapat diberikan pada akun sesuai penugasan. Izin langsung tidak mencabut bawaan role. Semua route/query/job/download diperiksa backend. Matriks adalah keputusan aplikasi, bukan pengesahan kompetensi oleh Dinkes.

## Sudah diimplementasikan

Keluarga/warga/wali dan riwayat; episode maternal; aturan usia berversi; periode/kegiatan/sasaran otomatis dan manual; kehadiran; pengukuran dan hasil petugas per kelompok; edukasi/rujukan/tindak lanjut; rumah multi-warga/petugas; koreksi final dengan arsip terenkripsi; lima registry rekap; penguncian/snapshot/arsip/komparasi/ekspor; ringkasan lokal dan adapter AI/OCR antrean; import draft/verifikasi/commit/sumber historis; audit dan kontrol akses.

Provider eksternal menggunakan konfigurasi backend, default nonaktif. Tanpa key, alur inti dan import manual tetap berjalan. Pengujian provider memakai HTTP fake dan tidak menimbulkan biaya API. Model tidak di-hardcode. Tidak ada hasil AI/OCR demo yang diklaim nyata.

## Masih perlu konfigurasi atau pengesahan

SOP klinis rinci/eligibility/ambang, versi formulir Dinkes/Puskesmas yang berlaku, penanggung jawab klinis, penugasan hasil sensitif, kebijakan data nyata/retensi/provider, serta deployment produksi. Hasil penelusuran resmi dan status dokumen 2026 ada di [sop-references.md](sop-references.md).

Pencatatan manual hasil petugas tidak bergantung pada cutoff otomatis. Tidak ada tebakan diagnosis. Laporan aplikasi memakai mapping sementara dan belum dinyatakan sebagai formulir resmi disahkan.

## Di luar lingkup

Multi-Posyandu/SaaS, portal warga, kas/keuangan, stok/resep/pembayaran, integrasi pemerintah/WhatsApp otomatis, native mobile, prediksi penyakit dan offline. Ekspor identitas sensitif tidak tersedia. NIK/KK/kontak tersedia sebagai kolom opsional sesuai kebutuhan dokumen awal dan koreksi pengguna 16 Oktober 2026; bayi tidak wajib memiliki NIK.

Hasil pengujian aktual dan sisa kesiapan lingkungan dicatat di [progress.md](progress.md); fitur berjalan dibedakan dari konfigurasi provider atau kesiapan produksi.
