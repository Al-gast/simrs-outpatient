# UAT terarah dan pengumpulan bukti v1.1.0

Tanggal pelaksanaan 25 November 2026 (WIB). Tujuh kelompok alur utama telah lulus berdasarkan tangkapan layar dan konfirmasi pengguna; tabel berikut membedakan bukti aktual dari cakupan tambahan yang belum diverifikasi satu per satu. Gunakan data dummy dan QA terisolasi untuk race/failure.

| ID | Skenario | Kriteria | Status/bukti |
|---|---|---|---|
| R110-01 | Backup/migration | TOC dump, fingerprint identik, empat migration, health | Lulus; terminal upgrade |
| R110-02 | Sesi enam role dan tab lama | Refresh, logout, bearer dicabut, cookie beda akun aman | Alur utama dikonfirmasi; HTTP QA; seluruh variasi browser belum dibuktikan |
| R110-03 | Kode master/status/hapus | Kode unik, referensi mencegah delete ketat | Lulus alur utama; QA master |
| R110-04 | Sidebar/modal | 7 submenu, modal tengah, tooltip, role filter | Lulus desktop; responsive detail belum dibuktikan |
| R110-05 | Kunjungan tanpa layanan opsional | Skip eksplisit, simpan pertama tanpa alasan, invoice pendaftaran | Tercakup aturan/QA; bukti browser skip khusus belum ada |
| R110-06 | Kunjungan lengkap | Versi naik, lab Node, stok sekali, empat kategori | Lulus; screenshot 05–34 dan Newman |
| R110-07 | Revisi dan stale version | Reason untuk edit, audit, revisi sebelum bayar | QA lulus; detail browser revisi belum tercatat lengkap |
| R110-08 | Pembayaran bertahap | Saldo eksak, replay, admin read-only | Lulus alur kasir dan QA; detail admin unpaid di browser belum dibuktikan |
| R110-09 | Cetak | A4 invoice dan dua kuitansi | Tiga PDF aktual; footer browser about:blank masih ada |
| R110-10 | Data sebelum upgrade | Invoice/stok lama tetap | Lulus fingerprint dan dashboard |
| R110-11 | Laporan | Total akurat, filter, modal, basis tanggal | Lulus alur utama; QA perbandingan SQL; tidak semua modal didokumentasikan |
| R110-12 | Pengguna dan audit | Nama staff, aksi terpisah, filter, proyeksi aman | Dikonfirmasi pengguna; API QA; screenshot final belum tersedia |
| R110-13 | Concurrency/outage | Duplikat, stock race, idempotency, DB grants | Lulus QA terisolasi |
| R110-14 | Responsive dan navigasi | 375/1024/desktop, keyboard, dirty guard | Belum terbukti lengkap |
| R110-15 | Postman dan pengumpulan | 51 request dan assertion, Git dapat diakses | Newman lulus; publikasi/pengiriman perlu verifikasi |

Bukti utama: [indeks aktual](evidence/FINAL_RUNTIME_EVIDENCE.md). Jangan mengubah hasil yang belum dicatat menjadi Lulus. Catat tanggal/WIB, lingkungan, ID skenario, harapan, observasi, status dan nama bukti. Checklist rinci tersedia di [QA manual](QA_MANUAL_CHECKLIST.md). Jangan mengunggah backup, `.env` atau raw token.
