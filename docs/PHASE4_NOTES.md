# Catatan fase 4 — Pendaftaran dan antrean v0.4.0

Ringkasan historis checkpoint source. Kondisi pada saat fase ini berbeda dari status pengujian v1.1.0 saat ini.

## Implementasi

Tanggal server WIB, jadwal aktif sesuai hari, satu kunjungan per pasien/poli/hari. Shared master advisory lock, counter UPSERT dan unique constraints, rollback saat duplikat; retry deadlock/serialization terbatas. Dokter memulai kunjungan sendiri, histori administratif tanpa isi klinis. Filter dan pagination; modal sebelumnya berupa drawer.

## Verifikasi saat fase ini

Lint PHP, policy PHP/TS, sintaks dan review SQL; lihat log. Bukti asli: [log statis fase 4](evidence/phase4-static-checks.txt).

## Keterbatasan historis

visit-smoke disiapkan untuk duplikat paralel, antrean berbeda, rollback, tarif snapshot, filter, scope dan start paralel. Belum dieksekusi saat fase ini.

Status terbaru dan kontrak yang menggantikan keputusan awal: [README](../README.md), [API](API.md), [alur transaksi](WORKFLOW.md), [bukti aktual](evidence/FINAL_RUNTIME_EVIDENCE.md).
