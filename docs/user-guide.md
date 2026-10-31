# Panduan pengurus

## Login dan pembagian akses

Akun dibuat Ketua; tidak ada registrasi publik. Empat jabatan: Ketua, Sekretaris, Bendahara, Anggota. Matriks operational-v3 memberi Ketua seluruh kemampuan administratif termasuk koreksi final, pengaturan usia, import, ekspor, analisis dan melihat nomor identitas lengkap; Sekretaris mengelola pencatatan/finalisasi/pengajuan/import/ekspor/analisis; Bendahara agregat; Anggota pencatatan tanpa finalisasi/pengajuan/persetujuan.

Hasil skrining sensitif memerlukan tambahan `sensitive.view` dan `sensitive.record`; tidak diberikan otomatis pada jabatan. Ketua mengelola grant tambahan lewat Akun & izin. Kotak bawaan jabatan tidak dapat dicabut dengan mengosongkan izin tambahan. Untuk mengurangi hak bawaan ubah jabatan/nonaktifkan akun. Ketua aktif terakhir dilindungi. Hak akun bukan bukti kompetensi klinis.

## Data Masyarakat

Tambah identitas satu kali. Tanggal lahir boleh kosong; tanggal estimasi ditandai dan tidak diperlakukan sebagai usia pasti. Tidak perlu membuat NIK/KK palsu. Filter kategori dan grafik menggunakan aturan usia terbaru untuk tampilan saat ini; arsip sasaran memakai snapshot periode.

NIK dan kontak telepon tersedia di formulir warga dan boleh kosong, termasuk bagi bayi. NIK/nomor KK harus 16 digit bila diisi dan disimpan sebagai teks sehingga nol di depan tetap ada. Nomor KK diisi melalui Data keluarga & anggota, lalu dipakai bersama oleh warga yang dihubungkan ke keluarga tersebut. Nomor identitas duplikat, termasuk pada arsip, ditolak untuk diperiksa.

Ketika mengedit, nomor tersimpan ditampilkan terpisah. Isi kolom untuk mengganti; biarkan kosong untuk mempertahankan. Centang Hapus NIK/kontak/nomor KK tersimpan bila memang ingin menghapus. Ketua melihat nomor lengkap; akun lain mendapat tampilan tersamar kecuali diberi izin identity.reveal melalui Akun & izin. Hak mencatat tidak otomatis memberi hak melihat nomor lengkap. Rekap, ekspor agregat dan analisis tidak memuat nomor ini.

Kelola keluarga melalui tautan **Data keluarga & anggota**. Tambah kepala keluarga/alamat jika diperlukan, lalu hubungkan warga pada keluarga dan wali melalui Detail warga. Relasi wali tidak boleh melingkar. Pengarsipan keluarga/warga mempertahankan histori.

Pada warga perempuan, tambahkan episode hamil/nifas/menyusui dengan tanggal dan sumber. Jenis sama tidak boleh tumpang tindih. Tutup episode dengan tanggal akhir bila selesai; jangan menghapus riwayat. Snapshot sasaran yang sudah dibuat tidak berubah.

## Data Sasaran dan pelayanan

Buat bulan/tahun. Tanggal acuan akhir bulan. Buat jadwal kegiatan bila diperlukan. **Bentuk sasaran otomatis** memilih warga berdasarkan status efektif yang tersedia dan versi usia periode; penambahan ulang tidak menduplikasi atau mengaktifkan kembali sasaran yang sengaja dikeluarkan. Warga dengan histori yang belum lengkap tetap harus ditinjau. Pemilihan manual dapat memulihkan sasaran dengan alasan.

Kehadiran harus dikonfirmasi: belum dikonfirmasi, hadir, tidak hadir. Belum dicatat bukan otomatis tidak hadir. Sasaran tanpa kunjungan dapat dikeluarkan dengan alasan. Jika identitas atau episode maternal baru dicatat setelah pembentukan sasaran, gunakan **Perbarui snapshot sasaran** pada periode terbuka dengan alasan. Periksa dampaknya pada kelompok rekap; kehadiran tetap dipertahankan dan perubahan tercatat dalam audit.

Buka **Pelayanan**. Pilih waktu WIB dalam periode dan kegiatan terkait jika ada. Setiap pengukuran memiliki status: tidak diperiksa, diperiksa, belum diketahui, tidak berlaku, menolak. Hanya status diperiksa yang menerima nilai positif; status lain mempertahankan nilai kosong.

Pilih hasil pelayanan/skrining sesuai hasil yang benar-benar diberikan petugas. Jangan memilih normal atau “Tidak” sebagai pengganti belum diperiksa. Nama petugas pemberi hasil dan sumber wajib untuk hasil pelayanan/penilaian. Aplikasi tidak menghitung diagnosis atau cutoff klinis yang belum ditetapkan.

Rujukan “Ya” memerlukan tujuan/alasan. Catat tanggal, status dan catatan tindak lanjut bila tersedia. Simpan draft, koreksi draft bila perlu, lalu finalkan administratif sesuai izin. Kunjungan ulang boleh dan tidak menggandakan kehadiran unik.

Ketua dapat **Buka koreksi final** dengan alasan pada periode terbuka/dikembalikan. Catatan lama disimpan terenkripsi dan kunjungan menjadi draft yang harus difinalkan lagi. Untuk periode terkunci, buka melalui review rekap terlebih dahulu.

## Kunjungan rumah

Dari periode, pilih **Kunjungan rumah**. Bentuk sasaran keluarga aktif, pilih keluarga, tanggal, alasan, petugas (dapat lebih dari satu), serta anggota yang benar-benar dikunjungi. Catat temuan per warga tanpa mengubah belum diketahui menjadi “Tidak”.

Simpan/edit draft lalu finalkan administratif. Jumlah keluarga unik, warga unik dan kejadian kunjungan berbeda. Koreksi final oleh Ketua mempertahankan versi sebelumnya.

## Data Rekapitulasi

Pilih periode dan salah satu lima rekap: anak; sekolah/remaja; dewasa/lansia; maternal; kunjungan rumah. Hasil diketahui terakhir per indikator dari kunjungan final dipakai untuk kategori hasil. Total hasil dibandingkan dengan warga yang mempunyai hasil indikator itu. Penyebut nol ditampilkan Tidak berlaku. Temuan rumah “Ya” dihitung satu warga minimal satu kali pada periode.

Ringkasan operasional tetap berasal dari transaksi. Setiap rekap menampilkan sumbernya: transaksi atau import historis yang dipilih. Jangan menjumlahkan kedua sumber. Kelompok maternal dapat tumpang tindih dengan kelompok usia; total populasi memakai warga unik.

Selesaikan kehadiran dan seluruh draft individu/rumah, tulis catatan lalu ajukan. Ketua dapat kembalikan atau setujui/kunci. Kunci membuat snapshot semua rekap. Buka kembali dengan alasan untuk perbaikan, lalu buat versi baru; versi lama tetap dapat dipilih dan diekspor. Perbandingan menolak selisih bila versi usia/mapping berbeda.

Unduh XLSX/PDF memakai sumber, versi dan izin yang sama dengan tampilan. Laporan masih berlabel pemetaan/usia sementara sampai SOP ditetapkan. Tidak ada tanda tangan/logo institusi yang dibuat.

## Analisis

Pilih **Lingkup analisis**: operasional umum, ringkasan semua kelompok atau satu jenis rekap. Pilih periode pembanding bila diperlukan; versi usia, mapping dan sumber harus sebanding. **Buat ringkasan lokal** menghasilkan template deterministik berlabel bukan AI. AI opsional memerlukan konfigurasi backend; tombol nonaktif menjelaskan keadaan itu. Jika tersedia, tindakan antrekan meminta konfirmasi pengiriman agregat. Tidak mengirim nama/tanggal lahir/alamat/catatan.

Agregat kecil diblokir, bukti angka divalidasi dan narasi tidak menjadi angka resmi. Muat ulang status untuk hasil antrean. Ketua meninjau/setujui/tolak dan dapat merevisi ringkasan/kesimpulan; hasil asli dipertahankan. Perubahan periode utama atau pembanding membuat hasil Perlu diperbarui dan mencegah persetujuan hasil lama.

## Import dan rekonsiliasi

Pilih periode → **Import & data historis**. Unduh template CSV; XLSX memakai header/urutan sama, satu sheet, maksimal 500 baris. Tanggal teks YYYY-MM-DD. Angka kosong bukan nol. Import warga tidak mengubah identitas existing: calon duplikat ditolak untuk diperiksa.

Foto JPG/PNG/WebP hanya untuk agregat historis; unggah privat, maksimal 8 MB/25 megapiksel. Pilih jenis laporan dan sumber. Foto tampil di dekat editor draft. OCR opsional hanya berjalan setelah provider tersedia dan Anda menyetujui pengiriman foto; tanpa provider, isi manual. Jangan mengunggah informasi yang tidak diperlukan untuk rekap.

Periksa semua baris dan sumber sel. Tambah/hapus/koreksi baris, simpan draft, centang pernyataan review lalu commit. Commit satu transaksi: bila baris tidak valid, seluruhnya dibatalkan. Checksum sama tidak menggandakan batch. Foto rekap tidak menciptakan warga atau pemeriksaan individu.

Ketua menetapkan satu sumber untuk periode/jenis melalui Rekonsiliasi, dengan alasan. Sumber historis harus telah di-commit dan cocok periode/jenis. Sumber terkunci tidak dapat diganti. Arsip menyimpan hasil pemilihan sumber pada saat kunci.

## Mengganti SOP usia nanti

Utilitas **Aturan usia** menampilkan semua versi. Buat versi baru, isi sumber/nomor dokumen dan enam batas akhir eksklusif dalam bulan penuh. Batas harus berurutan naik. Centang Sementara jika belum disahkan. Versi baru berlaku bagi periode baru; versi lama dan laporan terkunci tetap tersedia.

Perubahan cutoff atau definisi klinis memerlukan pemetaan dan pengujian tersendiri; bukan sekadar mengubah batas umur. Jabatan pengurus tidak otomatis memberikan kewenangan klinis.

Simpan sebelum berpindah halaman. Konflik revisi berarti data telah berubah: muat ulang dan cocokkan perubahan. Tabel dapat digeser dan difokuskan keyboard; menu ponsel mendukung Escape. Semua data uji adalah sintetis. Penggunaan data nyata/produksi tetap memerlukan keputusan pengelola.
