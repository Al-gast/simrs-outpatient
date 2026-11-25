# Catatan fase 5 — Rekam medis dan tindakan v0.5.0

Ringkasan historis checkpoint source. Kondisi pada saat fase ini berbeda dari status pengujian v1.1.0 saat ini.

## Implementasi

Rekam medis wajib keluhan/anamnesis/fisik/diagnosis dan enam vital, versi bersama, optimistic locking, tindakan multi-item, snapshot nama/tarif, alasan dan audit; histori dokter dengan pembatasan scope. Finish tanpa rekam medis ditolak. Aturan revisi biaya kemudian diperluas fase 7 dan alasan simpan pertama diperbaiki v1.0.1.

## Verifikasi saat fase ini

Lint PHP, policy dependency-free, kompilasi TS subset, sintaks TS/TSX, perbandingan schema/migration/token. Bukti asli: [log statis fase 5](evidence/phase5-static-checks.txt).

## Keterbatasan historis

clinical-smoke dan koleksi Clinical disiapkan; runtime HTTP/DB/browser belum tersedia saat fase ini.

Status terbaru dan kontrak yang menggantikan keputusan awal: [README](../README.md), [API](API.md), [alur transaksi](WORKFLOW.md), [bukti aktual](evidence/FINAL_RUNTIME_EVIDENCE.md).
