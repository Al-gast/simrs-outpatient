# Catatan fase 7 — Billing dan pembayaran v0.7.0

Ringkasan historis checkpoint source. Kondisi pada saat fase ini berbeda dari status pengujian v1.1.0 saat ini.

## Implementasi

Invoice otomatis empat kategori saat siap billing, replay issuance aman, total nol selesai tanpa pembayaran. Partial/full simulasi, bigint, kuitansi individual dan print text node. Visit/invoice/key advisory lock, UUID dan hash canonical, replay sebelum pengecekan saldo/revisi. Revisi tindakan unpaid atomik; setelah bayar hanya diagnosis. Retry body/key disimpan sessionStorage per user/visit/tab; storage gagal memblokir pembayaran baru, tab tutup/perangkat beda perlu rekonsiliasi. Nama pasien/poli/kasir tampilan adalah referensi kini, bukan seluruh snapshot historis.

## Verifikasi saat fase ini

Policy PHP/TS, sintaks, config dan ZIP; schema/dependency tetap. Bukti asli: [log statis fase 7](evidence/phase7-static-checks.txt).

## Keterbatasan historis

billing-smoke --db-check disiapkan, HTTP/race/print/Postman belum berjalan saat fase ini. Tidak ada refund/void/tax/asuransi/gateway.

Status terbaru dan kontrak yang menggantikan keputusan awal: [README](../README.md), [API](API.md), [alur transaksi](WORKFLOW.md), [bukti aktual](evidence/FINAL_RUNTIME_EVIDENCE.md).
