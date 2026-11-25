# Pemetaan persyaratan terhadap implementasi dan bukti

Pemetaan B–G merujuk studi kasus PT Inova Medika Solusindo. Bukti final berasal dari pengujian lokal tanggal 25 November 2026, bukan dari screenshot mockup. Status kelompok lulus tidak berarti seluruh kombinasi browser atau setiap skenario negatif diuji manual.

| Persyaratan | Implementasi utama | Pengujian/bukti |
|---|---|---|
| B1 master | MasterService, MasterData.tsx, schema | master-smoke.py, screenshot 04 |
| B2 pendaftaran/antrean | VisitService, Visits.tsx, queue_counters | visit-smoke.py, screenshot 05–07 |
| B3 EMR/histori | ClinicalService, Clinical.tsx | clinical-smoke.py, screenshot 08–09/16–18 |
| B4 tindakan/tarif | ClinicalService, BillingService, visit_procedures | clinical/billing-smoke.py, screenshot 10–11 |
| B5 farmasi/stok | AncillaryService, AncillaryEditor/Queue, stock_movements | ancillary-smoke.py, screenshot 12–13/23–26 |
| B6 lab/hasil/integrasi | AncillaryService, laboratory.ts, histori klinis | ancillary-smoke.py, Newman 24–26, screenshot 14–15/20–22 |
| B7 billing/print | BillingService, Billing.tsx, invoice/payment tables | billing-smoke.py, Newman, screenshot 27–34, tiga PDF |
| B8 dashboard | reporting.ts, Reports.tsx, reporting views | report-admin-smoke.py, Newman 38–44, screenshot 01–03 |
| B9/E6 autentikasi dan RBAC | AuthService, RolePolicy, AdminService, Node auth, route guard | smoke.py, security-smoke.py, Newman 03–08/45–51 |
| C1–5 Node REST | laboratory.ts, reporting.ts, auth.ts, api.ts, API.md | QA outage, Newman 24–26/38 |
| D1 duplikasi | VisitService, unique constraint/counter | visit-smoke.py dan Newman 18 |
| D2 race stok | AncillaryService dan UPDATE bersyarat | ancillary-smoke.py dan invariant SQL |
| D3 pembayaran ganda | BillingService, UUID ledger | billing-smoke.py dan Newman 33–36 |
| D4 revisi setelah invoice | ClinicalService, invoice revision | billing-smoke.py |
| D5 gangguan integrasi | Timeout Node, retry, fallback Core | ancillary-smoke.py --outage; tidak ada durable queue |
| D6 performa | Indeks, agregat, pagination, explain-reporting.sql | Plan SQL aktual pada dataset kecil; load test belum ada |
| E7/F5 Postman | postman/*.json | 51 request/133 assertion Newman lulus |
| E8/F1 Git | .gitignore, GITHUB_SETUP.md | Commit lokal; akses remote perlu verifikasi |
| E9–10 stack | Manifest dan arsitektur | Build Docker berhasil; lockfile/advisory belum lengkap |
| F1 source | frontend, backend-yii, backend-node | Source tersedia |
| F2 DB | schema.sql, empat migration, ERD.md | Migration QA dan upgrade non-destructive |
| F3 dokumentasi | README, ARCHITECTURE, API, TECHNICAL_DECISIONS | Dokumentasi Bahasa Indonesia |
| F4 bukti | screenshot, invoice, kuitansi, Newman Node | 34 gambar/3 PDF, bukti aktual |
| F5 pengujian | TEST_CASES, TESTING, scripts, log statis | 844 HTTP checks, 1.554 assertion statis, Newman |
| G pengumpulan | Data dummy, SUBMISSION_EMAIL.md | Deadline sesuai HR; email/publikasi perlu dilakukan |

Lihat [indeks bukti](evidence/FINAL_RUNTIME_EVIDENCE.md), [UAT final](FINAL_UAT.md) dan [daftar periksa](SUBMISSION_CHECKLIST.md). Screenshot pengguna/audit versi final belum tersedia, tetapi pengujian pengguna dikonfirmasi dan API diuji. Tidak ada klaim pengujian beban produksi atau audit keamanan independen.
