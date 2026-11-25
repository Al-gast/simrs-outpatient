# Catatan fase 9 — Pengujian dan hardening v0.9.0

Ringkasan historis checkpoint source. Kondisi pada saat fase ini berbeda dari status pengujian v1.1.0 saat ini.

## Implementasi

QA-01 validasi Rupiah tanpa strip digit; QA-02 UUID ledger adjustment; QA-03 revoke UPDATE/DELETE/TRUNCATE ledger; QA-04 password 1–72 byte tanpa NUL; QA-05 needsReload sebelum pembayaran baru; QA-06 agregasi detail invoice terbatas dan indeks antrean; QA-07 ISO timestamp; QA-08 CSP/permissions policy; QA-09 runner QA terisolasi, dokter/kasir berbeda, invariant dan grants; QA-10 fokus keyboard/drawer; QA-11 trim lab konsisten PHP/Node. Migration 000003 dan ledger adjustment, tanpa perubahan baseline.

## Verifikasi saat fase ini

618 assertion PHP, 411 TS, lint, 36 file TS/TSX sintaks, JSON/YAML/Python/shell, dry-run, ZIP. Bukti asli: [log statis fase 9](evidence/phase9-static-checks.txt).

## Keterbatasan historis

Full React build TS2688, registry DNS EAI_AGAIN, Docker tidak tersedia. Tidak ada runtime saat fase ini. Severity adalah penilaian engineering, bukan penetration test.

Status terbaru dan kontrak yang menggantikan keputusan awal: [README](../README.md), [API](API.md), [alur transaksi](WORKFLOW.md), [bukti aktual](evidence/FINAL_RUNTIME_EVIDENCE.md).
