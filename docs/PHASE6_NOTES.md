# Catatan fase 6 — Farmasi dan laboratorium v0.6.0

Ringkasan historis checkpoint source. Kondisi pada saat fase ini berbeda dari status pengujian v1.1.0 saat ini.

## Implementasi

Satu resep dan order lab per visit, maksimal 20 item, draft replace/clear, versi bersama, snapshot dan validasi master/stok. Tidak ada reservasi. Dispense atomik dengan shared master/visit lock dan ID obat terurut; rollback seluruhnya bila stok kurang. Lab free text via Node atau fallback Yii, bearer/Request ID/UUID asli, key+visit lock, hash dan ledger; hasil immutable. Lab dapat selesai saat in_exam tanpa memajukan visit; setelah finish layanan terakhir memicu ready_for_billing. Tidak ada durable queue, retry draft di memori.

## Verifikasi saat fase ini

Policy statis dan sintaks; schema/migration/dependency tidak berubah. Bukti asli: [log statis fase 6](evidence/phase6-static-checks.txt).

## Keterbatasan historis

ancillary-smoke --db-check/--outage dan koleksi disiapkan, runtime/SQL/concurrency/Postman/browser belum dieksekusi saat fase ini.

Status terbaru dan kontrak yang menggantikan keputusan awal: [README](../README.md), [API](API.md), [alur transaksi](WORKFLOW.md), [bukti aktual](evidence/FINAL_RUNTIME_EVIDENCE.md).
