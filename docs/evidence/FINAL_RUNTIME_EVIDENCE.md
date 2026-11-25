# SIMRS Outpatient v1.1.0 — Runtime evidence

Tanggal pengujian: 25 November 2026 (WIB). Semua pasien dan transaksi adalah data dummy. Screenshot adalah file asli yang dikirim pengguna dari aplikasi lokal, bukan mockup. PDF berasal dari fitur cetak aplikasi.

## Browser evidence

- `screenshots/01-dashboard-kpi.png` — capture 10:21:40 WIB.
- `screenshots/02-dashboard-trends.png` — capture 10:21:53 WIB.
- `screenshots/03-dashboard-payment-status.png` — capture 10:22:05 WIB.
- `screenshots/04-master-patient-modal.png` — capture 10:22:59 WIB.
- `screenshots/05-registration-form.png` — capture 10:27:27 WIB.
- `screenshots/06-registration-success.png` — capture 10:27:42 WIB.
- `screenshots/07-doctor-queue.png` — capture 10:29:41 WIB.
- `screenshots/08-clinical-record.png` — capture 10:32:36 WIB.
- `screenshots/09-clinical-vitals.png` — capture 10:32:51 WIB.
- `screenshots/10-procedures.png` — capture 10:34:28 WIB.
- `screenshots/11-procedures-total.png` — capture 10:34:38 WIB.
- `screenshots/12-prescription.png` — capture 10:35:29 WIB.
- `screenshots/13-prescription-total.png` — capture 10:35:39 WIB.
- `screenshots/14-lab-order.png` — capture 10:36:08 WIB.
- `screenshots/15-lab-estimate.png` — capture 10:36:32 WIB.
- `screenshots/16-clinical-review.png` — capture 10:36:54 WIB.
- `screenshots/17-review-saved-data.png` — capture 10:37:14 WIB.
- `screenshots/18-review-total.png` — capture 10:37:27 WIB.
- `screenshots/19-ancillary-status.png` — capture 10:39:50 WIB.
- `screenshots/20-lab-queue.png` — capture 10:40:49 WIB.
- `screenshots/21-lab-result.png` — capture 10:41:23 WIB.
- `screenshots/22-lab-completed.png` — capture 10:41:38 WIB.
- `screenshots/23-pharmacy-queue.png` — capture 10:43:30 WIB.
- `screenshots/24-pharmacy-stock-before.png` — capture 10:43:49 WIB.
- `screenshots/25-pharmacy-stock-after.png` — capture 10:44:01 WIB.
- `screenshots/26-pharmacy-completed.png` — capture 10:44:12 WIB.
- `screenshots/27-billing-list.png` — capture 10:46:25 WIB.
- `screenshots/28-billing-workspace.png` — capture 10:46:41 WIB.
- `screenshots/29-invoice-items.png` — capture 10:46:50 WIB.
- `screenshots/30-billing-partial-list.png` — capture 10:47:17 WIB.
- `screenshots/31-payment-progress.png` — capture 10:47:27 WIB.
- `screenshots/32-partial-payment-receipt.png` — capture 10:47:37 WIB.
- `screenshots/33-paid-invoice-receipts.png` — capture 10:49:17 WIB.
- `screenshots/34-billing-paid-list.png` — capture 10:49:29 WIB.

## Billing

- `billing/INV-000003.pdf`: invoice Rp350.000, lunas.
- `billing/PAY-000003.pdf`: pembayaran tunai Rp150.000.
- `billing/PAY-000004.pdf`: pembayaran non-tunai simulasi Rp200.000.

## Automated QA

- `automated-qa/runtime-excerpts.txt`: kutipan terpilih dari log terminal aktual yang dikirim pengguna, bukan raw report lengkap. QA project simrs-qa-20261125-105055-5845058e; 844 HTTP checks, 656 PHP assertions, 10 invariant queries sebelum dan sesudah outage. Pesan permission denied merupakan negative test yang diharapkan. Query timings berasal dari dataset dummy kecil, bukan load test.

## Newman / Postman

Hasil eksekusi aktual pada MacBook: 51/51 request, 133/133 assertions, exit code 0. File `postman/newman-result.json` dan `postman/newman-summary.txt` sudah dibuat langsung pada MacBook dan tidak dimasukkan dalam ZIP ini agar tidak menimpa hasil asli. Request 24–26 mencakup integrasi Node.js laboratorium; 38 reporting Node.js; 45 penolakan akses kasir (403).

## Batasan

Screenshot final manajemen pengguna/audit log tidak tersedia dalam batch ini; pengujian tersebut dikonfirmasi pengguna dan API diuji dalam automated QA. Tidak ada screenshot Postman GUI karena collection dijalankan melalui Newman CLI. Pengujian UI bukan bukti production readiness. Jangan commit raw Newman report, .env, database dump atau kredensial.
