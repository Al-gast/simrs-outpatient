# Keputusan teknis dan pertimbangan desain

| Skenario | Pilihan | Alternatif dan konsekuensi |
|---|---|---|
| D1 antrean bersamaan | UPSERT counter per poli/tanggal, unique constraint, transaksi | MAX+1 tidak aman saat bersamaan; distributed lock tidak diperlukan untuk satu DB. |
| D2 stok | Lock dan UPDATE bersyarat, movement atomik | Reservasi saat resep akan menambah lifecycle; stok dicek ulang saat dispense. |
| D3 pembayaran ganda | UUID, fingerprint, invoice lock dan ledger unik | Debounce UI bukan jaminan; key harus dipakai ulang untuk retry. |
| D4 koreksi klinis | Optimistic locking, invoice revision, audit reason | Last-write-wins berisiko; biaya dibekukan setelah pembayaran pertama. |
| D5 gangguan layanan | Timeout, event idempotent dan fallback Core | Durable inbox/outbox belum tersedia; pengirim menyimpan event sendiri. |
| D6 performa | Indeks, pagination, view agregat dan snapshot read-only | Cache/Redis belum dibutuhkan; ukur pada dataset nyata sebelum optimasi. |

## Keputusan lintas modul

- Yii2 penulis tunggal data bisnis; Node untuk integrasi dan laporan. Ini mencegah aturan transaksi terduplikasi, dengan konsekuensi Node bergantung pada Core untuk autentikasi.
- PostgreSQL bigint dan string desimal pada API menghindari floating point Rupiah. BigInt digunakan untuk agregat tampilan dan progres pembayaran. Snapshot tarif menjaga histori.
- Role tetap enam dan satu role per pengguna; scope dokter berdasarkan staff_id. Tidak ada superuser klinis. Least privilege dan audit append-only untuk runtime, bukan jaminan audit kriptografis.
- Sesi browser v1.0.1 memakai HttpOnly SameSite Strict dengan Origin eksplisit dan token berumur terbatas. Bearer tidak disimpan di localStorage. v1.1.0 mencegah tab lama mencabut cookie akun lain. Logout seluruh sesi akun adalah trade-off MVP.
- Kode master otomatis via sequence, kode eksplisit lama tetap diterima; gap sequence normal. Aktivasi dan DELETE ketat terpisah, FK melindungi histori. Alasan perubahan klinis bergantung pada riwayat tersimpan, termasuk save kosong.
- v1.0.3 menggunakan dedicated workspace dan review; tidak menambah status tersimpan. Draft klinis belum tersimpan tidak dipersistenkan ke browser. Nomor transaksi hanya diformat, pencetakan DOM text node mengurangi risiko injeksi.
- v1.0.4 memisahkan cash received berdasarkan paid_at dari total invoice kelompok tanggal kunjungan. Snapshot bukan saldo historis per akhir periode. Summary dan detail dapat berbeda antar request. Nama staff melalui join, bukan N+1. Audit diterjemahkan tanpa mengubah kode filter eksak.
- BR-001 dan BR-002 ditunda karena bukan syarat eksplisit dan berisiko menambah regression; pendaftaran hari sama tetap berlaku. Tidak mengklaim booking masa depan.

Implementasi rinci: [alur transaksi](WORKFLOW.md), [arsitektur](ARCHITECTURE.md), [API](API.md), [hasil QA](evidence/FINAL_RUNTIME_EVIDENCE.md). Pengujian beban, advisory dependency dan audit independen masih diperlukan untuk produksi.
