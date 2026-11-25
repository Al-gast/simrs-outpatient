# Daftar periksa pengumpulan technical test

Keluaran sesuai studi kasus PT Inova Medika Solusindo: Git repository yang dapat diakses evaluator, tiga source, schema/ERD/migration, arsitektur/API/instalasi/keputusan, screenshot aktual, alur pasien, invoice/kuitansi, bukti Node, Postman dan pengujian kritis. Deadline yang diinformasikan HR: Rabu 25 November 2026 pukul 14.00 WIB; ikuti email asli (PDF menyebut 72 jam kalender sejak diterima).

- [x] Source, schema, ERD, empat migration dan dokumentasi.
- [x] Regression statis dan build Docker aktual.
- [x] Backup, migration-only upgrade, fingerprint 23 tabel bisnis identik.
- [x] Tujuh kelompok UAT alur utama, 34 screenshot dan tiga PDF.
- [x] QA terisolasi: 844 HTTP checks, invariant dan outage lulus.
- [x] Postman melalui Newman: 51 request, 133 assertion lulus; bukti Node melalui request 24–26/38.
- [x] Commit lokal dan targeted secret scan pada 243 file sebelum lokalisasi dokumentasi.
- [ ] Periksa ulang dokumentasi/secret scan setelah perubahan dokumentasi.
- [ ] Pastikan akses evaluator ke repository, verifikasi README dan bukti.
- [ ] Kirim email dengan URL aktual, tanpa placeholder.

## Peta bukti

| Kebutuhan | Lokasi |
|---|---|
| Dashboard | `evidence/screenshots/01-dashboard-kpi.png` dan 02–03 |
| Master dan pendaftaran | `evidence/screenshots/04-master-patient-modal.png` dan 05–06 |
| Dokter dan review | `evidence/screenshots/07-doctor-queue.png` dan 08–19 |
| Laboratorium dan farmasi | `evidence/screenshots/20-lab-queue.png` dan 21–26 |
| Tagihan dan pembayaran | `evidence/screenshots/27-billing-list.png` dan 28–34 |
| PDF | `evidence/billing/INV-000003.pdf`, `PAY-000003.pdf`, `PAY-000004.pdf` |
| Postman dan Node | `evidence/postman/newman-summary.txt`, `newman-result.json` |
| QA/SQL | `evidence/automated-qa/runtime-excerpts.txt` |
| Indeks lengkap | [Bukti aktual](evidence/FINAL_RUNTIME_EVIDENCE.md) |

Screenshot pengguna/audit final, pengujian responsive terperinci, lockfile aktual dan audit dependency belum tersedia lengkap; jangan menyatakan sebaliknya. Catat keterbatasan pada README. File mentah tetap privat. Jangan unggah PDF soal rekrutmen tanpa izin, data nyata, token atau backup. Akses repository tidak dapat digantikan ZIP semata. Tidak ada email dikirim oleh aplikasi.
