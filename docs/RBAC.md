# Kontrol akses berdasarkan peran (RBAC)

Keterangan: K = kelola, B = baca, A = aksi pelayanan, T = hanya kunjungan yang ditugaskan, — = dilarang. Administrator bukan pelaku klinis atau kasir universal.

| Sumber daya | admin | registration | doctor | pharmacy | laboratory | cashier |
|---|---|---|---|---|---|---|
| Pengguna | K | — | — | — | — | — |
| Master pasien | K | K | B/T | — | — | — |
| Poli, tenaga medis, jadwal | K | B | B | — | — | — |
| Master tindakan/obat/lab | K | — | B | B obat | B lab | — |
| Penyesuaian stok | K | — | — | — | — | — |
| Pendaftaran dan antrean | B | A | B/T | — | — | — |
| Rekam medis/diagnosis | — | — | A/T | — | — | — |
| Tindakan/resep/permintaan lab | — | — | A/T | B resep | B order lab | — |
| Penyerahan obat | — | — | B/T | A | — | — |
| Hasil lab dan integrasi | — | — | B/T | — | A | — |
| Invoice dan pembayaran | B | — | — | — | — | A |
| Dasbor dan rincian agregat | B | — | — | — | — | — |
| Metadata audit | B | — | — | — | — | — |

Semua akun aktif dapat membuka profil sendiri dan logout. Backend mengotorisasi setiap request, menu frontend hanya pembatas kenyamanan. Dokter terbatas `visits.doctor_id = users.staff_id`; riwayat lintas dokter hanya baca untuk pasien dengan kunjungan yang sedang diperiksa hari ini dan ditugaskan, dengan audit akses. Riwayat administratif pendaftaran tidak mengandung isi klinis. Farmasi, lab dan kasir menerima identitas minimal pasien dari DTO modulnya, bukan akses umum master pasien. Administrator tidak boleh meresepkan, mencatat hasil, menyerahkan obat atau membayar.

## Validasi akses

401 untuk bearer hilang/tidak valid/dicabut; 403 untuk peran tidak sesuai; 404 untuk objek di luar ruang lingkup dokter agar tidak membocorkan keberadaan objek. Setiap ID path/body divalidasi. Node melakukan introspeksi ke Yii untuk setiap request terlindungi; header role tidak dipercaya. Frontend mengarahkan sesi tidak valid ke login dan menampilkan penolakan untuk rute terlarang.

## Aturan akun dan data master

- Satu peran per akun; doctor wajib terkait tenaga medis berprofesi dokter. Binding dokter yang sudah ada tidak dapat dipindah. Username lowercase/trim; password baru/reset 12–72 byte UTF-8. Self-edit ditolak; tidak ada hapus permanen akun. Perubahan berhasil menaikkan `token_version` dengan `expected_version`.
- Master: aktivasi/deaktivasi melalui PATCH; DELETE ketat `?mode=permanent` hanya jika tidak ada referensi/stock. DELETE lama dapat fallback ke deaktivasi sesuai kebijakan. Jadwal aktif yang bertabrakan ditolak; dokter/poli terkait jadwal aktif tidak boleh dinonaktifkan sembarang.
- Audit admin hanya metadata, tanpa password/hash, alasan atau snapshot klinis. Hak akses database membatasi Node ke view pelaporan. Cookie sesi tidak dapat menggantikan bearer pada API bisnis.

Rincian kontrak: [API](API.md). Bukti aktual pengujian penolakan akses: [QA](evidence/automated-qa/runtime-excerpts.txt), [Newman](evidence/postman/newman-summary.txt), termasuk 403 laporan oleh kasir.
