# Riwayat implementasi dan struktur proyek

Pengembangan dilakukan dalam sepuluh fase awal, dilanjutkan lima fase penyempurnaan berdasarkan UAT. Fase-fase sebelumnya merupakan checkpoint source, bukan klaim bahwa runtime telah diuji pada saat itu. Hasil final terpisah di [bukti aktual](evidence/FINAL_RUNTIME_EVIDENCE.md).

| Fase | Keluaran |
|---|---|
| 1 | Persyaratan, arsitektur, ERD, schema awal, RBAC, kontrak API, spesifikasi UI/UX dan rencana tes |
| 2 | Fondasi React/Yii2/Express, PostgreSQL, Compose, migration/seed, autentikasi, role guard dan health |
| 3 | Tujuh master, CRUD, pencarian, validasi, penghapusan aman dan stok |
| 4 | Pendaftaran, antrean dan pengujian duplikasi/concurrency |
| 5 | Rekam medis, tindakan, versi, snapshot, histori dokter |
| 6 | Farmasi, lab, penyerahan atomik, integrasi Node dan transisi |
| 7 | Invoice, pembayaran sebagian/lunas, idempotency, revisi dan cetak |
| 8 | Dashboard, reporting, pengelolaan akun, audit |
| 9 | Hardening transaksi, RBAC, QA terisolasi, pemeriksaan invariant |
| 10 | Dokumentasi, Postman, paket source v1.0.0 |
| v1.0.1 | Sesi browser, kode otomatis, status master, alasan klinis |
| v1.0.2 | Penyempurnaan UI/UX global |
| v1.0.3 | Ruang kerja klinis/billing, review, dokumen cetak |
| v1.0.4 | Dashboard, keuangan, pengguna/audit |
| v1.1.0 | Security hardening, fingerprint, pemeriksaan rilis, upgrade kumulatif dan verifikasi aktual |

```text
simrs-outpatient/
  frontend/         # React dan pengujian UI helper
  backend-yii/      # Core API, controller, service, migration, tes
  backend-node/     # Integrasi, pelaporan, middleware, tes
  database/         # Schema awal dan hak akses
  scripts/          # Regression statis dan integrasi
  docs/             # Dokumentasi dan bukti aktual
  postman/          # Koleksi dan template environment
  docker-compose.yml
  .env.example
  .gitignore
  README.md
```

## Pengumpulan

Source aktual pada MacBook mempertahankan perbaikan JSONB lokal setelah merge. Build Docker, migration, UAT, QA terisolasi dan Newman telah berjalan; commit lokal telah dibuat. Catatan rilis historis diringkas dalam Bahasa Indonesia; log asli tetap menjadi acuan angka dan keterbatasan saat masing-masing fase. Publikasi Git dan akses evaluator tetap harus diverifikasi. Lihat [checklist](SUBMISSION_CHECKLIST.md), [pemetaan persyaratan](TRACEABILITY.md) dan [panduan instalasi](../README.md). Jangan mengklaim produksi atau menambahkan fitur yang belum diuji.
