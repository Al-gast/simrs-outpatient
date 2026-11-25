# Catatan fase 3 — Master data v0.3.0

Ringkasan historis checkpoint source. Kondisi pada saat fase ini berbeda dari status pengujian v1.1.0 saat ini.

## Implementasi

Tujuh resource whitelist dengan CRUD, pencarian literal, filter, pagination, harga integer 0–999999999999, kode/RM unik. Advisory lock serialisasi master, FK dan guard jadwal/profesi, hard delete aman atau fallback deaktivasi; stok atomik dengan movement dan audit. Adjustment saat itu belum idempotent (diperbaiki fase 9). Drawer master dan role-scoped pasien dokter. PHP server empat worker; schema/Node tetap.

## Verifikasi saat fase ini

42 assertion dasar PHP + 133 master, 174 assertion TS total, 18 TS sintaks; shell/JSON/Python. Bukti asli: [log statis fase 3](evidence/phase3-static-checks.txt).

## Keterbatasan historis

Saat fase ini belum ada pengujian HTTP/SQL/browser; master-smoke disiapkan untuk DB disposable, termasuk concurrency dan duplikat.

Status terbaru dan kontrak yang menggantikan keputusan awal: [README](../README.md), [API](API.md), [alur transaksi](WORKFLOW.md), [bukti aktual](evidence/FINAL_RUNTIME_EVIDENCE.md).
