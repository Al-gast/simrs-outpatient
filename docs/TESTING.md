# Panduan verifikasi v1.1.0

## Hasil aktual

Pada 25 November 2026, MacBook M1: build Docker React/Node/Yii/migrate dan pengujian paket lulus; migration 000004 diterapkan tanpa seed ulang; fingerprint 23 tabel bisnis identik; tujuh kelompok UAT utama lulus; 844 pemeriksaan HTTP, 656 assertion PHP, 10 invariant DB sebelum/sesudah outage, dan 51 request Newman dengan 133 assertion lulus. Regression statis 1.554 assertion (termasuk 656 PHP) dicatat sebelum merge lokal JSONB. Jangan menjumlahkan angka yang tumpang tindih sebagai test coverage. Bukti: [indeks aktual](evidence/FINAL_RUNTIME_EVIDENCE.md), [statis](evidence/v1.1.0-static-checks.txt), [Newman](evidence/postman/newman-summary.txt).

## Menjalankan QA terisolasi

Pastikan Docker Desktop berjalan, port 3000/3001/8080 bebas. Hentikan stack normal tanpa `-v`. Jangan jalankan smoke test mutasi pada DB UAT existing.

```bash
python3 scripts/qa-runtime.py --dry-run --outage
python3 scripts/qa-runtime.py --outage --keep
```

Runner membuat rahasia, proyek Compose dan volume terpisah, build sequential, health, rollback/reapply migration pada data dummy, policy, HTTP, grant/invariant, EXPLAIN dan outage Node. `--keep` mempertahankan stack untuk Newman; gunakan direktori/nama proyek yang dicetak. Tanpa opsi itu hanya QA terisolasi dihapus. Simpan `qa-results/<run-id>/summary.json` dan log privat; jika preflight gagal, simpan error terminal. Perbaiki root cause dan jalankan ulang pada QA baru, jangan ubah label hasil yang gagal.

| Tahap | Skrip | Bukti yang diperiksa |
|---|---|---|
| Dasar | `smoke.py` | Login enam role, penolakan dan revokasi |
| Master | `master-smoke.py` | CRUD, validasi, hapus/status, stok |
| Pendaftaran | `visit-smoke.py` | Duplikat bersamaan, nomor unik, penugasan |
| Klinis | `clinical-smoke.py --db-check` | Versi stale, snapshot, histori dan audit |
| Penunjang | `ancillary-smoke.py --db-check` | Race stok, rollback multi-item, replay lab |
| Billing | `billing-smoke.py --db-check` | Empat kategori, revisi, dua kasir, pembayaran sebagian/lunas |
| Laporan/admin | `report-admin-smoke.py` | Perbandingan SQL, filter, akun |
| Keamanan | `security-smoke.py` | IDOR lintas dokter, replay penyesuaian stok, sesi cookie/bearer lama |
| DB | `verify-db-grants.sh`; `verify-invariants.sh` | Operasi terlarang ditolak, invariant transaksi |
| Gangguan | `ancillary-smoke.py --db-check --outage` | Node berhenti/pulih, fallback Core |
| Performa | `explain-reporting.sql` | Plan SQL dan buffer, bukan benchmark beban |

## Regression statis dan paket

```bash
sh scripts/check-static.sh
python3 scripts/check-release.py
```

Subset statis membutuhkan PHP CLI, Node/npm dan TypeScript global. Memeriksa sintaks, policy dan helper frontend, DOM double untuk print, routing, format, payload akun, grafik/agregat, serta pemetaan 192 request di sembilan collection dan 198 skrip JS Postman. Tidak menggantikan full build/Vitest atau DB nyata. Build paket berjalan dalam Docker. `frontend/tests/` dan `backend-node/tests/` memuat pengujian berbasis paket. Uji browser khusus tetap diperlukan untuk responsive, popup, keyboard, navigation guard, kondisi storage dan cross-tab.

## Postman/Newman

Ikuti [panduan koleksi](../postman/README.md). End-to-End terdiri dari 51 request berurutan. Hasil aktual sudah lulus melalui Newman. Pengujian sequential bukan bukti concurrency; gunakan QA paralel dan invariant SQL. Simpan ringkasan yang telah disaring; laporan mentah dapat memuat kredensial.

## Performa dan batasan bukti

Catat versi PostgreSQL, CPU/RAM, jumlah baris, periode, query/plan/buffer, waktu SQL dan HTTP secara terpisah. EXPLAIN ANALYZE menjalankan query. Dataset dummy kecil tidak membuktikan SLA/throughput. Hasil terpilih ada pada log QA; dependency audit, lockfile, full responsive coverage dan penilaian keamanan independen belum terverifikasi lengkap.

| ID/tahap | Tanggal/lingkungan | Harapan | Observasi | Status | Bukti |
|---|---|---|---|---|---|
| Contoh format pencatatan | Isi setelah eksekusi | Isi | Isi | Lulus/gagal/terblokir | Path log/screenshot |

Gunakan data dummy, jangan publish `.env`, token, backup, laporan raw atau data medis asli.
