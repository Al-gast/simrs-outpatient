# Panduan pengujian Postman Collection melalui Newman atau Postman Desktop

Gunakan stack dummy terisolasi, bukan DB utama. Import `SIMRS.postman_environment.json` dan isi `demo_prefix` dari `.env` stack QA secara lokal. URL Core `http://localhost:8080/api/v1`, Node `http://localhost:3001/api/v1`. Jangan share nilai prefix atau token. Lihat direktori/nama stack yang dicetak runner QA.

## Alur End-to-End

`End-to-End.postman_collection.json` berisi 51 request berurutan, satu iterasi. Tanggal dibaca dari server WIB, jadwal aktif dipilih dinamis, master/pasien dummy unik dibuat, lalu pendaftaran, klinis, lab Node, farmasi, invoice, pembayaran, laporan dan logout enam role. Assertion meliputi duplikasi, replay lab/dispense/payment, konflik payload, saldo dan RBAC. Ini pengujian sequential, bukan bukti race.

Di Postman Desktop: import koleksi dan environment, pilih environment SIMRS, jalankan Collection Runner dari awal hingga akhir. Alternatif Newman (Node/npm diperlukan): simpan salinan environment terisi di direktori privat di luar repo dan gunakan perintah berikut dengan path aktual.

```bash
npx --yes newman@6 run postman/End-to-End.postman_collection.json \
  -e "$HOME/path-private/environment.json" \
  --iteration-count 1 --bail failure
```

Untuk export JSON, gunakan `--reporters json --reporter-json-export` ke file privat. Raw report dapat memuat token, request body dan password. Buat salinan ringkasan yang hanya berisi nama request, metode, status HTTP, jumlah assertion dan kegagalan; jangan publish laporan mentah. Bersihkan nilai token/rahasia environment dan collection setelah tes. Jangan menjalankan request tengah tanpa variabel prasyarat. Timeout transaksi perlu rekonsiliasi sebelum retry; pertahankan UUID dan body semula. Jangan membuat key baru secara buta.

## Koleksi terfokus

| Koleksi | Prasyarat |
|---|---|
| SIMRS | Health Core/Node dan enam akun seed |
| MasterData | Admin dan master seed |
| Visits | Role dan daftar kunjungan |
| Clinical | `clinical_visit_id` kunjungan dummy ditugaskan |
| Ancillary | Role seed dan item lab dummy jika diperlukan |
| Billing | `billing_visit_id` kunjungan yang memiliki invoice |
| Reporting-Admin | `report_from`, `report_to` periode WIB |
| QA-Hardening | DB disposable; membuat obat dan mutasi stok |

## Hasil aktual

25 November 2026: Newman menjalankan 51/51 request dan 133/133 assertion, exit code 0. Request 24–26 menguji order/hasil lab/replay melalui Node; 38 laporan Node; 45 kasir ditolak 403. Bukti: [ringkasan](../docs/evidence/postman/newman-summary.txt) dan [JSON yang disaring](../docs/evidence/postman/newman-result.json). Tidak ada screenshot Postman GUI karena eksekusi menggunakan CLI. `python3 scripts/check-release.py` memeriksa pemetaan metode/path serta sintaks JS seluruh sembilan collection, bukan eksekusi HTTP.
