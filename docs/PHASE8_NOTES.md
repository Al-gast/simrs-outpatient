# Catatan fase 8 — Dashboard, reporting dan pengguna v0.8.0

Ringkasan historis checkpoint source. Kondisi pada saat fase ini berbeda dari status pengujian v1.1.0 saat ini.

## Implementasi

Migration 000002 menambah indeks audit/dispense dan lateral sum invoice. Reporting count kunjungan, completed kini, top 10 poli, kuantitas tindakan, kas paid_at, penggunaan obat dari dispense movement, status invoice cohort tanggal kunjungan. Maksimal 366 hari, read-only REPEATABLE READ, pool 5, timeout query 3 detik, detail paginated tanpa identitas klinis. Admin create/edit/reset/status, staff dokter unik bahkan nonaktif, username immutable, self-edit ditolak, expected_version/token_version, audit metadata saja.

## Verifikasi saat fase ini

Policy PHP/TS dan sintaks; report-admin-smoke dan explain-reporting disiapkan. Bukti asli: [log statis fase 8](evidence/phase8-static-checks.txt).

## Keterbatasan historis

Runtime dan EXPLAIN belum berjalan saat fase ini. Tidak ada klaim speedup, benchmark atau cache produksi.

Status terbaru dan kontrak yang menggantikan keputusan awal: [README](../README.md), [API](API.md), [alur transaksi](WORKFLOW.md), [bukti aktual](evidence/FINAL_RUNTIME_EVIDENCE.md).
