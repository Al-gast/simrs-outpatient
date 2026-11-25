# Panduan demonstrasi satu kunjungan dummy

Prasyarat: layanan sehat, migration dan seed berhasil, gunakan awalan kata sandi environment yang sedang berjalan. Buka `http://localhost:3000`. Buat pasien dummy baru per simulasi. Seed: Poli Umum Rp25.000; D001 praktik hari kerja 08.00–16.00; T001 Konsultasi Rp100.000, T002 Pemeriksaan umum Rp50.000, OB001 Rp2.500/tablet, L001 Darah lengkap Rp150.000. Periksa harga dan stok kini, karena master dapat berubah. Tanggal WIB, jadwal memeriksa hari aktif bukan jam saat ini.

| Langkah/peran | Aksi | Hasil yang diharapkan |
|---|---|---|
| 1 admin | Periksa dasbor, poli, jadwal, stok ≥10 | Data aktual, bukan angka contoh |
| 2 registration | Buat pasien dummy, cari nama/RM | RM otomatis |
| 3 registration | Daftar ke Poli Umum dan jadwal D001 hari ini | Antrean harian, waiting, Rp25.000 |
| 4 doctor | Mulai, isi keluhan/anamnesis/vital/fisik/diagnosis dummy, simpan | in_exam, versi 1 |
| 5 doctor | T001 dan T002 masing-masing 1, resep OB001 10 tablet, L001; simpan tiap tahap dan review | Rp150.000 tindakan, Rp25.000 obat, Rp150.000 lab; versi bertambah |
| 6 doctor | Selesaikan pemeriksaan setelah review | services_pending jika lab/resep menunggu |
| 7 laboratory | Catat hasil dummy via Node | Selesai; HTTP sukses; key replay aman |
| 8 pharmacy | Konfirmasi penyerahan | Stok berkurang 10 sekali; ready_for_billing dan invoice otomatis |
| 9 cashier | Periksa empat kategori biaya, bayar Rp150.000 tunai | Total Rp350.000, sisa Rp200.000, partial |
| 10 cashier | Bayar Rp200.000 non-tunai simulasi | Lunas, saldo 0, completed, dua kuitansi |
| 11 cashier | Cetak invoice dan dua kuitansi | A4, nominal dan ID benar; matikan header/footer browser |
| 12 admin | Filter periode hari ini, buka rincian dan audit | Angka aktual mencakup semua transaksi; kas berdasarkan paid_at, tagihan tanggal kunjungan |
| 13 doctor | Lihat histori kunjungan selesai | Hasil lab/resep/rekam medis sesuai scope |

Tindakan, resep, dan lab boleh dilewati bila tidak diperlukan; jangan membuat layanan fiktif pada sistem nyata. Nilai klinis demonstrasi hanya dummy, bukan saran medis. Logout antarperan atau gunakan profil berbeda. Refresh memulihkan sesi belum kedaluwarsa. Hasil lab dapat juga disimpan sebelum finalisasi; invoice baru diterbitkan setelah pemeriksaan dan semua layanan selesai.

## Bukti aktual

Skenario 25 November 2026: Andi Saputra (Dummy), RM-000004, UMUM-001, kunjungan 4, RX-000002, LAB-000002, INV-000003 Rp350.000, PAY-000003 Rp150.000 dan PAY-000004 Rp200.000. Stok OB001 90→80. Lihat [indeks bukti](evidence/FINAL_RUNTIME_EVIDENCE.md). Request lab Node dibuktikan melalui Newman, bukan tangkapan Network.

Untuk demonstrasi teknis gunakan QA terisolasi: duplikasi pendaftaran, persaingan stok, replay pembayaran, revisi invoice sebelum pembayaran, stale version, outage Node dan fallback. Jangan ubah invoice lunas untuk sekadar demonstrasi. Simpan Request ID, ID kunjungan, nomor transaksi dan log tanpa token/rahasia.
