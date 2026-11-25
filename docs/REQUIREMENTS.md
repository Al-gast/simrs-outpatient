# Persyaratan dan kriteria penerimaan

Pemetaan berasal dari studi kasus: B modul, C Node, D enam kasus teknis, E teknologi, F keluaran, G data dummy. Kriteria berikut adalah spesifikasi; status hasil aktual dilaporkan terpisah di bukti pengujian.

| ID / bagian | Cakupan | Kriteria |
|---|---|---|
| R01/B1 | Master pasien, tenaga medis, poli, jadwal, tindakan, obat dan lab | CRUD, pencarian, filter, validasi, pagination, kode unik, histori terlindungi, penyesuaian stok. |
| R02/B2 | Pendaftaran dan antrean | Pilih pasien, poli, dokter/jadwal aktif; nomor antrean harian, cegah duplikasi, status pelayanan. |
| R03/B3 | Rekam medis elektronik | Keluhan/anamnesis, vital, pemeriksaan fisik, diagnosis utama/sekunder, histori, versi dan audit. |
| R04/B4 | Tindakan | Beberapa tindakan/kuantitas dengan snapshot tarif dan komponen invoice. |
| R05/B5 | Farmasi | Resep dosis/frekuensi/jumlah, cek stok saat simpan dan serah, pengurangan atomik sekali, histori. |
| R06/B6 | Lab | Permintaan multi-item, input hasil, hasil tampil bagi dokter, REST Node melalui Yii. |
| R07/B7 | Billing | Invoice empat kategori, pembayaran simulasi tunai/non-tunai, sebagian/lunas, cegah overpayment/duplikat, cetak invoice dan kuitansi individual. |
| R08/B8 | Dashboard | Kunjungan, per poli, tindakan, penerimaan, obat, status bayar; periode dan rincian. |
| R09/B9 | Autentikasi | Enam role, login/logout, otorisasi backend, scope dokter, token dicabut ditolak, kelola akun. |
| R10/C | Node.js | Integrasi lab, reporting, middleware autentikasi dan validasi, error JSON dan dokumentasi; tanpa tulis tabel inti. |
| R11/D1–D6 | Kasus teknis | Concurrency, idempotency, audit, gangguan integrasi, bukti performa. |
| R12/E–G | Pengumpulan | Tiga source, Git dapat diakses, schema/ERD/migration, arsitektur/API/instalasi/keputusan, tangkapan layar aktual, invoice/kuitansi, bukti Postman dan pengujian kritis; data dummy. |

## Sasaran nonfungsional

Correctness: constraint dan transaksi melengkapi validasi. Keamanan: RBAC backend, least privilege, SQL berparameter, hash password, tanpa data asli dan rahasia pada log. Maintainability: controller tipis, service domain, kontrak response konsisten, migration terurut. Usability: label Indonesia, navigasi konsisten, status loading/error/empty dan cetak. Performa: indeks, pagination terbatas, agregasi, hindari N+1; ukur sebelum menyatakan latensi.

## Batasan

Di luar cakupan: rawat inap, BPJS/asuransi, SATUSEHAT, pembayaran dan perangkat lab nyata, multi-fasilitas, batch/expiry inventory, katalog ICD (diagnosis free text), pembatalan/refund dan perubahan finansial pascapembayaran. Satu kunjungan per pasien/poli/tanggal lokal, satu resep dan invoice per kunjungan; total nol dapat selesai tanpa pembayaran. Cetak browser mendukung PDF. Deployment publik tidak diwajibkan. Lihat [pemetaan](TRACEABILITY.md) dan [bukti aktual](evidence/FINAL_RUNTIME_EVIDENCE.md).
